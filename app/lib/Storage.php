<?php

/**
 * Pengelolaan folder & berkas.
 *
 * Struktur folder yang dibuat otomatis di Google Drive:
 *
 *   <Folder Induk (dari tautan yang Anda berikan)>
 *     └── Self Assessment 2026 - RS Khusus THT SS Medika
 *           ├── 01. PENYELENGGARAAN LAYANAN
 *           │     ├── 1. Pelayanan Rawat Jalan
 *           │     └── 1.a Waktu tunggu rawat jalan (Target <60 menit)
 *           └── 00. PROFIL RUMAH SAKIT
 *                 └── Legalitas Kelengkapan Perizinan - 4. Izin Mendirikan Bangunan (IMB)
 *
 * Setiap folder otomatis dibagikan "siapa saja yang memiliki link dapat melihat",
 * sehingga tautan folder bisa dibuka semua pihak tanpa perlu izin tambahan.
 */
class Storage
{
    private array $cfg;
    private GoogleDrive $drive;
    public string $lastError = '';

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->drive = GoogleDrive::fromSettings($cfg);
    }

    public function drive(): GoogleDrive
    {
        return $this->drive;
    }

    public function driveAktif(): bool
    {
        return $this->drive->isConfigured() && $this->drive->rootFolderId() !== '';
    }

    // -----------------------------------------------------------------
    // Folder
    // -----------------------------------------------------------------

    /** Folder utama satu periode penilaian (dibuat sekali). */
    public function assessmentFolder(array $assessment): ?array
    {
        $existing = DB::one(
            "SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = 'root' AND owner_key = 'root'",
            [$assessment['id']]
        );
        $nama = sprintf('Self Assessment %d - %s', $assessment['tahun'], $assessment['nama_rs']);

        if ($existing && ($existing['drive_id'] || !$this->driveAktif())) {
            return $existing;
        }
        if (!$this->driveAktif()) {
            return $this->localFolder((int) $assessment['id'], 'root', 'root', $nama, $this->slug($nama));
        }

        $res = $this->drive->ensureFolder($nama, $this->drive->rootFolderId());
        if (!$res) {
            $this->lastError = $this->drive->lastError;
            return null;
        }
        return $this->saveFolder((int) $assessment['id'], 'root', 'root', $nama, $res);
    }

    /** Folder per bagian dokumen. */
    public function sectionFolder(array $assessment, string $key, string $nama): ?array
    {
        $existing = DB::one(
            "SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = 'section' AND owner_key = ?",
            [$assessment['id'], $key]
        );
        if ($existing && ($existing['drive_id'] || !$this->driveAktif())) {
            return $existing;
        }
        $parent = $this->assessmentFolder($assessment);
        if (!$parent) {
            return null;
        }
        if (!$this->driveAktif()) {
            return $this->localFolder((int) $assessment['id'], 'section', $key, $nama, $parent['local_path'] . '/' . $this->slug($nama));
        }
        $res = $this->drive->ensureFolder($nama, $parent['drive_id']);
        if (!$res) {
            $this->lastError = $this->drive->lastError;
            return null;
        }
        return $this->saveFolder((int) $assessment['id'], 'section', $key, $nama, $res);
    }

    /**
     * Folder untuk satu poin penilaian (owner_type 'item') atau baris tabel ('row').
     * Dibuat otomatis saat pertama kali dipakai.
     */
    public function pointFolder(array $assessment, string $ownerType, string $ownerKey): ?array
    {
        $existing = DB::one(
            'SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
            [$assessment['id'], $ownerType, $ownerKey]
        );
        if ($existing && ($existing['drive_id'] || !$this->driveAktif())) {
            return $existing;
        }

        [$sectionKey, $sectionName, $folderName] = $this->describe($ownerType, $ownerKey);
        if ($folderName === null) {
            $this->lastError = 'Poin tidak dikenal.';
            return null;
        }

        $parent = $this->sectionFolder($assessment, $sectionKey, $sectionName);
        if (!$parent) {
            return null;
        }
        if (!$this->driveAktif()) {
            return $this->localFolder(
                (int) $assessment['id'],
                $ownerType,
                $ownerKey,
                $folderName,
                $parent['local_path'] . '/' . $this->slug($folderName)
            );
        }
        $res = $this->drive->ensureFolder($folderName, $parent['drive_id']);
        if (!$res) {
            $this->lastError = $this->drive->lastError;
            return null;
        }
        return $this->saveFolder((int) $assessment['id'], $ownerType, $ownerKey, $folderName, $res);
    }

    /** Menentukan nama folder + bagian induk berdasarkan pemilik. */
    private function describe(string $ownerType, string $ownerKey): array
    {
        if ($ownerType === 'item') {
            $item = DB::one(
                'SELECT i.*, s.title AS section_title, s.ordering AS section_ordering, s.code AS section_code
                   FROM items i JOIN sections s ON s.id = i.section_id WHERE i.id = ?',
                [(int) $ownerKey]
            );
            if (!$item) {
                return ['', '', null];
            }
            $sectionName = sprintf('%02d. %s', $item['section_ordering'], $item['section_title']);
            $nama = trim($item['code'] . ' ' . $item['title']);
            return ['sec:' . $item['section_code'], $sectionName, $nama];
        }

        [$tableCode, $rowNo] = array_pad(explode(':', $ownerKey, 2), 2, '');
        $def = Forms::get($tableCode);
        if (!$def) {
            return ['', '', null];
        }
        $row = DB::one('SELECT data FROM form_rows WHERE table_code = ? AND row_no = ?', [$tableCode, (int) $rowNo]);
        $data = $row ? (json_decode($row['data'], true) ?: []) : [];
        $labelCol = array_values(array_diff(array_keys($def['cols']), ['no']))[0] ?? 'no';
        $label = trim((string) ($data[$labelCol] ?? ''));
        $nomor = trim((string) ($data['no'] ?? $rowNo));
        $nama = sprintf('%s - %s. %s', $def['title'], $nomor !== '' ? $nomor : $rowNo, $label);
        return ['sec:profil', '00. PROFIL RUMAH SAKIT', $nama];
    }

    private function saveFolder(int $assessmentId, string $type, string $key, string $nama, array $res): array
    {
        DB::q(
            'INSERT INTO drive_folders (assessment_id, owner_type, owner_key, nama, drive_id, drive_link)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE nama = VALUES(nama), drive_id = VALUES(drive_id), drive_link = VALUES(drive_link)',
            [$assessmentId, $type, $key, $nama, $res['id'], $res['link']]
        );
        if ($type === 'root') {
            DB::q('UPDATE assessments SET drive_folder_id = ?, drive_folder_link = ? WHERE id = ?', [$res['id'], $res['link'], $assessmentId]);
        }
        return DB::one(
            'SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
            [$assessmentId, $type, $key]
        );
    }

    private function localFolder(int $assessmentId, string $type, string $key, string $nama, string $path): array
    {
        $base = rtrim($this->cfg['app']['upload_dir'], '/');
        $rel  = trim(str_replace($base, '', $path), '/');
        if ($type === 'root') {
            $rel = $assessmentId . '-' . $this->slug($nama);
        }
        $full = $base . '/' . $rel;
        if (!is_dir($full)) {
            @mkdir($full, 0775, true);
        }
        DB::q(
            'INSERT INTO drive_folders (assessment_id, owner_type, owner_key, nama, local_path)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE nama = VALUES(nama), local_path = VALUES(local_path)',
            [$assessmentId, $type, $key, $nama, $rel]
        );
        return DB::one(
            'SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
            [$assessmentId, $type, $key]
        );
    }

    // -----------------------------------------------------------------
    // Berkas
    // -----------------------------------------------------------------

    /**
     * @param array $file satu entri dari $_FILES (name, tmp_name, size, error)
     * @return array{ok:bool,pesan:string,dokumen?:array}
     */
    public function simpanBerkas(array $assessment, string $ownerType, string $ownerKey, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'pesan' => $this->uploadErrorText((int) ($file['error'] ?? 4))];
        }
        if ($file['size'] > $this->cfg['app']['max_upload']) {
            return ['ok' => false, 'pesan' => 'Ukuran berkas melebihi batas ' . self::formatUkuran($this->cfg['app']['max_upload']) . '.'];
        }
        $nama = $this->bersihkanNamaFile($file['name']);
        $ext  = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $this->cfg['app']['allowed_ext'], true)) {
            return ['ok' => false, 'pesan' => 'Jenis berkas .' . $ext . ' tidak diizinkan.'];
        }

        $folder = $this->pointFolder($assessment, $ownerType, $ownerKey);
        if (!$folder) {
            return ['ok' => false, 'pesan' => 'Gagal menyiapkan folder: ' . ($this->lastError ?: 'tidak diketahui')];
        }

        $mime = $this->deteksiMime($file['tmp_name'], $ext);
        $namaUnik = date('Ymd-His') . '_' . $nama;

        if ($this->driveAktif() && $folder['drive_id']) {
            $res = $this->drive->uploadFile($file['tmp_name'], $namaUnik, $mime, $folder['drive_id']);
            if (!$res) {
                return ['ok' => false, 'pesan' => 'Gagal mengunggah ke Google Drive: ' . $this->drive->lastError];
            }
            $id = DB::insert('documents', [
                'assessment_id' => $assessment['id'],
                'owner_type'    => $ownerType,
                'owner_key'     => $ownerKey,
                'folder_id'     => $folder['id'],
                'nama_file'     => $nama,
                'mime'          => $mime,
                'ukuran'        => (int) $file['size'],
                'drive_id'      => $res['id'],
                'drive_link'    => $res['webViewLink'] ?? $this->drive->fileLink($res['id']),
                'uploaded_by'   => Auth::username(),
            ]);
        } else {
            $base = rtrim($this->cfg['app']['upload_dir'], '/');
            $rel  = $folder['local_path'] . '/' . $namaUnik;
            $full = $base . '/' . $rel;
            if (!is_dir(dirname($full))) {
                @mkdir(dirname($full), 0775, true);
            }
            if (!@move_uploaded_file($file['tmp_name'], $full) && !@rename($file['tmp_name'], $full)) {
                return ['ok' => false, 'pesan' => 'Gagal menyimpan berkas ke penyimpanan lokal.'];
            }
            $id = DB::insert('documents', [
                'assessment_id' => $assessment['id'],
                'owner_type'    => $ownerType,
                'owner_key'     => $ownerKey,
                'folder_id'     => $folder['id'],
                'nama_file'     => $nama,
                'mime'          => $mime,
                'ukuran'        => (int) $file['size'],
                'local_path'    => $rel,
                'uploaded_by'   => Auth::username(),
            ]);
        }

        Log::write(Auth::username(), 'unggah', $ownerType . ':' . $ownerKey . ' → ' . $nama);
        return ['ok' => true, 'pesan' => 'Berkas tersimpan.', 'dokumen' => DB::one('SELECT * FROM documents WHERE id = ?', [$id])];
    }

    public function hapusBerkas(int $assessmentId, int $docId): array
    {
        $doc = DB::one('SELECT * FROM documents WHERE id = ? AND assessment_id = ?', [$docId, $assessmentId]);
        if (!$doc) {
            return ['ok' => false, 'pesan' => 'Berkas tidak ditemukan.'];
        }
        if ($doc['drive_id'] && $this->driveAktif()) {
            $this->drive->deleteFile($doc['drive_id']);
        }
        if ($doc['local_path']) {
            $full = rtrim($this->cfg['app']['upload_dir'], '/') . '/' . $doc['local_path'];
            if (is_file($full)) {
                @unlink($full);
            }
        }
        DB::q('DELETE FROM documents WHERE id = ?', [$docId]);
        Log::write(Auth::username(), 'hapus_berkas', $doc['nama_file']);
        return ['ok' => true, 'pesan' => 'Berkas dihapus.'];
    }

    /**
     * Memindahkan berkas yang terlanjur tersimpan di server ke Google Drive
     * (dipakai bila Drive baru dihubungkan setelah beberapa dokumen diunggah).
     *
     * @return array{dipindah:int,gagal:int,sisa:int,pesan:string}
     */
    public function sinkronKeDrive(array $assessment, int $batas = 25): array
    {
        if (!$this->driveAktif()) {
            return ['dipindah' => 0, 'gagal' => 0, 'sisa' => 0, 'pesan' => 'Google Drive belum aktif.'];
        }

        $sisa = (int) DB::val(
            'SELECT COUNT(*) FROM documents WHERE assessment_id = ? AND drive_id IS NULL AND local_path IS NOT NULL',
            [$assessment['id']]
        );
        $docs = DB::all(
            'SELECT * FROM documents WHERE assessment_id = ? AND drive_id IS NULL AND local_path IS NOT NULL ORDER BY id LIMIT ' . max(1, $batas),
            [$assessment['id']]
        );

        $dipindah = 0;
        $gagal = 0;
        $base = rtrim($this->cfg['app']['upload_dir'], '/');

        foreach ($docs as $d) {
            $full = $base . '/' . $d['local_path'];
            if (!is_file($full)) {
                $gagal++;
                continue;
            }
            $folder = $this->pointFolder($assessment, $d['owner_type'], $d['owner_key']);
            if (!$folder || empty($folder['drive_id'])) {
                $gagal++;
                continue;
            }
            $res = $this->drive->uploadFile($full, basename($d['local_path']), $d['mime'] ?: 'application/octet-stream', $folder['drive_id']);
            if (!$res) {
                $gagal++;
                continue;
            }
            DB::q(
                'UPDATE documents SET drive_id = ?, drive_link = ?, local_path = NULL, folder_id = ? WHERE id = ?',
                [$res['id'], $res['webViewLink'] ?? $this->drive->fileLink($res['id']), $folder['id'], $d['id']]
            );
            @unlink($full);
            $dipindah++;
        }

        Log::write(Auth::username(), 'sinkron_drive', $dipindah . ' berkas dipindahkan ke Google Drive');
        return [
            'dipindah' => $dipindah,
            'gagal'    => $gagal,
            'sisa'     => max(0, $sisa - $dipindah),
            'pesan'    => $dipindah . ' berkas dipindahkan ke Google Drive'
                . ($gagal ? ', ' . $gagal . ' gagal' : '')
                . ($sisa - $dipindah > 0 ? '. Masih ada ' . ($sisa - $dipindah) . ' berkas — jalankan lagi.' : '.'),
        ];
    }

    // -----------------------------------------------------------------

    public static function formatUkuran(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }
        return ($i === 0 ? (int) $n : number_format($n, 1, ',', '.')) . ' ' . $units[$i];
    }

    private function bersihkanNamaFile(string $nama): string
    {
        $nama = basename(str_replace('\\', '/', $nama));
        $nama = preg_replace('/[^\p{L}\p{N}\.\-_ ()]+/u', '_', $nama);
        $nama = preg_replace('/\s+/u', ' ', trim($nama));
        return $nama === '' ? 'dokumen' : mb_substr($nama, 0, 150, 'UTF-8');
    }

    private function deteksiMime(string $path, string $ext): string
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $m = finfo_file($fi, $path);
            finfo_close($fi);
            if ($m) {
                return $m;
            }
        }
        $peta = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        ];
        return $peta[$ext] ?? 'application/octet-stream';
    }

    private function uploadErrorText(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas server (upload_max_filesize).',
            UPLOAD_ERR_PARTIAL   => 'Berkas hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE   => 'Tidak ada berkas yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak tersedia di server.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis berkas ke disk.',
            default => 'Gagal mengunggah berkas (kode ' . $code . ').',
        };
    }

    private function slug(string $s): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s);
        return strtolower(trim(substr($s, 0, 60), '-')) ?: 'folder';
    }
}
