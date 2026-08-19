<?php

/**
 * Mengubah satu dokumen internal menjadi berkas Word (.docx).
 *
 * Tata letaknya sengaja dibuat sama persis dengan tampilan cetak di layar
 * (lihat RenderNaskah), sehingga berkas yang diunduh dapat langsung diedit
 * di Microsoft Word tanpa perlu menata ulang.
 */
class EksporNaskah
{
    private const DIKTUM = [
        'KESATU', 'KEDUA', 'KETIGA', 'KEEMPAT', 'KELIMA', 'KEENAM', 'KETUJUH',
        'KEDELAPAN', 'KESEMBILAN', 'KESEPULUH', 'KESEBELAS', 'KEDUA BELAS',
        'KETIGA BELAS', 'KEEMPAT BELAS', 'KELIMA BELAS',
    ];

    /** @return array{nama:string,isi:string} */
    public static function buat(array $d): array
    {
        $def = $d['def'] ?? Naskah::get($d['jenis']);
        if (!$def) {
            throw new RuntimeException('Jenis naskah tidak dikenal.');
        }
        $d['def'] = $def;

        $w = new DocxWriter();
        // Huruf mengikuti ketentuan jenis naskah: Bookman Old Style 12 untuk
        // naskah pengaturan/penetapan, Arial 12 untuk naskah lainnya.
        $w->hurufBawaan  = $def['huruf'] ?? 'Arial';
        $w->ukuranBawaan = 12;

        switch ($def['arketipe']) {
            case 'arahan':
                self::arahan($w, $d);
                break;
            case 'spo':
                self::spo($w, $d);
                break;
            case 'pedoman':
                self::pedoman($w, $d);
                break;
            case 'formulir':
                self::formulir($w, $d);
                break;
            default:
                self::surat($w, $d);
        }

        return ['nama' => self::namaBerkas($d), 'isi' => $w->keluarkan()];
    }

    public static function namaBerkas(array $d): string
    {
        $nomor = str_replace(['/', '\\', ':', '"'], '-', trim((string) $d['nomor']));
        $judul = preg_replace('/[^\p{L}\p{N} \-_]+/u', '', (string) $d['judul']);
        $judul = trim(preg_replace('/\s+/u', ' ', (string) $judul));
        return mb_substr(trim($nomor . ' - ' . $judul), 0, 120, 'UTF-8') . '.docx';
    }

    // -----------------------------------------------------------------
    // Bagian umum
    // -----------------------------------------------------------------

    private static function kop(DocxWriter $w): void
    {
        $i = RenderNaskah::identitas();
        if (trim($i['induk']) !== '') {
            $w->paragraf($i['induk'], ['rata' => 'center', 'ukuran' => 11, 'spasiSesudah' => 0]);
        }
        $w->paragraf(mb_strtoupper($i['nama'], 'UTF-8'), [
            'rata' => 'center', 'tebal' => true, 'ukuran' => 14, 'spasiSesudah' => 0,
        ]);
        if (trim($i['alamat']) !== '') {
            $w->paragraf($i['alamat'], ['rata' => 'center', 'ukuran' => 9, 'spasiSesudah' => 0]);
        }
        $kontak = implode('  ·  ', array_filter([
            trim($i['telepon']) !== '' ? 'Telp. ' . $i['telepon'] : '',
            $i['email'],
            $i['website'],
        ]));
        if ($kontak !== '') {
            $w->paragraf($kontak, ['rata' => 'center', 'ukuran' => 9, 'spasiSesudah' => 0]);
        }
        $w->garis();
    }

    private static function kaki(DocxWriter $w, array $d): void
    {
        $i = RenderNaskah::identitas();
        $nama = trim((string) $d['disahkan_oleh']) !== '' ? (string) $d['disahkan_oleh'] : $i['direktur'];
        $jab  = trim((string) $d['jabatan_pengesah']) !== '' ? (string) $d['jabatan_pengesah'] : $i['jabatan'];
        $tgl  = $d['tanggal_terbit'] ? tanggalIndo($d['tanggal_terbit']) : '';

        $w->kosong();
        // Blok tanda tangan diletakkan di sisi kanan namun rata kiri di dalamnya,
        // memakai tabel tanpa garis agar barisnya tidak bergerigi.
        $kiri = (int) round(DocxWriter::LEBAR_ISI * 0.32);
        $baris = [
            ['teks' => 'Ditetapkan di ' . $i['kota'], 'ukuran' => 12],
            ['teks' => 'Pada tanggal ' . $tgl, 'ukuran' => 12],
            ['teks' => mb_strtoupper($jab . ' ' . $i['nama'], 'UTF-8') . ',', 'ukuran' => 12],
        ];
        // ruang tanda tangan — spasi keras agar barisnya tidak dihilangkan Word
        for ($n = 0; $n < 3; $n++) {
            $baris[] = ['teks' => self::SPASI, 'ukuran' => 12];
        }
        $baris[] = ['teks' => $nama !== '' ? $nama : '(………………………………)',
                    'ukuran' => 12, 'tebal' => true, 'garisBawah' => true];

        $w->tabel([$kiri, DocxWriter::LEBAR_ISI - $kiri], [[
            ['teks' => self::SPASI],
            ['baris' => $baris],
        ]], false, false, true);
    }

    /** Spasi keras: dipakai sebagai baris kosong yang tetap tercetak. */
    private const SPASI = "\u{00A0}";

    /** @return string[] */
    private static function baris(?string $isi): array
    {
        $b = preg_split('/\r\n|\r|\n/', (string) $isi) ?: [];
        return array_values(array_filter(array_map('trim', $b), fn($x) => $x !== ''));
    }

    private static function isi(array $d, string $blok): string
    {
        return (string) ($d['isi'][$blok]['isi'] ?? '');
    }

    private static function judulBlok(array $d, string $blok, string $bawaan): string
    {
        $j = trim((string) ($d['isi'][$blok]['judul'] ?? ''));
        return $j !== '' ? $j : $bawaan;
    }

    private static function hurufKe(int $n): string
    {
        $s = '';
        $n++;
        while ($n > 0) {
            $n--;
            $s = chr(97 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }

    /** Daftar bernomor sebagai paragraf menjorok. */
    private static function daftar(DocxWriter $w, ?string $isi, string $gaya = 'angka', int $indent = 0): void
    {
        $baris = self::baris($isi);
        foreach ($baris as $i => $b) {
            $no = match ($gaya) {
                'huruf' => self::hurufKe($i) . '.',
                'polos' => '',
                default => ($i + 1) . '.',
            };
            $w->paragraf(trim($no . ' ' . $b), [
                'rata' => 'both', 'spasiSesudah' => 60,
                'indentKiri' => $indent + 360, 'gantung' => 360,
            ]);
        }
    }

    private static function teks(DocxWriter $w, ?string $isi, int $indent = 0): void
    {
        foreach (self::baris($isi) as $b) {
            $w->paragraf($b, ['rata' => 'both', 'indentKiri' => $indent, 'spasiSesudah' => 60]);
        }
    }

    /** Tabel dari teks: baris pertama judul kolom, kolom dipisah tanda |. */
    private static function tabelTeks(DocxWriter $w, ?string $isi): void
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return;
        }
        $matriks = array_map(fn($b) => array_map('trim', explode('|', $b)), $baris);
        $jml = max(array_map('count', $matriks));
        $lebar = array_fill(0, $jml, (int) floor(DocxWriter::LEBAR_ISI / $jml));

        $rows = [];
        foreach ($matriks as $i => $sel) {
            $sel = array_pad($sel, $jml, '');
            $rows[] = array_map(fn($s) => [
                'teks' => $s, 'ukuran' => 10,
                'tebal' => $i === 0, 'arsir' => $i === 0 ? 'DFE6EE' : null,
                'rata' => $i === 0 ? 'center' : 'left',
            ], $sel);
        }
        $w->tabel($lebar, $rows);
    }

    private static function blok(DocxWriter $w, array $blokDef, string $isi): void
    {
        switch ($blokDef['tipe'] ?? 'teks') {
            case 'daftar':
                self::daftar($w, $isi);
                break;
            case 'diktum':
                self::diktum($w, $isi);
                break;
            case 'tabel':
                self::tabelTeks($w, $isi);
                break;
            case 'bab':
                self::bab($w, $isi);
                break;
            default:
                self::teks($w, $isi);
        }
    }

    // -----------------------------------------------------------------
    // Naskah pengaturan / penetapan
    // -----------------------------------------------------------------

    private static function arahan(DocxWriter $w, array $d): void
    {
        $i = RenderNaskah::identitas();
        $def = $d['def'];
        self::kop($w);

        $tengah = ['rata' => 'center', 'tebal' => true, 'spasiSesudah' => 0];
        $w->paragraf(mb_strtoupper($def['nama'], 'UTF-8'), $tengah);
        $w->paragraf(mb_strtoupper($i['nama'], 'UTF-8'), $tengah);
        $w->paragraf('NOMOR ' . $d['nomor'], ['rata' => 'center', 'spasiSesudah' => 160]);
        $w->paragraf('TENTANG', ['rata' => 'center', 'spasiSesudah' => 40]);
        $w->paragraf(mb_strtoupper((string) $d['judul'], 'UTF-8'), ['rata' => 'center', 'tebal' => true, 'spasiSesudah' => 200]);
        $w->paragraf(mb_strtoupper($d['jabatan_pengesah'] . ' ' . $i['nama'], 'UTF-8') . ',', [
            'rata' => 'center', 'tebal' => true, 'spasiSesudah' => 200,
        ]);

        // konsiderans — label sejajar, isi menjorok
        $lebar = [1700, 300, DocxWriter::LEBAR_ISI - 2000];
        foreach ([['menimbang', 'Menimbang', 'huruf'], ['mengingat', 'Mengingat', 'angka']] as [$kode, $bawaan, $gaya]) {
            if (!isset($d['isi'][$kode])) {
                continue;
            }
            $isi = self::baris(self::isi($d, $kode));
            if (!$isi) {
                continue;
            }
            $paragraf = [];
            foreach ($isi as $n => $b) {
                $paragraf[] = [
                    'teks' => ($gaya === 'huruf' ? self::hurufKe($n) . '.' : ($n + 1) . '.') . ' ' . $b,
                    'ukuran' => 12, 'rata' => 'both', 'spasiSesudah' => 60,
                    'indentKiri' => 360, 'gantung' => 360,
                ];
            }
            $w->tabel($lebar, [[
                ['teks' => self::judulBlok($d, $kode, $bawaan), 'ukuran' => 12],
                ['teks' => ':', 'ukuran' => 12],
                ['baris' => $paragraf, 'ukuran' => 12],
            ]], false, false);
        }

        $w->paragraf('MEMUTUSKAN:', ['rata' => 'center', 'tebal' => true, 'spasiSebelum' => 120, 'spasiSesudah' => 120]);

        $tetap = trim(self::isi($d, 'menetapkan'));
        if ($tetap === '') {
            $tetap = $def['nama'] . ' TENTANG ' . $d['judul'];
        }
        $w->tabel($lebar, [[
            ['teks' => 'Menetapkan', 'ukuran' => 12],
            ['teks' => ':', 'ukuran' => 12],
            ['teks' => mb_strtoupper($tetap, 'UTF-8'), 'ukuran' => 12, 'tebal' => true, 'rata' => 'both'],
        ]], false, false);

        self::diktum($w, self::isi($d, 'diktum'));

        $penutup = trim(self::isi($d, 'penutup'));
        if ($penutup !== '') {
            self::teks($w, $penutup);
        }
        self::kaki($w, $d);
    }

    private static function diktum(DocxWriter $w, ?string $isi): void
    {
        $baris = self::baris($isi);
        if (!$baris) {
            return;
        }
        $lebar = [1700, 300, DocxWriter::LEBAR_ISI - 2000];
        $rows = [];
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
            $rows[] = [
                ['teks' => $label, 'ukuran' => 12, 'tebal' => true, 'spasiSesudah' => 80],
                ['teks' => ':', 'ukuran' => 12, 'spasiSesudah' => 80],
                ['teks' => $teks, 'ukuran' => 12, 'rata' => 'both', 'spasiSesudah' => 80],
            ];
        }
        $w->tabel($lebar, $rows, false, false);
    }

    // -----------------------------------------------------------------
    // Standar Prosedur Operasional / Instruksi Kerja
    // -----------------------------------------------------------------

    private static function spo(DocxWriter $w, array $d): void
    {
        $i = RenderNaskah::identitas();
        $def = $d['def'];
        $lebar = [2400, 2413, 2413, 2412];

        $nama = trim((string) $d['disahkan_oleh']) !== '' ? (string) $d['disahkan_oleh'] : $i['direktur'];

        $w->tabel($lebar, [
            [
                ['gabung' => 'mulai', 'vRata' => 'center', 'baris' => [
                    ['teks' => $i['nama'], 'tebal' => true, 'ukuran' => 10, 'rata' => 'center'],
                    ['teks' => $i['alamat'], 'ukuran' => 8, 'rata' => 'center'],
                ]],
                ['kolom' => 3, 'teks' => mb_strtoupper((string) $d['judul'], 'UTF-8'),
                 'tebal' => true, 'ukuran' => 12, 'rata' => 'center', 'vRata' => 'center'],
            ],
            [
                ['gabung' => 'lanjut', 'teks' => ''],
                ['baris' => [['teks' => 'No. Dokumen', 'ukuran' => 9, 'rata' => 'center'],
                             ['teks' => (string) $d['nomor'], 'ukuran' => 10, 'tebal' => true, 'rata' => 'center']]],
                ['baris' => [['teks' => 'No. Revisi', 'ukuran' => 9, 'rata' => 'center'],
                             ['teks' => (string) (int) $d['revisi'], 'ukuran' => 10, 'tebal' => true, 'rata' => 'center']]],
                ['baris' => [['teks' => 'Halaman', 'ukuran' => 9, 'rata' => 'center'],
                             ['teks' => '1/1', 'ukuran' => 10, 'tebal' => true, 'rata' => 'center']]],
            ],
            [
                ['teks' => mb_strtoupper($def['nama'], 'UTF-8'), 'tebal' => true, 'ukuran' => 10,
                 'rata' => 'center', 'vRata' => 'center'],
                ['baris' => [['teks' => 'Tanggal Terbit', 'ukuran' => 9, 'rata' => 'center'],
                             ['teks' => $d['tanggal_terbit'] ? tanggalIndo($d['tanggal_terbit']) : '—',
                              'ukuran' => 10, 'tebal' => true, 'rata' => 'center']]],
                ['kolom' => 2, 'baris' => [
                    ['teks' => 'Ditetapkan oleh', 'ukuran' => 9, 'rata' => 'center'],
                    ['teks' => self::SPASI, 'ukuran' => 11], ['teks' => self::SPASI, 'ukuran' => 11],
                    ['teks' => self::SPASI, 'ukuran' => 11],
                    ['teks' => $nama !== '' ? $nama : '(………………………)', 'ukuran' => 10,
                     'tebal' => true, 'garisBawah' => true, 'rata' => 'center'],
                    ['teks' => (string) $d['jabatan_pengesah'], 'ukuran' => 9, 'rata' => 'center'],
                ]],
            ],
        ], false);

        // batang tubuh
        $lebarBadan = [2400, DocxWriter::LEBAR_ISI - 2400];
        $rows = [];
        foreach ($def['blok'] as $b) {
            $isi = self::isi($d, $b['kode']);
            $paragraf = [];
            $baris = self::baris($isi);
            if (!$baris) {
                $paragraf[] = ['teks' => '—', 'ukuran' => 11];
            } elseif (($b['tipe'] ?? 'teks') === 'daftar') {
                foreach ($baris as $n => $x) {
                    $paragraf[] = ['teks' => ($n + 1) . '. ' . $x, 'ukuran' => 11, 'rata' => 'both',
                                   'indentKiri' => 300, 'gantung' => 300, 'spasiSesudah' => 40];
                }
            } else {
                foreach ($baris as $x) {
                    $paragraf[] = ['teks' => $x, 'ukuran' => 11, 'rata' => 'both'];
                }
            }
            $rows[] = [
                ['teks' => self::judulBlok($d, $b['kode'], $b['judul']), 'tebal' => true, 'ukuran' => 11],
                ['baris' => $paragraf],
            ];
        }
        $w->tabel($lebarBadan, $rows, false);
    }

    // -----------------------------------------------------------------
    // Pedoman / panduan
    // -----------------------------------------------------------------

    private static function pedoman(DocxWriter $w, array $d): void
    {
        self::kop($w);
        $w->paragraf(mb_strtoupper($d['def']['nama'], 'UTF-8'), ['rata' => 'center', 'tebal' => true, 'spasiSesudah' => 0]);
        $w->paragraf(mb_strtoupper((string) $d['judul'], 'UTF-8'), ['rata' => 'center', 'tebal' => true, 'ukuran' => 14, 'spasiSesudah' => 40]);
        $w->paragraf('Nomor: ' . $d['nomor'] . '  ·  Revisi: ' . (int) $d['revisi'], ['rata' => 'center', 'ukuran' => 10, 'spasiSesudah' => 200]);
        if (trim((string) $d['dasar_dokumen']) !== '') {
            $w->paragraf('Ditetapkan dengan: ' . $d['dasar_dokumen'], ['miring' => true, 'ukuran' => 10, 'spasiSesudah' => 160]);
        }
        foreach ($d['def']['blok'] as $b) {
            self::blok($w, $b, self::isi($d, $b['kode']));
        }
        self::kaki($w, $d);
    }

    private static function bab(DocxWriter $w, ?string $isi): void
    {
        foreach (self::baris($isi) as $b) {
            if (preg_match('/^(BAB\s+[IVXLC]+|[A-Z]\.\s|\d+\.\s)/u', $b) && mb_strlen($b, 'UTF-8') < 90) {
                $w->paragraf($b, ['tebal' => true, 'spasiSebelum' => 200, 'spasiSesudah' => 60]);
            } else {
                $w->paragraf($b, ['rata' => 'both', 'spasiSesudah' => 60]);
            }
        }
    }

    // -----------------------------------------------------------------
    // Formulir
    // -----------------------------------------------------------------

    private static function formulir(DocxWriter $w, array $d): void
    {
        self::kop($w);
        $w->paragraf(mb_strtoupper((string) $d['judul'], 'UTF-8'), ['rata' => 'center', 'tebal' => true, 'ukuran' => 13, 'spasiSesudah' => 0]);
        $w->paragraf($d['nomor'] . '  ·  Revisi ' . (int) $d['revisi'], ['rata' => 'center', 'ukuran' => 9, 'spasiSesudah' => 160]);

        $pengantar = trim(self::isi($d, 'pengantar'));
        if ($pengantar !== '') {
            self::teks($w, $pengantar);
        }

        $isian = self::baris(self::isi($d, 'isian'));
        if ($isian) {
            $lebar = [2600, 300, DocxWriter::LEBAR_ISI - 2900];
            $rows = [];
            foreach ($isian as $b) {
                $rows[] = [
                    ['teks' => $b, 'ukuran' => 11],
                    ['teks' => ':', 'ukuran' => 11],
                    ['teks' => str_repeat('.', 70), 'ukuran' => 11],
                ];
            }
            $w->tabel($lebar, $rows, false, false);
        }

        self::tabelTeks($w, self::isi($d, 'tabel'));

        $catatan = trim(self::isi($d, 'catatan'));
        if ($catatan !== '') {
            $w->paragraf($catatan, ['ukuran' => 9, 'miring' => true, 'spasiSebelum' => 120]);
        }
        self::kaki($w, $d);
    }

    // -----------------------------------------------------------------
    // Surat & naskah khusus
    // -----------------------------------------------------------------

    private static function surat(DocxWriter $w, array $d): void
    {
        $def = $d['def'];
        self::kop($w);

        $w->paragraf(mb_strtoupper($def['nama'], 'UTF-8'), ['rata' => 'center', 'tebal' => true, 'ukuran' => 13, 'spasiSesudah' => 0]);
        $w->paragraf('Nomor: ' . $d['nomor'], ['rata' => 'center', 'ukuran' => 11, 'spasiSesudah' => 40]);
        if (trim((string) $d['judul']) !== '') {
            $w->paragraf((string) $d['judul'], ['rata' => 'center', 'tebal' => true, 'spasiSesudah' => 200]);
        }

        $lebar = [2200, 300, DocxWriter::LEBAR_ISI - 2500];
        foreach ($def['blok'] as $b) {
            $isi = trim(self::isi($d, $b['kode']));
            if ($isi === '') {
                continue;
            }
            $judul = self::judulBlok($d, $b['kode'], $b['judul']);

            if (in_array($b['kode'], ['pembuka', 'isi', 'penutup'], true)) {
                self::blok($w, $b, $isi);
                continue;
            }
            if (($b['tipe'] ?? 'teks') === 'tabel') {
                $w->paragraf($judul, ['tebal' => true, 'spasiSebelum' => 120]);
                self::tabelTeks($w, $isi);
                continue;
            }

            $paragraf = [];
            $baris = self::baris($isi);
            if (($b['tipe'] ?? 'teks') === 'daftar') {
                foreach ($baris as $n => $x) {
                    $paragraf[] = ['teks' => ($n + 1) . '. ' . $x, 'ukuran' => 12,
                                   'indentKiri' => 340, 'gantung' => 340, 'spasiSesudah' => 40];
                }
            } else {
                foreach ($baris as $x) {
                    $paragraf[] = ['teks' => $x, 'ukuran' => 12, 'rata' => 'both'];
                }
            }
            $w->tabel($lebar, [[
                ['teks' => $judul, 'ukuran' => 12],
                ['teks' => ':', 'ukuran' => 12],
                ['baris' => $paragraf],
            ]], false, false);
        }
        self::kaki($w, $d);
    }
}
