<?php

/**
 * Merender dokumen internal menjadi naskah siap cetak.
 * Susunan mengikuti Pedoman Tata Naskah: kepala (kop + jenis + nomor +
 * TENTANG + judul), pembukaan (konsiderans), batang tubuh (diktum), dan
 * kaki (tempat, tanggal, jabatan, tanda tangan, nama).
 */
class RenderNaskah
{
    private const DIKTUM = [
        'KESATU', 'KEDUA', 'KETIGA', 'KEEMPAT', 'KELIMA', 'KEENAM', 'KETUJUH',
        'KEDELAPAN', 'KESEMBILAN', 'KESEPULUH', 'KESEBELAS', 'KEDUA BELAS',
        'KETIGA BELAS', 'KEEMPAT BELAS', 'KELIMA BELAS',
    ];

    /** Identitas rumah sakit untuk kop naskah. */
    public static function identitas(): array
    {
        return [
            'nama'     => Settings::get('naskah_nama_rs', 'RUMAH SAKIT KHUSUS THT SS MEDIKA'),
            'induk'    => Settings::get('naskah_nama_induk', ''),
            'alamat'   => Settings::get('naskah_alamat', 'Jl. Salemba Satu No. 11-13, Jakarta Pusat 10430'),
            'telepon'  => Settings::get('naskah_telepon', ''),
            'email'    => Settings::get('naskah_email', ''),
            'website'  => Settings::get('naskah_website', ''),
            'logo'     => Settings::get('naskah_logo', ''),
            'kota'     => Settings::get('naskah_kota', 'Jakarta'),
            'direktur' => Settings::get('naskah_nama_direktur', ''),
            'jabatan'  => Settings::get('naskah_jabatan_direktur', 'Direktur'),
            'singkatan' => Settings::get('naskah_singkatan_rs', 'SSM'),
        ];
    }

    // -----------------------------------------------------------------
    // Bagian umum
    // -----------------------------------------------------------------

    public static function kop(): string
    {
        $i = self::identitas();
        $h = '<div class="kop-naskah">';
        $h .= '<div class="kop-logo">';
        if ($i['logo'] !== '') {
            $h .= '<img src="' . e($i['logo']) . '" alt="Logo">';
        } else {
            $h .= '<span class="kop-logo-kosong">LOGO</span>';
        }
        $h .= '</div><div class="kop-teks">';
        if ($i['induk'] !== '') {
            $h .= '<div class="kop-induk">' . e($i['induk']) . '</div>';
        }
        $h .= '<div class="kop-nama">' . e($i['nama']) . '</div>';
        $baris = array_filter([
            $i['alamat'],
            trim(implode('  ·  ', array_filter([
                $i['telepon'] !== '' ? 'Telp. ' . $i['telepon'] : '',
                $i['email'],
                $i['website'],
            ]))),
        ]);
        foreach ($baris as $b) {
            $h .= '<div class="kop-alamat">' . e($b) . '</div>';
        }
        $h .= '</div></div><hr class="kop-garis">';
        return $h;
    }

    /** Blok tanda tangan di kaki naskah. */
    public static function kaki(array $d): string
    {
        $i = self::identitas();
        $tgl = $d['tanggal_terbit'] ? tanggalIndo($d['tanggal_terbit']) : '';
        $nama = trim((string) $d['disahkan_oleh']) !== '' ? $d['disahkan_oleh'] : $i['direktur'];
        $jab  = trim((string) $d['jabatan_pengesah']) !== '' ? $d['jabatan_pengesah'] : $i['jabatan'];

        $h = '<div class="kaki-naskah"><div class="kaki-kanan">';
        $h .= '<div>Ditetapkan di ' . e($i['kota']) . '</div>';
        $h .= '<div>Pada tanggal ' . e($tgl) . '</div>';
        $h .= '<div class="kaki-jabatan">' . e(mb_strtoupper($jab . ' ' . $i['nama'], 'UTF-8')) . ',</div>';
        $h .= '<div class="kaki-ruang"></div>';
        $h .= '<div class="kaki-nama">' . e($nama !== '' ? $nama : '(………………………………)') . '</div>';
        $h .= '</div></div>';
        return $h;
    }

    // -----------------------------------------------------------------
    // Pengubah isi menjadi HTML
    // -----------------------------------------------------------------

    /** @return string[] baris tidak kosong */
    private static function baris(?string $isi): array
    {
        $b = preg_split('/\r\n|\r|\n/', (string) $isi) ?: [];
        return array_values(array_filter(array_map('trim', $b), fn($x) => $x !== ''));
    }

    /** Daftar bernomor angka atau huruf. */
    public static function daftar(?string $isi, string $gaya = 'angka'): string
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return '<p class="kosong">…</p>';
        }
        $kelas = $gaya === 'huruf' ? 'daftar-huruf' : ($gaya === 'polos' ? 'daftar-polos' : 'daftar-angka');
        $h = '<ol class="' . $kelas . '">';
        foreach ($baris as $b) {
            $h .= '<li>' . enl($b) . '</li>';
        }
        return $h . '</ol>';
    }

    /** Paragraf biasa. */
    public static function teks(?string $isi): string
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return '<p class="kosong">…</p>';
        }
        $h = '';
        foreach ($baris as $b) {
            $h .= '<p>' . e($b) . '</p>';
        }
        return $h;
    }

    /**
     * Diktum: tiap baris menjadi satu diktum berlabel KESATU, KEDUA, dan
     * seterusnya. Label dapat ditulis sendiri dengan format "LABEL : isi".
     */
    public static function diktum(?string $isi): string
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return '<p class="kosong">…</p>';
        }
        $h = '<table class="diktum">';
        $n = 0;
        foreach ($baris as $b) {
            if (preg_match('/^([A-Z][A-Z ]{2,20})\s*:\s*(.+)$/u', $b, $m)) {
                $label = trim($m[1]);
                $teks  = trim($m[2]);
            } else {
                $label = self::DIKTUM[$n] ?? ('KE-' . ($n + 1));
                $teks  = $b;
            }
            $n++;
            $h .= '<tr><td class="d-label">' . e($label) . '</td><td class="d-titik">:</td>'
                . '<td class="d-isi">' . e($teks) . '</td></tr>';
        }
        return $h . '</table>';
    }

    /** Tabel: baris pertama menjadi judul kolom, kolom dipisah tanda |. */
    public static function tabel(?string $isi): string
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return '<p class="kosong">…</p>';
        }
        $h = '<table class="w naskah-tabel">';
        foreach ($baris as $i => $b) {
            $sel = array_map('trim', explode('|', $b));
            $tag = $i === 0 ? 'th' : 'td';
            $h .= '<tr>';
            foreach ($sel as $s) {
                $h .= "<$tag>" . e($s) . "</$tag>";
            }
            $h .= '</tr>';
        }
        return $h . '</table>';
    }

    /** Bab: baris berawalan "BAB " menjadi judul, sisanya paragraf. */
    public static function bab(?string $isi): string
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return '<p class="kosong">…</p>';
        }
        $h = '';
        foreach ($baris as $b) {
            if (preg_match('/^(BAB\s+[IVXLC]+|[A-Z]\.\s|\d+\.\s)/u', $b) && mb_strlen($b, 'UTF-8') < 90) {
                $h .= '<div class="naskah-bab">' . e($b) . '</div>';
            } else {
                $h .= '<p>' . e($b) . '</p>';
            }
        }
        return $h;
    }

    private static function blokHtml(array $blokDef, ?string $isi): string
    {
        return match ($blokDef['tipe'] ?? 'teks') {
            'daftar'  => self::daftar($isi),
            'diktum'  => self::diktum($isi),
            'tabel'   => self::tabel($isi),
            'bab'     => self::bab($isi),
            default   => self::teks($isi),
        };
    }

    // -----------------------------------------------------------------
    // Naskah utuh
    // -----------------------------------------------------------------

    public static function naskah(array $d): string
    {
        $isi = match ($d['def']['arketipe'] ?? 'surat') {
            'arahan'   => self::arahan($d),
            'spo'      => self::spo($d),
            'pedoman'  => self::pedoman($d),
            'formulir' => self::formulir($d),
            default    => self::surat($d),
        };
        return self::cap($d) . $isi;
    }

    /**
     * Cap pengendalian dokumen (Bab IX pedoman): naskah yang belum disahkan
     * atau sudah dicabut tidak boleh dipakai sebagai acuan kerja, sehingga
     * keadaannya ikut tercetak pada lembarnya.
     */
    private static function cap(array $d): string
    {
        $teks = match (true) {
            $d['status'] === 'dicabut'         => 'TIDAK BERLAKU',
            $d['status'] === 'draft'           => 'DRAF',
            $d['status'] === 'diperiksa'       => 'BELUM DISAHKAN',
            $d['klasifikasi'] === 'tak_terkendali' => 'TIDAK TERKENDALI',
            default => '',
        };
        return $teks === '' ? '' : '<div class="cap-naskah">' . e($teks) . '</div>';
    }

    private static function isi(array $d, string $blok): ?string
    {
        return $d['isi'][$blok]['isi'] ?? null;
    }

    private static function judulBlok(array $d, string $blok, string $bawaan): string
    {
        $j = trim((string) ($d['isi'][$blok]['judul'] ?? ''));
        return $j !== '' ? $j : $bawaan;
    }

    // --- naskah pengaturan / penetapan --------------------------------
    private static function arahan(array $d): string
    {
        $i = self::identitas();
        $def = $d['def'];
        $h = self::kop();

        $h .= '<div class="naskah-kepala">';
        $h .= '<div class="n-jenis">' . e(mb_strtoupper($def['nama'], 'UTF-8')) . '<br>'
            . e(mb_strtoupper($i['nama'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-nomor">NOMOR ' . e($d['nomor']) . '</div>';
        $h .= '<div class="n-tentang">TENTANG</div>';
        $h .= '<div class="n-judul">' . e(mb_strtoupper($d['judul'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-jabatan">' . e(mb_strtoupper($d['jabatan_pengesah'] . ' ' . $i['nama'], 'UTF-8')) . ',</div>';
        $h .= '</div>';

        foreach ([['menimbang', 'Menimbang'], ['mengingat', 'Mengingat']] as [$kode, $bawaan]) {
            if (!isset($d['isi'][$kode])) {
                continue;
            }
            $h .= '<table class="konsiderans"><tr>'
                . '<td class="k-label">' . e(self::judulBlok($d, $kode, $bawaan)) . '</td>'
                . '<td class="k-titik">:</td><td class="k-isi">'
                . self::daftar(self::isi($d, $kode), $kode === 'menimbang' ? 'huruf' : 'angka')
                . '</td></tr></table>';
        }

        $h .= '<div class="n-memutuskan">MEMUTUSKAN:</div>';
        $tetap = trim((string) self::isi($d, 'menetapkan'));
        $h .= '<table class="konsiderans"><tr><td class="k-label">Menetapkan</td><td class="k-titik">:</td>'
            . '<td class="k-isi"><p class="tebal">'
            . e($tetap !== '' ? mb_strtoupper($tetap, 'UTF-8') : mb_strtoupper($def['nama'] . ' TENTANG ' . $d['judul'], 'UTF-8'))
            . '</p></td></tr></table>';

        $h .= '<div class="n-batang">' . self::diktum(self::isi($d, 'diktum')) . '</div>';

        $penutup = trim((string) self::isi($d, 'penutup'));
        if ($penutup !== '') {
            $h .= '<div class="n-penutup">' . self::teks($penutup) . '</div>';
        }
        return $h . self::kaki($d);
    }

    // --- standar prosedur operasional ---------------------------------
    private static function spo(array $d): string
    {
        $i = self::identitas();
        $def = $d['def'];

        $h = '<div class="spo-gulir"><table class="spo-kepala">';
        $h .= '<tr>';
        $h .= '<td class="spo-logo" rowspan="2">';
        if ($i['logo'] !== '') {
            $h .= '<img src="' . e($i['logo']) . '" alt="Logo">';
        }
        $h .= '<div class="spo-nama-rs">' . e($i['nama']) . '</div>'
            . '<div class="spo-alamat">' . e($i['alamat']) . '</div></td>';
        $h .= '<td class="spo-judul" colspan="3">' . e(mb_strtoupper($d['judul'], 'UTF-8')) . '</td>';
        $h .= '</tr><tr>';
        $h .= '<td class="spo-sel"><span>No. Dokumen</span><b>' . e($d['nomor']) . '</b></td>';
        $h .= '<td class="spo-sel"><span>No. Revisi</span><b>' . (int) $d['revisi'] . '</b></td>';
        $h .= '<td class="spo-sel"><span>Halaman</span><b>1/1</b></td>';
        $h .= '</tr><tr>';
        $h .= '<td class="spo-jenis">' . e(mb_strtoupper($def['nama'], 'UTF-8')) . '</td>';
        $h .= '<td class="spo-sel" colspan="1"><span>Tanggal Terbit</span><b>'
            . e($d['tanggal_terbit'] ? tanggalIndo($d['tanggal_terbit']) : '—') . '</b></td>';
        $h .= '<td class="spo-ttd" colspan="2"><span>Ditetapkan oleh</span>'
            . '<div class="spo-ruang"></div>'
            . '<b>' . e(trim((string) $d['disahkan_oleh']) !== '' ? $d['disahkan_oleh'] : $i['direktur']) . '</b>'
            . '<span>' . e($d['jabatan_pengesah']) . '</span></td>';
        $h .= '</tr></table></div>';

        $h .= '<table class="spo-badan">';
        foreach ($def['blok'] as $b) {
            $h .= '<tr><td class="spo-b-label">' . e(self::judulBlok($d, $b['kode'], $b['judul'])) . '</td>'
                . '<td class="spo-b-isi">' . self::blokHtml($b, self::isi($d, $b['kode'])) . '</td></tr>';
        }
        return $h . '</table>';
    }

    // --- pedoman / panduan --------------------------------------------
    private static function pedoman(array $d): string
    {
        $i = self::identitas();
        $h = self::kop();
        $h .= '<div class="naskah-kepala">';
        $h .= '<div class="n-jenis">' . e(mb_strtoupper($d['def']['nama'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-judul">' . e(mb_strtoupper($d['judul'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-nomor">Nomor: ' . e($d['nomor']) . ' &nbsp;·&nbsp; Revisi: ' . (int) $d['revisi'] . '</div>';
        $h .= '</div>';
        if (trim((string) $d['dasar_dokumen']) !== '') {
            $h .= '<p class="n-dasar">Ditetapkan dengan: ' . e($d['dasar_dokumen']) . '</p>';
        }
        foreach ($d['def']['blok'] as $b) {
            $h .= self::blokHtml($b, self::isi($d, $b['kode']));
        }
        return $h . self::kaki($d);
    }

    // --- formulir ------------------------------------------------------
    private static function formulir(array $d): string
    {
        $h = self::kop();
        $h .= '<div class="naskah-kepala"><div class="n-judul">' . e(mb_strtoupper($d['judul'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-nomor-kecil">' . e($d['nomor']) . ' &nbsp;·&nbsp; Revisi ' . (int) $d['revisi'] . '</div></div>';

        $pengantar = trim((string) self::isi($d, 'pengantar'));
        if ($pengantar !== '') {
            $h .= self::teks($pengantar);
        }
        $isian = self::baris(self::isi($d, 'isian'));
        if ($isian) {
            $h .= '<table class="form-isian">';
            foreach ($isian as $b) {
                $h .= '<tr><td class="fi-label">' . e($b) . '</td><td class="fi-titik">:</td>'
                    . '<td class="fi-garis"></td></tr>';
            }
            $h .= '</table>';
        }
        $h .= self::tabel(self::isi($d, 'tabel'));
        $catatan = trim((string) self::isi($d, 'catatan'));
        if ($catatan !== '') {
            $h .= '<p class="form-catatan">' . enl($catatan) . '</p>';
        }
        return $h . self::kaki($d);
    }

    // --- surat ----------------------------------------------------------
    private static function surat(array $d): string
    {
        $i = self::identitas();
        $def = $d['def'];
        $h = self::kop();

        $h .= '<div class="naskah-kepala">';
        $h .= '<div class="n-judul-surat">' . e(mb_strtoupper($def['nama'], 'UTF-8')) . '</div>';
        $h .= '<div class="n-nomor-kecil">Nomor: ' . e($d['nomor']) . '</div>';
        if (trim((string) $d['judul']) !== '') {
            $h .= '<div class="n-perihal-judul">' . e($d['judul']) . '</div>';
        }
        $h .= '</div>';

        foreach ($def['blok'] as $b) {
            $isi = trim((string) self::isi($d, $b['kode']));
            if ($isi === '') {
                continue;
            }
            $judul = self::judulBlok($d, $b['kode'], $b['judul']);
            // blok pendek ditampilkan sebagai "Label : isi"
            if (in_array($b['kode'], ['kepada', 'dari', 'perihal', 'lampiran_ket'], true) && $b['tipe'] === 'teks') {
                $h .= '<table class="surat-baris"><tr><td class="s-label">' . e($judul) . '</td>'
                    . '<td class="s-titik">:</td><td class="s-isi">' . enl($isi) . '</td></tr></table>';
                continue;
            }
            if (in_array($b['kode'], ['pembuka', 'isi', 'penutup'], true)) {
                $h .= self::blokHtml($b, $isi);
                continue;
            }
            $h .= '<table class="surat-baris"><tr><td class="s-label">' . e($judul) . '</td>'
                . '<td class="s-titik">:</td><td class="s-isi">' . self::blokHtml($b, $isi) . '</td></tr></table>';
        }
        return $h . self::kaki($d);
    }
}
