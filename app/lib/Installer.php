<?php

/**
 * Membuat skema database dan mengisi master struktur dokumen
 * dari db/master.json (hasil ekstraksi dokumen Word resmi).
 */
class Installer
{
    public static function needsInstall(): bool
    {
        try {
            return !DB::tableExists('items') || (int) DB::val('SELECT COUNT(*) FROM items') === 0;
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function run(array $cfg): array
    {
        $log = [];

        DB::ensureDatabase();
        $log[] = 'Database siap.';

        // --- skema ---------------------------------------------------
        $sql = file_get_contents($cfg['app']['root'] . '/db/schema.sql');
        foreach (self::splitStatements($sql) as $stmt) {
            DB::pdo()->exec($stmt);
        }
        $log[] = 'Skema tabel dibuat.';

        // --- pengguna default ---------------------------------------
        if ((int) DB::val('SELECT COUNT(*) FROM users') === 0) {
            DB::insert('users', [
                'username'      => $cfg['auth']['default_user'],
                'password_hash' => password_hash($cfg['auth']['default_pass'], PASSWORD_DEFAULT),
                'nama'          => 'Administrator',
                'role'          => 'admin',
            ]);
            $log[] = 'Pengguna default dibuat: ' . $cfg['auth']['default_user'];
        }

        // --- modul dokumen internal ---------------------------------
        self::pasangModulDokumen($cfg);
        $log[] = 'Tabel modul Dokumen Internal dibuat.';

        $master = json_decode(file_get_contents($cfg['app']['root'] . '/db/master.json'), true);
        if (!$master) {
            throw new RuntimeException('db/master.json tidak dapat dibaca.');
        }

        // --- master checklist ---------------------------------------
        if ((int) DB::val('SELECT COUNT(*) FROM items') === 0) {
            $ord = 0;
            foreach ($master['sections'] as $si => $sec) {
                DB::q(
                    'INSERT INTO sections (code, title, subtitle, ordering) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), ordering=VALUES(ordering)',
                    [$sec['code'], $sec['title'], $sec['subtitle'] ?? '', $si + 1]
                );
                $sectionId = (int) DB::val('SELECT id FROM sections WHERE code = ?', [$sec['code']]);

                foreach ($sec['items'] as $ii => $item) {
                    $no = $item['no'] !== '' ? $item['no'] : (string) ($ii + 1);
                    $itemId = DB::insert('items', [
                        'section_id' => $sectionId,
                        'parent_id'  => null,
                        'level'      => 0,
                        'label'      => $no . '.',
                        'code'       => $no,
                        'title'      => $item['title'],
                        'ordering'   => ++$ord,
                    ]);
                    self::insertChildren($sectionId, $itemId, $item['children'], 1, $no, $ord);

                    if (!empty($item['hasil'])) {
                        // simpan sebagai bawaan; nanti disalin ke jawaban saat periode dibuat
                        DB::q(
                            'INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
                            ['default_hasil_item_' . $itemId, $item['hasil']]
                        );
                    }
                }
            }
            $log[] = 'Master poin self assessment diisi: ' . DB::val('SELECT COUNT(*) FROM items') . ' poin.';
        }

        // --- master baris tabel profil ------------------------------
        if ((int) DB::val('SELECT COUNT(*) FROM form_rows') === 0) {
            foreach (Forms::all() as $code => $def) {
                $src = $master['tables'][$code] ?? null;
                if (!$src) {
                    continue;
                }
                $colCodes = array_keys($def['cols']);
                foreach ($src['rows'] as $i => $row) {
                    $data = [];
                    foreach ($colCodes as $ci => $cc) {
                        $data[$cc] = $row[$ci] ?? '';
                    }
                    DB::insert('form_rows', [
                        'table_code' => $code,
                        'row_no'     => $i + 1,
                        'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
            $log[] = 'Master baris tabel profil diisi: ' . DB::val('SELECT COUNT(*) FROM form_rows') . ' baris.';
        }

        // --- periode penilaian awal ---------------------------------
        if ((int) DB::val('SELECT COUNT(*) FROM assessments') === 0) {
            $dd = [];
            foreach ($master['data_dasar'] as $f) {
                $dd[$f['key']] = $f;
            }
            $namaRs = $dd['nama_rumah_sakit']['value'] ?? 'Rumah Sakit';
            $tahun  = (int) date('Y');
            $aid = DB::insert('assessments', [
                'nama_rs' => $namaRs,
                'tahun'   => $tahun,
                'status'  => 'draft',
            ]);

            $ordering = 0;
            foreach ($master['data_dasar'] as $f) {
                DB::insert('profil_values', [
                    'assessment_id' => $aid,
                    'k'             => $f['key'],
                    'label'         => $f['label'],
                    'v'             => $f['value'],
                    'ordering'      => ++$ordering,
                ]);
            }
            foreach (self::extraProfilFields() as $k => $label) {
                DB::insert('profil_values', [
                    'assessment_id' => $aid,
                    'k'             => $k,
                    'label'         => $label,
                    'v'             => '',
                    'ordering'      => ++$ordering,
                ]);
            }

            self::seedAssessmentValues($aid, $master);
            $log[] = 'Periode penilaian awal dibuat: ' . $namaRs . ' tahun ' . $tahun . '.';
        }

        DB::q('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', ['installed_at', date('c')]);
        DB::q(
            'INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=v',
            ['gdrive_root_folder_id', $cfg['drive']['root_folder_id']]
        );

        return $log;
    }

    /** Menyalin nilai bawaan dokumen ke sebuah periode penilaian. */
    public static function seedAssessmentValues(int $assessmentId, ?array $master = null): void
    {
        if ($master === null) {
            $master = json_decode(file_get_contents(dirname(__DIR__, 2) . '/db/master.json'), true);
        }

        // nilai sel tabel yang sudah terisi di dokumen Word
        foreach (Forms::all() as $code => $def) {
            $rows = DB::all('SELECT row_no, data FROM form_rows WHERE table_code = ? ORDER BY row_no', [$code]);
            foreach ($rows as $r) {
                $data = json_decode($r['data'], true) ?: [];
                foreach ($def['editable'] as $col) {
                    $v = trim((string) ($data[$col] ?? ''));
                    if ($v === '') {
                        continue;
                    }
                    DB::q(
                        'INSERT INTO form_values (assessment_id, table_code, row_no, col_code, v) VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE v = VALUES(v)',
                        [$assessmentId, $code, (int) $r['row_no'], $col, $v]
                    );
                }
            }
        }

        // keterangan bawaan pada poin utama
        $defaults = DB::all("SELECT k, v FROM settings WHERE k LIKE 'default_hasil_item_%'");
        foreach ($defaults as $d) {
            $itemId = (int) substr($d['k'], strlen('default_hasil_item_'));
            if ($itemId <= 0 || $d['v'] === null || $d['v'] === '') {
                continue;
            }
            DB::q(
                'INSERT INTO answers (assessment_id, item_id, keterangan) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan)',
                [$assessmentId, $itemId, $d['v']]
            );
        }
    }

    public static function extraProfilFields(): array
    {
        return [
            'sk_tempat_tidur' => 'SK Tempat Tidur Rumah Sakit Nomor',
            'sk_kris'         => 'SK Tempat Tidur KRIS Nomor',
            'nama_direktur_ttd' => 'Nama Penandatangan',
            'kota_ttd'        => 'Kota Penandatanganan',
            'tanggal_ttd'     => 'Tanggal Penandatanganan',
        ];
    }

    private static function insertChildren(int $sectionId, int $parentId, array $children, int $level, string $prefix, int &$ord): void
    {
        foreach ($children as $i => $c) {
            $label = (string) ($c['label'] ?? '');
            $code  = $prefix . '.' . self::kodeDariLabel($label, $i);
            $id = DB::insert('items', [
                'section_id' => $sectionId,
                'parent_id'  => $parentId,
                'level'      => $level,
                'label'      => $label,
                'code'       => $code,
                'title'      => $c['text'],
                'ordering'   => ++$ord,
            ]);
            if (!empty($c['children'])) {
                self::insertChildren($sectionId, $id, $c['children'], $level + 1, $code, $ord);
            }
        }
    }

    /**
     * Potongan kode penomoran untuk membentuk jalur seperti "3.k.7"
     * (dipakai pada nama folder Google Drive). Label bullet diganti nomor urut.
     */
    private static function kodeDariLabel(string $label, int $index): string
    {
        $k = trim($label, " \t.)(-•·o");
        return $k !== '' ? $k : (string) ($index + 1);
    }

    /**
     * Menyelaraskan penomoran DAN susunan bertingkat poin pada pemasangan yang
     * sudah berjalan dengan dokumen Word terbaru. Hanya kolom label, code,
     * level, dan parent_id yang disentuh — jawaban, dokumen, dan folder aman
     * karena tetap menunjuk id poin yang sama.
     *
     * @return array{diperbarui:int,dilewati:int,pesan:string}
     */
    public static function perbaruiPenomoran(array $cfg): array
    {
        $master = json_decode(file_get_contents($cfg['app']['root'] . '/db/master.json'), true);
        if (!$master) {
            return ['diperbarui' => 0, 'dilewati' => 0, 'pesan' => 'db/master.json tidak dapat dibaca.'];
        }

        // Susun ulang urutan persis seperti saat pemasangan (telusur mendalam).
        $rencana = [];
        $ord = 0;
        $ratakan = function (array $anak, string $prefix, int $level, ?int $indukOrd)
            use (&$ratakan, &$rencana, &$ord) {
            foreach ($anak as $i => $c) {
                $label = (string) ($c['label'] ?? '');
                $code  = $prefix . '.' . self::kodeDariLabel($label, $i);
                $rencana[++$ord] = [
                    'label' => $label, 'code' => $code, 'title' => $c['text'],
                    'level' => $level, 'indukOrd' => $indukOrd,
                ];
                $ordSaya = $ord;
                if (!empty($c['children'])) {
                    $ratakan($c['children'], $code, $level + 1, $ordSaya);
                }
            }
        };
        foreach ($master['sections'] as $sec) {
            foreach ($sec['items'] as $ii => $item) {
                $no = $item['no'] !== '' ? $item['no'] : (string) ($ii + 1);
                $rencana[++$ord] = [
                    'label' => $no . '.', 'code' => $no, 'title' => $item['title'],
                    'level' => 0, 'indukOrd' => null,
                ];
                $ratakan($item['children'], $no, 1, $ord);
            }
        }

        $baris = DB::all('SELECT id, ordering, title, label, code, level, parent_id FROM items ORDER BY ordering');
        if (count($baris) !== count($rencana)) {
            return [
                'diperbarui' => 0,
                'dilewati'   => count($baris),
                'pesan'      => 'Jumlah poin di database (' . count($baris) . ') berbeda dengan dokumen ('
                    . count($rencana) . '). Struktur tidak diubah — pasang ulang master untuk menyelaraskan.',
            ];
        }

        // Semua judul harus cocok sebelum apa pun diubah.
        $idPerOrdering = [];
        foreach ($baris as $b) {
            $r = $rencana[(int) $b['ordering']] ?? null;
            if (!$r || $r['title'] !== $b['title']) {
                return [
                    'diperbarui' => 0,
                    'dilewati'   => count($baris),
                    'pesan'      => 'Susunan poin di database berbeda dengan dokumen (poin ke-'
                        . $b['ordering'] . '). Struktur tidak diubah.',
                ];
            }
            $idPerOrdering[(int) $b['ordering']] = (int) $b['id'];
        }

        $diperbarui = 0;
        DB::pdo()->beginTransaction();
        try {
            foreach ($baris as $b) {
                $r = $rencana[(int) $b['ordering']];
                $indukId = $r['indukOrd'] !== null ? ($idPerOrdering[$r['indukOrd']] ?? null) : null;
                if (
                    $r['label'] === $b['label'] && $r['code'] === $b['code']
                    && (int) $r['level'] === (int) $b['level']
                    && $indukId === ($b['parent_id'] !== null ? (int) $b['parent_id'] : null)
                ) {
                    continue;
                }
                DB::q(
                    'UPDATE items SET label = ?, code = ?, level = ?, parent_id = ? WHERE id = ?',
                    [$r['label'], $r['code'], $r['level'], $indukId, (int) $b['id']]
                );
                $diperbarui++;
            }
            DB::pdo()->commit();
        } catch (Throwable $e) {
            DB::pdo()->rollBack();
            return ['diperbarui' => 0, 'dilewati' => 0, 'pesan' => 'Gagal menyelaraskan: ' . $e->getMessage()];
        }

        Settings::set('master_penomoran', self::PENOMORAN_VERSI);
        return [
            'diperbarui' => $diperbarui,
            'dilewati'   => 0,
            'pesan'      => $diperbarui . ' poin diselaraskan dengan penomoran dan susunan dokumen Word.',
        ];
    }

    /** Dinaikkan bila penomoran master berubah, memicu penyelarasan otomatis. */
    public const PENOMORAN_VERSI = '3';

    /**
     * Dinaikkan bila skema modul Dokumen Internal berubah, sehingga pemasangan
     * yang sudah berjalan ikut diperbarui tanpa perlu memasang ulang.
     */
    public const DOKUMEN_VERSI = '1';

    /**
     * Membuat/menyelaraskan tabel modul Dokumen Internal.
     * Semua pernyataan memakai CREATE TABLE IF NOT EXISTS, jadi aman dijalankan
     * berulang kali dan tidak menyentuh data modul Binwasdal.
     */
    public static function pasangModulDokumen(array $cfg): void
    {
        $berkas = $cfg['app']['root'] . '/db/schema_dokumen.sql';
        if (!is_file($berkas)) {
            throw new RuntimeException('db/schema_dokumen.sql tidak ditemukan.');
        }
        foreach (self::splitStatements((string) file_get_contents($berkas)) as $stmt) {
            DB::pdo()->exec($stmt);
        }
        Settings::set('modul_dokumen', self::DOKUMEN_VERSI);
    }

    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = array_map('trim', explode(';', $sql));
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }
}
