<?php

/**
 * Pengelolaan dokumen internal: penomoran otomatis, isi per blok,
 * revisi, status pengendalian, dan distribusi.
 */
class Dokumen
{
    // -----------------------------------------------------------------
    // Penomoran
    // -----------------------------------------------------------------

    /**
     * Nomor urut berikutnya. Nomor direset setiap tahun, dihitung terpisah
     * per jenis naskah — dan untuk jenis yang memakai penanda {bagian},
     * dihitung terpisah pula per bagian.
     */
    public static function urutBerikutnya(string $jenis, string $bagian, int $tahun): int
    {
        $def = Naskah::get($jenis);
        $perBagian = $def && str_contains($def['format'], '{bagian}');

        $sql = 'SELECT MAX(nomor_urut) FROM dokumen WHERE jenis = ? AND tahun = ?';
        $par = [$jenis, $tahun];
        if ($perBagian) {
            $sql .= ' AND bagian = ?';
            $par[] = $bagian;
        }
        return ((int) DB::val($sql, $par, 0)) + 1;
    }

    /** Menyusun nomor lengkap dari rumus pada master jenis. */
    public static function susunNomor(string $jenis, string $bagian, int $tahun, int $urut, ?int $bulan = null): string
    {
        $def = Naskah::get($jenis);
        if (!$def) {
            return '';
        }
        $rs = Settings::get('naskah_singkatan_rs', 'SSM');
        return strtr($def['format'], [
            '{urut}'   => (string) $urut,
            '{urut3}'  => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{bagian}' => $bagian,
            '{rs}'     => $rs,
            '{bulan}'  => Naskah::bulanRomawi($bulan ?? (int) date('n')),
            '{tahun}'  => (string) $tahun,
        ]);
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    public static function buat(string $jenis, string $bagian, string $judul, int $tahun): int
    {
        $def = Naskah::get($jenis);
        if (!$def) {
            throw new RuntimeException('Jenis naskah tidak dikenal.');
        }
        $urut  = self::urutBerikutnya($jenis, $bagian, $tahun);
        $nomor = self::susunNomor($jenis, $bagian, $tahun, $urut);

        $id = DB::insert('dokumen', [
            'jenis'  => $jenis,
            'bagian' => $bagian,
            'nomor'  => $nomor,
            'nomor_urut' => $urut,
            'tahun'  => $tahun,
            'judul'  => mb_substr(trim($judul), 0, 500, 'UTF-8'),
            'disiapkan_oleh' => $def['pengesahan']['disiapkan'] ?? '',
            'diperiksa_oleh' => $def['pengesahan']['diperiksa'] ?? '',
            'disahkan_oleh'  => Settings::get('naskah_nama_direktur', ''),
            'jabatan_pengesah' => Settings::get('naskah_jabatan_direktur', 'Direktur'),
            'tanggal_terbit' => date('Y-m-d'),
            'created_by' => Auth::username(),
        ]);

        foreach ($def['blok'] as $i => $b) {
            DB::insert('dokumen_isi', [
                'dokumen_id' => $id,
                'blok'   => $b['kode'],
                'judul'  => $b['judul'],
                'isi'    => '',
                'urutan' => $i + 1,
            ]);
        }
        self::catat($id, 'dibuat', 'Dokumen dibuat dengan nomor ' . $nomor);
        return $id;
    }

    public static function ambil(int $id): ?array
    {
        $d = DB::one('SELECT * FROM dokumen WHERE id = ?', [$id]);
        if (!$d) {
            return null;
        }
        $d['def'] = Naskah::get($d['jenis']);
        $d['isi'] = [];
        foreach (DB::all('SELECT * FROM dokumen_isi WHERE dokumen_id = ? ORDER BY urutan, id', [$id]) as $b) {
            $d['isi'][$b['blok']] = $b;
        }
        $d['berkas'] = self::berkas($id);
        return $d;
    }

    /** Hasil scan & lampiran yang tersimpan untuk sebuah dokumen. */
    public static function berkas(int $dokumenId): array
    {
        return DB::all('SELECT * FROM dokumen_berkas WHERE dokumen_id = ? ORDER BY kategori, id', [$dokumenId]);
    }

    public static function simpanIsi(int $dokumenId, string $blok, string $isi, string $judul = ''): void
    {
        $ada = DB::one('SELECT id FROM dokumen_isi WHERE dokumen_id = ? AND blok = ? ORDER BY urutan LIMIT 1',
            [$dokumenId, $blok]);
        if ($ada) {
            DB::q('UPDATE dokumen_isi SET isi = ?' . ($judul !== '' ? ', judul = ?' : '') . ' WHERE id = ?',
                $judul !== '' ? [$isi, $judul, $ada['id']] : [$isi, $ada['id']]);
        } else {
            $urut = (int) DB::val('SELECT COALESCE(MAX(urutan),0)+1 FROM dokumen_isi WHERE dokumen_id = ?', [$dokumenId]);
            DB::insert('dokumen_isi', [
                'dokumen_id' => $dokumenId, 'blok' => $blok,
                'judul' => $judul, 'isi' => $isi, 'urutan' => $urut,
            ]);
        }
        DB::q('UPDATE dokumen SET updated_at = NOW() WHERE id = ?', [$dokumenId]);
    }

    /** Kolom kepala dokumen yang boleh diubah langsung. */
    public const KOLOM_KEPALA = [
        'judul', 'ringkasan', 'bagian', 'nomor', 'tanggal_terbit', 'tanggal_berlaku',
        'tanggal_tinjau', 'disiapkan_oleh', 'diperiksa_oleh', 'disahkan_oleh',
        'jabatan_pengesah', 'dasar_dokumen', 'klasifikasi',
    ];

    public static function simpanKepala(int $id, string $kolom, string $nilai): bool
    {
        if (!in_array($kolom, self::KOLOM_KEPALA, true)) {
            return false;
        }
        if (str_starts_with($kolom, 'tanggal_')) {
            $nilai = trim($nilai);
            $nilai = $nilai === '' ? null : date('Y-m-d', strtotime($nilai) ?: time());
        } elseif ($kolom === 'klasifikasi') {
            if (!array_key_exists($nilai, Naskah::klasifikasiOptions())) {
                return false;
            }
        } else {
            $nilai = mb_substr(trim($nilai), 0, 500, 'UTF-8');
        }
        DB::q("UPDATE dokumen SET `$kolom` = ? WHERE id = ?", [$nilai, $id]);
        return true;
    }

    public static function ubahStatus(int $id, string $status, string $catatan = ''): bool
    {
        if (!array_key_exists($status, Naskah::statusOptions())) {
            return false;
        }
        $lama = DB::one('SELECT status, klasifikasi FROM dokumen WHERE id = ?', [$id]);
        if (!$lama) {
            return false;
        }
        $klas = $lama['klasifikasi'];
        if ($status === 'dicabut') {
            $klas = 'absolute';
        } elseif ($status === 'disahkan' && $klas === 'absolute') {
            $klas = 'master';
        }
        DB::q('UPDATE dokumen SET status = ?, klasifikasi = ? WHERE id = ?', [$status, $klas, $id]);
        self::catat($id, 'status', 'Status: ' . Naskah::statusLabel($lama['status'])
            . ' → ' . Naskah::statusLabel($status) . ($catatan !== '' ? ' — ' . $catatan : ''));
        return true;
    }

    /**
     * Membuat revisi: dokumen lama ditandai tidak berlaku, salinan baru dibuat
     * dengan nomor revisi bertambah dan isi yang sama sebagai titik awal.
     */
    public static function revisi(int $id, string $catatan = ''): ?int
    {
        $lama = self::ambil($id);
        if (!$lama) {
            return null;
        }
        $baru = DB::insert('dokumen', [
            'jenis' => $lama['jenis'], 'bagian' => $lama['bagian'],
            'nomor' => $lama['nomor'], 'nomor_urut' => $lama['nomor_urut'],
            'tahun' => $lama['tahun'], 'judul' => $lama['judul'],
            'ringkasan' => $lama['ringkasan'],
            'revisi' => (int) $lama['revisi'] + 1,
            'status' => 'draft', 'klasifikasi' => 'master',
            'tanggal_terbit' => date('Y-m-d'),
            'tanggal_berlaku' => null,
            'disiapkan_oleh' => $lama['disiapkan_oleh'],
            'diperiksa_oleh' => $lama['diperiksa_oleh'],
            'disahkan_oleh' => $lama['disahkan_oleh'],
            'jabatan_pengesah' => $lama['jabatan_pengesah'],
            'dasar_dokumen' => $lama['dasar_dokumen'],
            'induk_id' => $id,
            'created_by' => Auth::username(),
        ]);
        foreach ($lama['isi'] as $b) {
            DB::insert('dokumen_isi', [
                'dokumen_id' => $baru, 'blok' => $b['blok'],
                'judul' => $b['judul'], 'isi' => $b['isi'], 'urutan' => $b['urutan'],
            ]);
        }
        DB::q("UPDATE dokumen SET status = 'dicabut', klasifikasi = 'absolute', dicabut_oleh_id = ? WHERE id = ?",
            [$baru, $id]);
        self::catat($id, 'direvisi', 'Digantikan revisi ke-' . ((int) $lama['revisi'] + 1)
            . ($catatan !== '' ? ' — ' . $catatan : ''));
        self::catat($baru, 'dibuat', 'Revisi ke-' . ((int) $lama['revisi'] + 1) . ' dari dokumen sebelumnya');
        return $baru;
    }

    public static function hapus(int $id): void
    {
        DB::q('DELETE FROM dokumen WHERE id = ?', [$id]);
    }

    public static function catat(int $dokumenId, string $aksi, string $catatan = ''): void
    {
        try {
            DB::insert('dokumen_riwayat', [
                'dokumen_id' => $dokumenId, 'aksi' => $aksi,
                'catatan' => $catatan, 'oleh' => Auth::username(),
            ]);
        } catch (Throwable $e) {
            // pencatatan riwayat tidak boleh menggagalkan aksi utama
        }
    }

    public static function riwayat(int $dokumenId): array
    {
        return DB::all('SELECT * FROM dokumen_riwayat WHERE dokumen_id = ? ORDER BY id DESC', [$dokumenId]);
    }

    // -----------------------------------------------------------------
    // Daftar & rekap
    // -----------------------------------------------------------------

    public static function daftar(array $saring = []): array
    {
        $sql = 'SELECT d.*,
                       (SELECT COUNT(*) FROM dokumen_berkas f WHERE f.dokumen_id = d.id) AS jml_berkas
                  FROM dokumen d WHERE 1=1';
        $par = [];
        if (!empty($saring['jenis'])) {
            $sql .= ' AND d.jenis = ?';
            $par[] = $saring['jenis'];
        }
        if (!empty($saring['bagian'])) {
            $sql .= ' AND d.bagian = ?';
            $par[] = $saring['bagian'];
        }
        if (!empty($saring['status'])) {
            $sql .= ' AND d.status = ?';
            $par[] = $saring['status'];
        }
        if (!empty($saring['tahun'])) {
            $sql .= ' AND d.tahun = ?';
            $par[] = (int) $saring['tahun'];
        }
        if (!empty($saring['cari'])) {
            $sql .= ' AND (d.judul LIKE ? OR d.nomor LIKE ?)';
            $par[] = '%' . $saring['cari'] . '%';
            $par[] = '%' . $saring['cari'] . '%';
        }
        $sql .= ' ORDER BY d.tahun DESC, d.jenis, d.nomor_urut DESC, d.id DESC';
        return DB::all($sql, $par);
    }

    public static function ringkasan(): array
    {
        $perJenis = [];
        foreach (DB::all('SELECT jenis, COUNT(*) AS n FROM dokumen GROUP BY jenis') as $r) {
            $perJenis[$r['jenis']] = (int) $r['n'];
        }
        $perStatus = [];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM dokumen GROUP BY status') as $r) {
            $perStatus[$r['status']] = (int) $r['n'];
        }
        return [
            'total'      => (int) DB::val('SELECT COUNT(*) FROM dokumen', [], 0),
            'per_jenis'  => $perJenis,
            'per_status' => $perStatus,
            'berkas'     => (int) DB::val('SELECT COUNT(*) FROM dokumen_berkas', [], 0),
            'perlu_tinjau' => (int) DB::val(
                "SELECT COUNT(*) FROM dokumen WHERE status = 'disahkan'
                   AND tanggal_tinjau IS NOT NULL AND tanggal_tinjau <= CURDATE()", [], 0),
        ];
    }

    public static function tahunTersedia(): array
    {
        $t = DB::all('SELECT DISTINCT tahun FROM dokumen ORDER BY tahun DESC');
        $out = array_map(fn($r) => (int) $r['tahun'], $t);
        if (!in_array((int) date('Y'), $out, true)) {
            array_unshift($out, (int) date('Y'));
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // Distribusi
    // -----------------------------------------------------------------

    public static function distribusi(int $dokumenId): array
    {
        return DB::all('SELECT * FROM dokumen_distribusi WHERE dokumen_id = ? ORDER BY id', [$dokumenId]);
    }

    public static function tambahDistribusi(int $dokumenId, string $unit, string $salinanKe, string $penerima): int
    {
        return DB::insert('dokumen_distribusi', [
            'dokumen_id' => $dokumenId,
            'unit' => mb_substr(trim($unit), 0, 190, 'UTF-8'),
            'salinan_ke' => mb_substr(trim($salinanKe), 0, 40, 'UTF-8'),
            'penerima' => mb_substr(trim($penerima), 0, 190, 'UTF-8'),
            'tanggal' => date('Y-m-d'),
        ]);
    }
}
