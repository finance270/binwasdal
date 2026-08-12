<?php

/**
 * Menyusun dokumen Word (.docx) dari data satu periode penilaian.
 * Tata letaknya mengikuti dokumen Self Assessment resmi: kertas A4, Arial,
 * tabel bergaris, penomoran poin sesuai dokumen asli.
 */
class EksporWord
{
    private const ARSIR_HEADER = 'DFE6EE';
    private const ARSIR_INDUK  = 'EEF3F9';

    /**
     * @param string $bagian 'all' | 'profil' | 'sdm' | id bagian
     * @return array{nama:string,isi:string}
     */
    public static function buat(array $assessment, string $bagian = 'all'): array
    {
        $aid = (int) $assessment['id'];
        $doc = new DocxWriter();
        $profil = Assessment::profilMap($aid);
        $sections = Assessment::sections();
        $extra = Installer::extraProfilFields();

        $tampilProfil = in_array($bagian, ['all', 'profil'], true);
        $tampilSdm    = in_array($bagian, ['all', 'sdm'], true);
        $idBagian = $bagian === 'all'
            ? array_map('intval', array_column($sections, 'id'))
            : (ctype_digit($bagian) ? [(int) $bagian] : []);

        $pertama = true;

        // ------------------------------------------------ profil RS
        if ($tampilProfil) {
            $doc->judul('DOKUMEN SELF ASSESSMENT PEMBINAAN DAN PENGAWASAN');
            $doc->paragraf(
                'RUMAH SAKIT DI ' . mb_strtoupper($assessment['wilayah'], 'UTF-8')
                . ' TAHUN ' . (int) $assessment['tahun'],
                ['tebal' => true, 'ukuran' => 12, 'rata' => 'center', 'spasiSesudah' => 200]
            );

            $doc->subBab('Data Dasar Rumah Sakit');
            $baris = [];
            foreach (Assessment::profil($aid) as $f) {
                if (isset($extra[$f['k']])) {
                    continue;
                }
                $baris[] = [$f['label'], ':', (string) $f['v']];
            }
            $doc->tabel([4000, 300, 5338], $baris, false, false);

            self::tabelProfil($doc, 'kompetensi', $aid);

            $doc->subBab('Kapasitas Tempat Tidur');
            $doc->tabel([5200, 300, 4138], [
                ['SK Tempat Tidur Rumah Sakit Nomor', ':', (string) ($profil['sk_tempat_tidur'] ?? '')],
                ['SK Tempat Tidur KRIS (Kelas Rawat Inap Standar) Nomor', ':', (string) ($profil['sk_kris'] ?? '')],
            ], false, false);
            self::tabelProfil($doc, 'tempat_tidur', $aid, true);

            foreach (['perizinan', 'sarana', 'kinerja', 'penyakit_rj', 'penyakit_ri'] as $kode) {
                self::tabelProfil($doc, $kode, $aid);
            }
            $pertama = false;
        }

        // ------------------------------------------- bagian penilaian
        foreach ($sections as $i => $s) {
            if (!in_array((int) $s['id'], $idBagian, true)) {
                continue;
            }
            if (!$pertama) {
                $doc->pindahHalaman();
            }
            $pertama = false;

            $doc->bab(romawi($i + 1) . '. ' . $s['title']);
            if (!empty($s['subtitle'])) {
                $doc->subBab($s['subtitle']);
            }

            $baris = [[
                ['teks' => 'No', 'tebal' => true, 'rata' => 'center', 'arsir' => self::ARSIR_HEADER, 'vRata' => 'center'],
                ['teks' => 'Unit Pelayanan / Program', 'tebal' => true, 'rata' => 'center', 'arsir' => self::ARSIR_HEADER, 'vRata' => 'center'],
                ['teks' => 'Hasil Self Assessment Rumah Sakit', 'tebal' => true, 'rata' => 'center', 'arsir' => self::ARSIR_HEADER, 'vRata' => 'center'],
            ]];
            self::barisPoin(Assessment::tree((int) $s['id'], $aid), $baris);
            $doc->tabel([560, 4700, 4378], $baris);
        }

        // ------------------------------------------------- ketenagaan
        if ($tampilSdm) {
            if (!$pertama) {
                $doc->pindahHalaman();
            }
            foreach (['sdm_detail', 'rekap_sdm'] as $kode) {
                self::tabelProfil($doc, $kode, $aid);
            }

            $doc->kosong(2);
            $ttd = trim((string) ($profil['nama_direktur_ttd'] ?? ''));
            if ($ttd === '') {
                $ttd = (string) ($profil['nama_direktur_rs'] ?? '');
            }
            $kolomKanan = [
                ['teks' => trim(($profil['kota_ttd'] ?? 'Jakarta') . ', ' . ($profil['tanggal_ttd'] ?? '')), 'rata' => 'center'],
                ['teks' => 'Mengetahui,', 'rata' => 'center'],
                ['teks' => 'Kepala/Direktur ' . $assessment['nama_rs'], 'rata' => 'center'],
                ['teks' => '', 'rata' => 'center'],
                ['teks' => '', 'rata' => 'center'],
                ['teks' => '', 'rata' => 'center'],
                ['teks' => $ttd !== '' ? $ttd : '(Nama Direktur Rumah Sakit)', 'rata' => 'center', 'tebal' => true, 'garisBawah' => true],
                ['teks' => '(Materai dan Tanda Tangan)', 'rata' => 'center', 'ukuran' => 9],
            ];
            $doc->tabel([5138, 4500], [[
                ['teks' => ''],
                ['baris' => $kolomKanan],
            ]], false, false);
        }

        $nama = 'Self Assessment ' . $assessment['tahun'] . ' - ' . $assessment['nama_rs'];
        if ($bagian === 'profil') {
            $nama .= ' (Profil RS)';
        } elseif ($bagian === 'sdm') {
            $nama .= ' (Ketenagaan)';
        } elseif (ctype_digit($bagian)) {
            foreach ($sections as $s) {
                if ((int) $s['id'] === (int) $bagian) {
                    $nama .= ' - ' . $s['title'];
                }
            }
        }

        return [
            'nama' => preg_replace('/[^\p{L}\p{N} \-\(\)\.]+/u', '', $nama) . '.docx',
            'isi'  => $doc->keluarkan(),
        ];
    }

    /** Menyusun baris tabel untuk pohon poin secara rekursif. */
    private static function barisPoin(array $nodes, array &$baris): void
    {
        foreach ($nodes as $n) {
            $level = (int) $n['level'];
            $induk = $level === 0;
            $arsir = $induk ? self::ARSIR_INDUK : null;

            $runs = [];
            if ($level > 0 && $n['label'] !== '') {
                $runs[] = ['teks' => $n['label'] . ' ', 'tebal' => true, 'ukuran' => 10];
            }
            $runs[] = ['teks' => $n['title'], 'tebal' => $induk, 'ukuran' => 10];

            $baris[] = [
                [
                    'teks'  => $induk ? $n['label'] : '',
                    'rata'  => 'center',
                    'tebal' => true,
                    'ukuran' => 10,
                    'arsir' => $arsir,
                ],
                [
                    'baris' => [[
                        'runs'       => $runs,
                        'indentKiri' => $level * 200,
                        'ukuran'     => 10,
                    ]],
                    'arsir' => $arsir,
                ],
                [
                    'baris' => self::isiHasil($n),
                    'arsir' => $arsir,
                ],
            ];

            if (!empty($n['children'])) {
                self::barisPoin($n['children'], $baris);
            }
        }
    }

    /** Isi kolom "Hasil Self Assessment": status, keterangan, folder, berkas. */
    private static function isiHasil(array $n): array
    {
        $out = [];
        $status = (string) ($n['status'] ?? '');
        if ($status !== '') {
            $out[] = ['teks' => statusLabel($status), 'tebal' => true, 'ukuran' => 10];
        }
        $ket = trim((string) ($n['keterangan'] ?? ''));
        if ($ket !== '') {
            $out[] = ['teks' => $ket, 'ukuran' => 10];
        }
        if (!empty($n['folder']['drive_link'])) {
            $out[] = [
                'runs' => [
                    ['teks' => 'Folder dokumen: ', 'ukuran' => 9],
                    ['teks' => $n['folder']['drive_link'], 'tautan' => $n['folder']['drive_link'], 'ukuran' => 9],
                ],
                'ukuran' => 9,
            ];
        }
        foreach ($n['documents'] ?? [] as $d) {
            $teks = '• ' . $d['nama_file'];
            if (!empty($d['drive_link'])) {
                $out[] = ['runs' => [['teks' => $teks, 'tautan' => $d['drive_link'], 'ukuran' => 9]], 'ukuran' => 9];
            } else {
                $out[] = ['teks' => $teks, 'ukuran' => 9];
            }
        }
        return $out;
    }

    /** Menulis satu tabel profil ke dokumen. */
    private static function tabelProfil(DocxWriter $doc, string $kode, int $aid, bool $tanpaJudul = false): void
    {
        $def = Forms::get($kode);
        if (!$def) {
            return;
        }
        if (!$tanpaJudul) {
            $doc->subBab($def['title']);
        }

        $kolom = array_keys($def['cols']);
        $persen = [];
        foreach ($kolom as $c) {
            $persen[] = $def['widths'][$c] ?? (int) (100 / count($kolom));
        }
        if (!empty($def['upload'])) {
            $kolom[] = '__unggah';
            $persen[] = $def['upload_width'] ?? 25;
        }
        $totalPersen = array_sum($persen) ?: 100;
        $lebar = [];
        foreach ($persen as $p) {
            $lebar[] = (int) round(DocxWriter::LEBAR_ISI * $p / $totalPersen);
        }
        // sesuaikan pembulatan agar totalnya pas
        $lebar[count($lebar) - 1] += DocxWriter::LEBAR_ISI - array_sum($lebar);

        $header = [];
        foreach ($kolom as $c) {
            $judul = $c === '__unggah'
                ? ($def['upload_title'] ?? 'Berkas Pendukung')
                : Assessment::headerLabel($kode, $c, $aid);
            $header[] = ['teks' => $judul, 'tebal' => true, 'rata' => 'center',
                         'arsir' => self::ARSIR_HEADER, 'vRata' => 'center', 'ukuran' => 10];
        }
        $baris = [$header];

        foreach (Assessment::formRows($kode, $aid) as $r) {
            $sel = [];
            foreach ($kolom as $c) {
                if ($c === '__unggah') {
                    $isi = [];
                    if (!empty($r['folder']['drive_link'])) {
                        $isi[] = ['runs' => [['teks' => 'Folder dokumen', 'tautan' => $r['folder']['drive_link'], 'ukuran' => 9]], 'ukuran' => 9];
                    }
                    foreach ($r['documents'] as $d) {
                        $teks = '• ' . $d['nama_file'];
                        $isi[] = !empty($d['drive_link'])
                            ? ['runs' => [['teks' => $teks, 'tautan' => $d['drive_link'], 'ukuran' => 9]], 'ukuran' => 9]
                            : ['teks' => $teks, 'ukuran' => 9];
                    }
                    $sel[] = ['baris' => $isi];
                    continue;
                }
                $nilai = in_array($c, $def['fixed'], true)
                    ? ($r['data'][$c] ?? '')
                    : ($r['values'][$c] ?? ($r['data'][$c] ?? ''));
                $sel[] = [
                    'teks'   => (string) $nilai,
                    'rata'   => ($def['align'][$c] ?? '') === 'center' ? 'center' : 'left',
                    'ukuran' => 10,
                ];
            }
            $baris[] = $sel;
        }

        $doc->tabel($lebar, $baris);

        if (!empty($def['note'])) {
            $doc->paragraf($def['note'], ['miring' => true, 'ukuran' => 9, 'spasiSesudah' => 120]);
        }
    }
}
