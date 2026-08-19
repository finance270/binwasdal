<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$page = $_GET['p'] ?? 'beranda';

// ---------------------------------------------------------------------
// Pemasangan otomatis saat pertama kali dijalankan
// ---------------------------------------------------------------------
try {
    $perluPasang = Installer::needsInstall();
} catch (Throwable $e) {
    $perluPasang = true;
}

if ($perluPasang) {
    if ($page !== 'pasang') {
        redirect(url(['p' => 'pasang']));
    }
    $log = [];
    $galat = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $log = Installer::run($CFG);
        } catch (Throwable $e) {
            $galat = $e->getMessage();
        }
        if (!$galat) {
            redirect(url(['p' => 'login']));
        }
    }
    view('pasang', ['CFG' => $CFG, 'log' => $log, 'galat' => $galat]);
    exit;
}

// ---------------------------------------------------------------------
// Otentikasi
// ---------------------------------------------------------------------
if ($page === 'login') {
    $galat = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (Auth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect(url(['p' => 'beranda']));
        }
        $galat = 'Nama pengguna atau kata sandi salah.';
    }
    view('login', ['CFG' => $CFG, 'galat' => $galat]);
    exit;
}

if ($page === 'logout') {
    Auth::logout();
    redirect(url(['p' => 'login']));
}

Auth::require();

// Penyelarasan penomoran poin bila master dokumen diperbarui
// (mis. setelah mengunggah versi aplikasi yang lebih baru).
if (Settings::get('master_penomoran') !== Installer::PENOMORAN_VERSI) {
    try {
        $hasil = Installer::perbaruiPenomoran($CFG);
        if ($hasil['diperbarui'] > 0) {
            Log::write('sistem', 'penomoran', $hasil['pesan']);
        }
    } catch (Throwable $e) {
        // jangan sampai menggagalkan permintaan halaman
    }
}

// Pemasangan / penyelarasan tabel modul Dokumen Internal. Aman dijalankan
// berulang kali karena seluruh pernyataan memakai CREATE TABLE IF NOT EXISTS.
if (Settings::get('modul_dokumen') !== Installer::DOKUMEN_VERSI) {
    try {
        Installer::pasangModulDokumen($CFG);
        Log::write('sistem', 'modul_dokumen', 'Tabel modul Dokumen Internal disiapkan.');
    } catch (Throwable $e) {
        // jangan menggagalkan permintaan halaman; pesan muncul saat modul dibuka
    }
}

$assessment = Assessment::current();
$storage    = new Storage($CFG);

// ---------------------------------------------------------------------
// API (dipanggil lewat AJAX)
// ---------------------------------------------------------------------
if (str_starts_with($page, 'api_')) {
    if (!Auth::checkCsrf($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_out(['ok' => false, 'pesan' => 'Sesi kedaluwarsa, muat ulang halaman.'], 419);
    }
    // hanya api_detail yang boleh diakses akun "viewer"
    if ($page !== 'api_detail' && !Auth::canEdit()) {
        json_out(['ok' => false, 'pesan' => 'Akun Anda hanya dapat melihat.'], 403);
    }

    // Kegagalan tak terduga tetap dijawab dalam bentuk JSON, supaya pengguna
    // membaca sebabnya alih-alih hanya "HTTP 500".
    set_exception_handler(function (Throwable $e) use ($page) {
        Log::write(Auth::username(), 'galat', $page . ' — ' . $e->getMessage());
        json_out(['ok' => false, 'pesan' => 'Terjadi kesalahan di server: ' . $e->getMessage()], 500);
    });
    register_shutdown_function(function () use ($page) {
        $g = error_get_last();
        if ($g && in_array($g['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            Log::write(Auth::username(), 'galat', $page . ' — ' . $g['message']);
            if (!headers_sent()) {
                json_out(['ok' => false, 'pesan' => 'Terjadi kesalahan di server: ' . $g['message']], 500);
            }
        }
    });

    switch ($page) {
        // --- isi jendela popup sebuah poin --------------------------
        case 'api_detail':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            $detail = Assessment::detailPoin((int) $assessment['id'], $type, $key);
            if (!$detail) {
                json_out(['ok' => false, 'pesan' => 'Poin tidak ditemukan.'], 404);
            }
            $detail['ok'] = true;
            $detail['boleh_edit'] = Auth::canEdit();
            json_out($detail);

        // --- simpan jawaban poin checklist -------------------------
        case 'api_jawaban':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $ket    = (string) ($_POST['keterangan'] ?? '');
            if (!array_key_exists($status, statusOptions())) {
                json_out(['ok' => false, 'pesan' => 'Status tidak valid.'], 422);
            }
            if (!DB::val('SELECT 1 FROM items WHERE id = ?', [$itemId])) {
                json_out(['ok' => false, 'pesan' => 'Poin tidak ditemukan.'], 404);
            }
            DB::q(
                'INSERT INTO answers (assessment_id, item_id, status, keterangan, updated_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), updated_by = VALUES(updated_by)',
                [$assessment['id'], $itemId, $status, $ket, Auth::username()]
            );
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- simpan sel tabel profil --------------------------------
        case 'api_sel':
            $table = (string) ($_POST['table_code'] ?? '');
            $rowNo = (int) ($_POST['row_no'] ?? -1);
            $col   = (string) ($_POST['col_code'] ?? '');
            $val   = (string) ($_POST['v'] ?? '');
            $def   = Forms::get($table);
            if (!$def) {
                json_out(['ok' => false, 'pesan' => 'Tabel tidak dikenal.'], 404);
            }
            $bolehHeader = $rowNo === 0 && in_array($col, $def['header_editable'] ?? [], true);
            if (!$bolehHeader && (!in_array($col, $def['editable'], true) || $rowNo < 1)) {
                json_out(['ok' => false, 'pesan' => 'Kolom tidak dapat diubah.'], 422);
            }
            DB::q(
                'INSERT INTO form_values (assessment_id, table_code, row_no, col_code, v)
                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
                [$assessment['id'], $table, $rowNo, $col, $val]
            );
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- simpan data dasar --------------------------------------
        case 'api_profil':
            $k = (string) ($_POST['k'] ?? '');
            $v = (string) ($_POST['v'] ?? '');
            if (!DB::val('SELECT 1 FROM profil_values WHERE assessment_id = ? AND k = ?', [$assessment['id'], $k])) {
                json_out(['ok' => false, 'pesan' => 'Isian tidak dikenal.'], 404);
            }
            DB::q('UPDATE profil_values SET v = ? WHERE assessment_id = ? AND k = ?', [$v, $assessment['id'], $k]);
            if ($k === 'nama_rumah_sakit' && trim($v) !== '') {
                // kolom nama_rs dibatasi 190 karakter
                DB::q('UPDATE assessments SET nama_rs = ? WHERE id = ?', [
                    mb_substr(trim($v), 0, 190, 'UTF-8'), $assessment['id'],
                ]);
            }
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- buat / ambil folder Google Drive untuk sebuah poin -----
        case 'api_folder':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            $folder = $storage->pointFolder($assessment, $type, $key);
            if (!$folder) {
                json_out(['ok' => false, 'pesan' => $storage->lastError ?: 'Gagal membuat folder.'], 500);
            }
            json_out([
                'ok'     => true,
                'folder' => [
                    'nama' => $folder['nama'],
                    'link' => $folder['drive_link'],
                    'id'   => $folder['drive_id'],
                    'lokal' => $folder['drive_id'] ? false : true,
                ],
            ]);

        // --- unggah beberapa berkas sekaligus ----------------------
        case 'api_unggah':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            if (empty($_FILES['berkas'])) {
                json_out(['ok' => false, 'pesan' => 'Tidak ada berkas yang dikirim.'], 422);
            }
            $files = $_FILES['berkas'];
            $jml = is_array($files['name']) ? count($files['name']) : 0;
            $berhasil = [];
            $gagal = [];
            for ($i = 0; $i < $jml; $i++) {
                $satu = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size'     => $files['size'][$i],
                    'error'    => $files['error'][$i],
                ];
                $res = $storage->simpanBerkas($assessment, $type, $key, $satu);
                if ($res['ok']) {
                    $d = $res['dokumen'];
                    $berhasil[] = [
                        'id'     => (int) $d['id'],
                        'nama'   => $d['nama_file'],
                        'ukuran' => Storage::formatUkuran((int) $d['ukuran']),
                        'link'   => tautanBerkas($d),
                        'ikon'   => ikonBerkas($d['nama_file']),
                    ];
                } else {
                    $gagal[] = $satu['name'] . ': ' . $res['pesan'];
                }
            }
            $folder = DB::one(
                'SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
                [$assessment['id'], $type, $key]
            );
            json_out([
                'ok'      => count($berhasil) > 0,
                'berkas'  => $berhasil,
                'gagal'   => $gagal,
                'pesan'   => count($berhasil) . ' berkas tersimpan' . ($gagal ? ', ' . count($gagal) . ' gagal' : '') . '.',
                'folder'  => $folder ? [
                    'nama'  => $folder['nama'],
                    'link'  => $folder['drive_link'],
                    'lokal' => $folder['drive_id'] ? false : true,
                ] : null,
            ]);

        case 'api_hapus_berkas':
            $res = $storage->hapusBerkas((int) $assessment['id'], (int) ($_POST['id'] ?? 0));
            json_out($res, $res['ok'] ? 200 : 404);

        case 'api_tes_drive':
            if (!Auth::isAdmin()) {
                json_out(['ok' => false, 'pesan' => 'Hanya admin.'], 403);
            }
            json_out(GoogleDrive::fromSettings($CFG)->testConnection());

        // =============================================================
        //  Modul Dokumen Internal
        // =============================================================

        // --- simpan satu blok isi naskah ----------------------------
        case 'api_dok_isi':
            $dok = Dokumen::ambil((int) ($_POST['id'] ?? 0));
            if (!$dok || !$dok['def']) {
                json_out(['ok' => false, 'pesan' => 'Dokumen tidak ditemukan.'], 404);
            }
            if ($dok['status'] === 'dicabut') {
                json_out(['ok' => false, 'pesan' => 'Dokumen sudah tidak berlaku — buat revisi baru untuk mengubahnya.'], 422);
            }
            $blok = (string) ($_POST['blok'] ?? '');
            if (!in_array($blok, array_column($dok['def']['blok'], 'kode'), true)) {
                json_out(['ok' => false, 'pesan' => 'Blok isi tidak dikenal.'], 422);
            }
            Dokumen::simpanIsi((int) $dok['id'], $blok, (string) ($_POST['isi'] ?? ''));
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- simpan satu kolom kepala naskah -------------------------
        case 'api_dok_kepala':
            $dok = Dokumen::ambil((int) ($_POST['id'] ?? 0));
            if (!$dok || !$dok['def']) {
                json_out(['ok' => false, 'pesan' => 'Dokumen tidak ditemukan.'], 404);
            }
            if ($dok['status'] === 'dicabut') {
                json_out(['ok' => false, 'pesan' => 'Dokumen sudah tidak berlaku — buat revisi baru untuk mengubahnya.'], 422);
            }
            if (!Dokumen::simpanKepala((int) $dok['id'], (string) ($_POST['k'] ?? ''), (string) ($_POST['v'] ?? ''))) {
                json_out(['ok' => false, 'pesan' => 'Isian tidak dapat diubah.'], 422);
            }
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- unggah hasil scan / lampiran naskah ---------------------
        case 'api_dok_unggah':
            $dok = Dokumen::ambil((int) ($_POST['id'] ?? 0));
            if (!$dok || !$dok['def']) {
                json_out(['ok' => false, 'pesan' => 'Dokumen tidak ditemukan.'], 404);
            }
            if (empty($_FILES['berkas'])) {
                json_out(['ok' => false, 'pesan' => 'Tidak ada berkas yang dikirim.'], 422);
            }
            $kategori = ($_POST['kategori'] ?? 'scan') === 'lampiran' ? 'lampiran' : 'scan';
            $files = $_FILES['berkas'];
            $jml = is_array($files['name']) ? count($files['name']) : 0;
            $berhasil = [];
            $gagal = [];
            for ($i = 0; $i < $jml; $i++) {
                $res = $storage->simpanBerkasDokumen($dok, [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size'     => $files['size'][$i],
                    'error'    => $files['error'][$i],
                ], $kategori);
                if ($res['ok']) {
                    $b = $res['berkas'];
                    $berhasil[] = [
                        'id'       => (int) $b['id'],
                        'nama'     => $b['nama_file'],
                        'kategori' => $b['kategori'],
                        'ukuran'   => Storage::formatUkuran((int) $b['ukuran']),
                        'link'     => $b['drive_link'] ?: url(['p' => 'dokumen_berkas', 'id' => $b['id']]),
                        'ikon'     => ikonBerkas($b['nama_file']),
                    ];
                } else {
                    $gagal[] = $files['name'][$i] . ': ' . $res['pesan'];
                }
            }
            $folder = DB::one("SELECT * FROM dokumen_folder WHERE owner_type = 'dokumen' AND owner_key = ?", [(string) $dok['id']]);
            json_out([
                'ok'     => count($berhasil) > 0,
                'berkas' => $berhasil,
                'gagal'  => $gagal,
                'pesan'  => count($berhasil) . ' berkas tersimpan' . ($gagal ? ', ' . count($gagal) . ' gagal' : '') . '.',
                'folder' => $folder ? [
                    'nama'  => $folder['nama'],
                    'link'  => $folder['drive_link'],
                    'lokal' => $folder['drive_id'] ? false : true,
                ] : null,
            ]);

        case 'api_dok_hapus_berkas':
            $res = $storage->hapusBerkasDokumen((int) ($_POST['id'] ?? 0));
            json_out($res, $res['ok'] ? 200 : 404);

        default:
            json_out(['ok' => false, 'pesan' => 'Endpoint tidak dikenal.'], 404);
    }
}

// ---------------------------------------------------------------------
// Unduh dokumen Word (.docx)
// ---------------------------------------------------------------------
if ($page === 'unduh') {
    $bagian = (string) ($_GET['bagian'] ?? 'all');
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('Ekstensi PHP "zip" belum aktif di server, sehingga berkas Word tidak dapat dibuat.');
    }
    try {
        $hasil = EksporWord::buat($assessment, $bagian);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Gagal membuat dokumen Word: ' . e($e->getMessage()));
    }
    Log::write(Auth::username(), 'unduh_word', $hasil['nama']);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Length: ' . strlen($hasil['isi']));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $hasil['nama']) . '"; '
        . "filename*=UTF-8''" . rawurlencode($hasil['nama']));
    header('Cache-Control: no-store');
    echo $hasil['isi'];
    exit;
}

// ---------------------------------------------------------------------
// Unduh satu naskah internal sebagai Word (.docx)
// ---------------------------------------------------------------------
if ($page === 'dokumen_unduh') {
    $dok = Dokumen::ambil((int) ($_GET['id'] ?? 0));
    if (!$dok || !$dok['def']) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('Ekstensi PHP "zip" belum aktif di server, sehingga berkas Word tidak dapat dibuat.');
    }
    try {
        $hasil = EksporNaskah::buat($dok);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Gagal membuat dokumen Word: ' . e($e->getMessage()));
    }
    Log::write(Auth::username(), 'unduh_naskah', $dok['nomor']);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Length: ' . strlen($hasil['isi']));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $hasil['nama']) . '"; '
        . "filename*=UTF-8''" . rawurlencode($hasil['nama']));
    header('Cache-Control: no-store');
    echo $hasil['isi'];
    exit;
}

// ---------------------------------------------------------------------
// Unduh berkas lokal
// ---------------------------------------------------------------------
if ($page === 'berkas' || $page === 'dokumen_berkas') {
    $doc = $page === 'dokumen_berkas'
        ? DB::one('SELECT * FROM dokumen_berkas WHERE id = ?', [(int) ($_GET['id'] ?? 0)])
        : DB::one('SELECT * FROM documents WHERE id = ? AND assessment_id = ?', [(int) ($_GET['id'] ?? 0), $assessment['id']]);
    if (!$doc || !$doc['local_path']) {
        http_response_code(404);
        exit('Berkas tidak ditemukan.');
    }
    $full = rtrim($CFG['app']['upload_dir'], '/') . '/' . $doc['local_path'];
    if (!is_file($full)) {
        http_response_code(404);
        exit('Berkas tidak ada di penyimpanan.');
    }
    $mime = $doc['mime'] ?: 'application/octet-stream';
    $ext  = strtolower(pathinfo($doc['nama_file'], PATHINFO_EXTENSION));

    // Hanya jenis yang aman ditampilkan langsung di peramban. Sisanya diunduh,
    // agar berkas yang diunggah tidak bisa dieksekusi atas nama aplikasi.
    $bolehLangsung = in_array($ext, [
        'pdf', 'txt',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'mp4', 'm4v', 'mov', 'webm', 'ogg', 'ogv', '3gp',
        'mp3', 'm4a', 'wav', 'oga', 'aac',
    ], true);

    $namaAman = str_replace(['"', "\r", "\n"], '', $doc['nama_file']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($bolehLangsung ? $mime : 'application/octet-stream'));
    header('Content-Disposition: ' . ($bolehLangsung ? 'inline' : 'attachment')
        . '; filename="' . $namaAman . '"; '
        . "filename*=UTF-8''" . rawurlencode($doc['nama_file']));

    $ukuran = filesize($full);
    $awal = 0;
    $akhir = $ukuran - 1;

    // Dukungan permintaan sebagian, supaya video dapat diputar dan digeser
    // posisinya tanpa mengunduh seluruh berkas lebih dulu.
    $rentang = $_SERVER['HTTP_RANGE'] ?? '';
    if ($rentang !== '' && preg_match('/bytes=(\d*)-(\d*)/', $rentang, $m)) {
        if ($m[1] !== '') {
            $awal = (int) $m[1];
        }
        if ($m[2] !== '') {
            $akhir = (int) $m[2];
        }
        if ($awal > $akhir || $awal >= $ukuran) {
            http_response_code(416);
            header('Content-Range: bytes */' . $ukuran);
            exit;
        }
        $akhir = min($akhir, $ukuran - 1);
        http_response_code(206);
        header('Content-Range: bytes ' . $awal . '-' . $akhir . '/' . $ukuran);
    }
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($akhir - $awal + 1));

    $fh = fopen($full, 'rb');
    fseek($fh, $awal);
    $sisa = $akhir - $awal + 1;
    while ($sisa > 0 && !feof($fh)) {
        $potongan = fread($fh, (int) min(512 * 1024, $sisa));
        if ($potongan === false) {
            break;
        }
        echo $potongan;
        flush();
        $sisa -= strlen($potongan);
    }
    fclose($fh);
    exit;
}

// ---------------------------------------------------------------------
// Aksi POST biasa
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !str_starts_with($page, 'api_')) {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Silakan muat ulang halaman.');
    }
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'pilih_periode') {
        Assessment::setCurrent((int) $_POST['assessment_id']);
        redirect(url(['p' => $_POST['kembali'] ?? 'beranda']));
    }

    if ($aksi === 'buat_periode') {
        Auth::requireEdit();
        $nama = mb_substr(trim((string) $_POST['nama_rs']), 0, 190, 'UTF-8');
        $tahun = (int) $_POST['tahun'];
        if ($nama === '' || $tahun < 2000) {
            flash('Nama rumah sakit dan tahun wajib diisi.', 'galat');
            redirect(url(['p' => 'pengaturan']));
        }
        if (DB::val('SELECT 1 FROM assessments WHERE nama_rs = ? AND tahun = ?', [$nama, $tahun])) {
            flash('Periode untuk rumah sakit dan tahun tersebut sudah ada.', 'galat');
            redirect(url(['p' => 'pengaturan']));
        }
        $id = Assessment::create(
            $nama,
            $tahun,
            mb_substr(trim((string) ($_POST['wilayah'] ?? '')), 0, 190, 'UTF-8'),
            !empty($_POST['salin'])
        );
        Assessment::setCurrent($id);
        flash('Periode penilaian baru dibuat.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'simpan_drive') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        Settings::set('gdrive_auth_mode', (string) $_POST['auth_mode']);
        Settings::set('gdrive_root_folder_id', GoogleDrive::parseFolderId((string) $_POST['root_folder_id']));
        Settings::set('gdrive_client_id', trim((string) $_POST['client_id']));
        Settings::set('gdrive_client_secret', trim((string) $_POST['client_secret']));
        if (trim((string) $_POST['refresh_token']) !== '') {
            Settings::set('gdrive_refresh_token', trim((string) $_POST['refresh_token']));
        }
        if (trim((string) $_POST['sa_json']) !== '') {
            Settings::set('gdrive_sa_json', trim((string) $_POST['sa_json']));
        }
        Settings::forget('gdrive_token');
        Settings::forget('gdrive_token_exp');
        flash('Pengaturan Google Drive disimpan.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'tukar_kode_oauth') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $kode = trim((string) $_POST['kode']);
        $redirect = trim((string) $_POST['redirect_uri']);
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code'          => $kode,
                'client_id'     => Settings::get('gdrive_client_id', ''),
                'client_secret' => Settings::get('gdrive_client_secret', ''),
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $res = json_decode((string) curl_exec($ch), true) ?: [];
        curl_close($ch);
        if (!empty($res['refresh_token'])) {
            Settings::set('gdrive_refresh_token', $res['refresh_token']);
            Settings::set('gdrive_auth_mode', 'oauth');
            Settings::forget('gdrive_token');
            flash('Refresh token berhasil disimpan. Google Drive siap dipakai.');
        } else {
            flash('Gagal menukar kode: ' . json_encode($res, JSON_UNESCAPED_UNICODE)
                . ' — pastikan meminta akses dengan prompt=consent&access_type=offline.', 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'status_dokumen') {
        Auth::requireEdit();
        DB::q('UPDATE assessments SET status = ? WHERE id = ?', [$_POST['status'] === 'final' ? 'final' : 'draft', $assessment['id']]);
        flash('Status dokumen diperbarui.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'simpan_pengguna') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $username = trim((string) $_POST['username']);
        $pass = (string) $_POST['password'];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::q('UPDATE users SET nama = ?, role = ?, aktif = ? WHERE id = ?', [
                trim((string) $_POST['nama']), (string) $_POST['role'], isset($_POST['aktif']) ? 1 : 0, $id,
            ]);
            if ($pass !== '') {
                DB::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            flash('Pengguna diperbarui.');
        } else {
            if ($username === '' || $pass === '') {
                flash('Nama pengguna dan kata sandi wajib diisi.', 'galat');
                redirect(url(['p' => 'pengaturan']));
            }
            if (DB::val('SELECT 1 FROM users WHERE username = ?', [$username])) {
                flash('Nama pengguna sudah dipakai.', 'galat');
                redirect(url(['p' => 'pengaturan']));
            }
            DB::insert('users', [
                'username'      => $username,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'nama'          => trim((string) $_POST['nama']),
                'role'          => (string) $_POST['role'],
            ]);
            flash('Pengguna ditambahkan.');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'hapus_pengguna') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $id = (int) $_POST['id'];
        if ($id !== (Auth::user()['id'] ?? 0)) {
            DB::q('DELETE FROM users WHERE id = ?', [$id]);
            flash('Pengguna dihapus.');
        } else {
            flash('Tidak dapat menghapus akun yang sedang dipakai.', 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'muat_ulang_kode') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        if (function_exists('opcache_reset') && @opcache_reset()) {
            flash('Cache kode program dikosongkan. Muat ulang halaman untuk melihat versi terbaru.');
        } elseif (!function_exists('opcache_get_status') || !@opcache_get_status(false)['opcache_enabled']) {
            flash('OPcache tidak aktif — berkas terbaru sudah langsung dipakai.');
        } else {
            flash('Gagal mengosongkan OPcache (fungsi opcache_reset dinonaktifkan). '
                . 'Restart container aplikasi untuk memuat berkas terbaru.', 'galat');
        }
        Log::write(Auth::username(), 'muat_ulang_kode', 'Reset OPcache');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'sinkron_drive') {
        Auth::requireEdit();
        $res = $storage->sinkronKeDrive($assessment);
        flash($res['pesan'], $res['gagal'] > 0 ? 'galat' : 'sukses');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'siapkan_folder') {
        Auth::requireEdit();
        $f = $storage->assessmentFolder($assessment);
        if ($f) {
            flash('Folder utama siap: ' . $f['nama'] . ($f['drive_link'] ? '' : ' (penyimpanan lokal)'));
        } else {
            flash('Gagal menyiapkan folder: ' . $storage->lastError, 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    // ----------------------------------------------------------------
    // Modul Dokumen Internal
    // ----------------------------------------------------------------

    if ($aksi === 'simpan_identitas_naskah') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        foreach ([
            'naskah_nama_rs', 'naskah_nama_induk', 'naskah_singkatan_rs', 'naskah_alamat',
            'naskah_telepon', 'naskah_email', 'naskah_website', 'naskah_logo',
            'naskah_kota', 'naskah_nama_direktur', 'naskah_jabatan_direktur',
        ] as $k) {
            Settings::set($k, trim((string) ($_POST[$k] ?? '')));
        }
        flash('Identitas kop naskah disimpan.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'dokumen_buat') {
        Auth::requireEdit();
        $jenis  = (string) ($_POST['jenis'] ?? '');
        $bagian = (string) ($_POST['bagian'] ?? 'DIR');
        $judul  = trim((string) ($_POST['judul'] ?? ''));
        $tahun  = (int) ($_POST['tahun'] ?? date('Y'));
        if (!Naskah::get($jenis) || !array_key_exists($bagian, Naskah::bagian())) {
            flash('Jenis naskah atau bagian tidak dikenal.', 'galat');
            redirect(url(['p' => 'dokumen']));
        }
        if ($judul === '') {
            flash('Judul naskah wajib diisi.', 'galat');
            redirect(url(['p' => 'dokumen', 'jenis' => $jenis]));
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) date('Y');
        }
        $id = Dokumen::buat($jenis, $bagian, $judul, $tahun);
        Log::write(Auth::username(), 'naskah_baru', $jenis . ' — ' . $judul);
        flash('Naskah baru dibuat. Silakan lengkapi isinya.');
        redirect(url(['p' => 'dokumen_edit', 'id' => $id]));
    }

    if ($aksi === 'dokumen_status') {
        Auth::requireEdit();
        $id = (int) ($_POST['id'] ?? 0);
        if (Dokumen::ubahStatus($id, (string) ($_POST['status'] ?? ''), trim((string) ($_POST['catatan'] ?? '')))) {
            flash('Status dokumen diperbarui.');
        } else {
            flash('Status tidak dapat diubah.', 'galat');
        }
        redirect(url(['p' => 'dokumen_edit', 'id' => $id]));
    }

    if ($aksi === 'dokumen_revisi') {
        Auth::requireEdit();
        $baru = Dokumen::revisi((int) ($_POST['id'] ?? 0), trim((string) ($_POST['catatan'] ?? '')));
        if ($baru) {
            flash('Revisi baru dibuat. Naskah lama ditandai tidak berlaku (absolute).');
            redirect(url(['p' => 'dokumen_edit', 'id' => $baru]));
        }
        flash('Dokumen tidak ditemukan.', 'galat');
        redirect(url(['p' => 'dokumen']));
    }

    if ($aksi === 'dokumen_hapus') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        foreach (Dokumen::berkas($id) as $b) {
            $storage->hapusBerkasDokumen((int) $b['id']);
        }
        Dokumen::hapus($id);
        Log::write(Auth::username(), 'naskah_hapus', 'Dokumen #' . $id);
        flash('Dokumen dihapus.');
        redirect(url(['p' => 'dokumen']));
    }

    if ($aksi === 'dokumen_distribusi') {
        Auth::requireEdit();
        $id = (int) ($_POST['id'] ?? 0);
        $unit = trim((string) ($_POST['unit'] ?? ''));
        if ($unit !== '') {
            Dokumen::tambahDistribusi($id, $unit, trim((string) ($_POST['salinan_ke'] ?? '')), trim((string) ($_POST['penerima'] ?? '')));
            Dokumen::catat($id, 'distribusi', 'Salinan diserahkan ke ' . $unit);
            flash('Distribusi salinan dicatat.');
        }
        redirect(url(['p' => 'dokumen_edit', 'id' => $id]));
    }

    if ($aksi === 'dokumen_kerangka') {
        Auth::requireEdit();
        $id = (int) ($_POST['id'] ?? 0);
        $dok = Dokumen::ambil($id);
        $pilih = (string) ($_POST['kerangka'] ?? '');
        $daftar = $dok['def']['kerangka'][$pilih] ?? null;
        if ($dok && $daftar) {
            $lama = trim((string) ($dok['isi']['bab']['isi'] ?? ''));
            $isi = ($lama !== '' ? $lama . "\n" : '') . implode("\n", $daftar);
            Dokumen::simpanIsi($id, 'bab', $isi);
            flash('Kerangka baku "' . $pilih . '" ditambahkan.');
        } else {
            flash('Kerangka tidak dikenal untuk jenis naskah ini.', 'galat');
        }
        redirect(url(['p' => 'dokumen_edit', 'id' => $id]));
    }
}

// ---------------------------------------------------------------------
// Halaman
// ---------------------------------------------------------------------
$data = [
    'CFG'        => $CFG,
    'assessment' => $assessment,
    'storage'    => $storage,
    'page'       => $page,
    'profil'     => Assessment::profilMap((int) $assessment['id']),
];

switch ($page) {
    case 'beranda':
        $data['ringkasan'] = Assessment::ringkasan((int) $assessment['id']);
        view('beranda', $data);
        break;

    case 'profil':
        $data['fields'] = Assessment::profil((int) $assessment['id']);
        $data['tables'] = Forms::profilTables();
        view('profil', $data);
        break;

    case 'sdm':
        $data['tables'] = Forms::sdmTables();
        view('sdm', $data);
        break;

    case 'bagian':
        $sec = Assessment::section((int) ($_GET['id'] ?? 0));
        if (!$sec) {
            $sections = Assessment::sections();
            redirect(url(['p' => 'bagian', 'id' => $sections[0]['id'] ?? 0]));
        }
        $data['sec']  = $sec;
        $data['tree'] = Assessment::tree((int) $sec['id'], (int) $assessment['id']);
        view('bagian', $data);
        break;

    case 'cetak':
        $data['bagian'] = $_GET['bagian'] ?? 'all';
        view('cetak', $data);
        break;

    case 'tautan':
        $data['daftar'] = Assessment::daftarTautan((int) $assessment['id']);
        view('tautan', $data);
        break;

    case 'pengaturan':
        $data['periode']  = Assessment::listAll();
        $data['users']    = Auth::isAdmin() ? DB::all('SELECT * FROM users ORDER BY id') : [];
        $data['log']      = DB::all('SELECT * FROM activity_log ORDER BY id DESC LIMIT 30');
        $data['drive']    = GoogleDrive::fromSettings($CFG);
        view('pengaturan', $data);
        break;

    // ---------------------------------------------------------------
    // Modul Dokumen Internal
    // ---------------------------------------------------------------

    case 'dokumen':
        $data['saring'] = [
            'jenis'  => (string) ($_GET['jenis'] ?? ''),
            'bagian' => (string) ($_GET['bagian'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'tahun'  => (string) ($_GET['tahun'] ?? ''),
            'cari'   => trim((string) ($_GET['cari'] ?? '')),
        ];
        $data['daftar']    = Dokumen::daftar($data['saring']);
        $data['ringkasan'] = Dokumen::ringkasan();
        $data['tahun']     = Dokumen::tahunTersedia();
        view('dokumen', $data);
        break;

    case 'dokumen_edit':
        $dok = Dokumen::ambil((int) ($_GET['id'] ?? 0));
        if (!$dok || !$dok['def']) {
            http_response_code(404);
            view('galat', $data + ['pesan' => 'Dokumen internal tidak ditemukan.']);
            break;
        }
        $data['dok']        = $dok;
        $data['riwayat']    = Dokumen::riwayat((int) $dok['id']);
        $data['distribusi'] = Dokumen::distribusi((int) $dok['id']);
        $data['folder']     = DB::one(
            "SELECT * FROM dokumen_folder WHERE owner_type = 'dokumen' AND owner_key = ?",
            [(string) $dok['id']]
        );
        view('dokumen_edit', $data);
        break;

    case 'dokumen_cetak':
        $dok = Dokumen::ambil((int) ($_GET['id'] ?? 0));
        if (!$dok || !$dok['def']) {
            http_response_code(404);
            view('galat', $data + ['pesan' => 'Dokumen internal tidak ditemukan.']);
            break;
        }
        $data['dok'] = $dok;
        view('dokumen_cetak', $data);
        break;

    case 'dokumen_induk':
        $data['saring'] = ['tahun' => (string) ($_GET['tahun'] ?? '')];
        $data['daftar'] = Dokumen::daftar($data['saring']);
        $data['tahun']  = Dokumen::tahunTersedia();
        view('dokumen_induk', $data);
        break;

    case 'dokumen_tata':
        view('dokumen_tata', $data);
        break;

    default:
        http_response_code(404);
        view('galat', $data + ['pesan' => 'Halaman tidak ditemukan.']);
}
