<?php

class Assessment
{
    public static function current(): array
    {
        $id = (int) ($_SESSION['assessment_id'] ?? 0);
        if ($id) {
            $a = DB::one('SELECT * FROM assessments WHERE id = ?', [$id]);
            if ($a) {
                return $a;
            }
        }
        $a = DB::one('SELECT * FROM assessments ORDER BY tahun DESC, id DESC LIMIT 1');
        if (!$a) {
            throw new RuntimeException('Belum ada periode penilaian. Buat terlebih dahulu di menu Pengaturan.');
        }
        $_SESSION['assessment_id'] = (int) $a['id'];
        return $a;
    }

    public static function setCurrent(int $id): void
    {
        $_SESSION['assessment_id'] = $id;
    }

    public static function listAll(): array
    {
        return DB::all('SELECT * FROM assessments ORDER BY tahun DESC, nama_rs ASC');
    }

    public static function create(string $namaRs, int $tahun, string $wilayah, bool $salinData = true): int
    {
        $id = DB::insert('assessments', [
            'nama_rs' => $namaRs,
            'tahun'   => $tahun,
            'wilayah' => $wilayah,
        ]);

        $ordering = 0;
        $master = json_decode(file_get_contents(dirname(__DIR__, 2) . '/db/master.json'), true);
        foreach ($master['data_dasar'] as $f) {
            DB::insert('profil_values', [
                'assessment_id' => $id,
                'k'             => $f['key'],
                'label'         => $f['label'],
                'v'             => $f['key'] === 'nama_rumah_sakit' ? $namaRs : '',
                'ordering'      => ++$ordering,
            ]);
        }
        foreach (Installer::extraProfilFields() as $k => $label) {
            DB::insert('profil_values', [
                'assessment_id' => $id,
                'k'             => $k,
                'label'         => $label,
                'v'             => '',
                'ordering'      => ++$ordering,
            ]);
        }
        if ($salinData) {
            Installer::seedAssessmentValues($id, $master);
        }
        return $id;
    }

    // --- profil ------------------------------------------------------

    public static function profil(int $assessmentId): array
    {
        return DB::all(
            'SELECT k, label, v, ordering FROM profil_values WHERE assessment_id = ? ORDER BY ordering, k',
            [$assessmentId]
        );
    }

    public static function profilMap(int $assessmentId): array
    {
        $out = [];
        foreach (self::profil($assessmentId) as $r) {
            $out[$r['k']] = $r['v'];
        }
        return $out;
    }

    // --- struktur checklist -----------------------------------------

    public static function sections(): array
    {
        return DB::all('SELECT * FROM sections ORDER BY ordering, id');
    }

    public static function section(int $id): ?array
    {
        return DB::one('SELECT * FROM sections WHERE id = ?', [$id]);
    }

    /** Pohon poin untuk satu bagian, lengkap dengan jawaban + berkas. */
    public static function tree(int $sectionId, int $assessmentId): array
    {
        $rows = DB::all(
            'SELECT i.*, a.status, a.keterangan
               FROM items i
          LEFT JOIN answers a ON a.item_id = i.id AND a.assessment_id = ?
              WHERE i.section_id = ?
           ORDER BY i.ordering, i.id',
            [$assessmentId, $sectionId]
        );

        $docs = self::documentsBySection($sectionId, $assessmentId);
        $folders = self::foldersBySection($sectionId, $assessmentId);

        $byId = [];
        foreach ($rows as $r) {
            $r['children']  = [];
            $r['documents'] = $docs[$r['id']] ?? [];
            $r['folder']    = $folders[$r['id']] ?? null;
            $byId[$r['id']] = $r;
        }
        $tree = [];
        foreach ($byId as $id => &$node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return $tree;
    }

    private static function documentsBySection(int $sectionId, int $assessmentId): array
    {
        $rows = DB::all(
            "SELECT d.* FROM documents d
               JOIN items i ON i.id = CAST(d.owner_key AS UNSIGNED)
              WHERE d.assessment_id = ? AND d.owner_type = 'item' AND i.section_id = ?
           ORDER BY d.uploaded_at, d.id",
            [$assessmentId, $sectionId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['owner_key']][] = $r;
        }
        return $out;
    }

    private static function foldersBySection(int $sectionId, int $assessmentId): array
    {
        $rows = DB::all(
            "SELECT f.* FROM drive_folders f
               JOIN items i ON i.id = CAST(f.owner_key AS UNSIGNED)
              WHERE f.assessment_id = ? AND f.owner_type = 'item' AND i.section_id = ?",
            [$assessmentId, $sectionId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['owner_key']] = $r;
        }
        return $out;
    }

    // --- rekap kemajuan ----------------------------------------------

    public static function progress(int $assessmentId): array
    {
        $rows = DB::all(
            "SELECT s.id, s.code, s.title, s.ordering,
                    (SELECT COUNT(*) FROM items i WHERE i.section_id = s.id) AS total,
                    (SELECT COUNT(*) FROM items i
                        JOIN answers a ON a.item_id = i.id AND a.assessment_id = :aid
                       WHERE i.section_id = s.id
                         AND (a.status <> '' OR (a.keterangan IS NOT NULL AND a.keterangan <> ''))) AS terisi,
                    (SELECT COUNT(*) FROM items i
                        JOIN documents d ON d.owner_type = 'item'
                                        AND d.owner_key = CAST(i.id AS CHAR)
                                        AND d.assessment_id = :aid2
                       WHERE i.section_id = s.id) AS berkas
               FROM sections s
           ORDER BY s.ordering, s.id",
            ['aid' => $assessmentId, 'aid2' => $assessmentId]
        );
        foreach ($rows as &$r) {
            $r['total']  = (int) $r['total'];
            $r['terisi'] = (int) $r['terisi'];
            $r['berkas'] = (int) $r['berkas'];
            $r['persen'] = $r['total'] > 0 ? round($r['terisi'] / $r['total'] * 100) : 0;
        }
        return $rows;
    }

    public static function ringkasan(int $assessmentId): array
    {
        $p = self::progress($assessmentId);
        $total = array_sum(array_column($p, 'total'));
        $terisi = array_sum(array_column($p, 'terisi'));
        $dokumen = (int) DB::val('SELECT COUNT(*) FROM documents WHERE assessment_id = ?', [$assessmentId]);
        $folder  = (int) DB::val("SELECT COUNT(*) FROM drive_folders WHERE assessment_id = ? AND owner_type IN ('item','row')", [$assessmentId]);
        return [
            'total'   => $total,
            'terisi'  => $terisi,
            'persen'  => $total > 0 ? round($terisi / $total * 100) : 0,
            'dokumen' => $dokumen,
            'folder'  => $folder,
            'sections' => $p,
        ];
    }

    /**
     * Data lengkap satu poin untuk ditampilkan di jendela popup.
     * $ownerType 'item'  -> $ownerKey berisi id item
     * $ownerType 'row'   -> $ownerKey berisi "<table_code>:<row_no>"
     */
    public static function detailPoin(int $assessmentId, string $ownerType, string $ownerKey): ?array
    {
        if ($ownerType === 'item') {
            $it = DB::one(
                'SELECT i.id, i.code, i.title, i.level, s.title AS bagian,
                        a.status, a.keterangan
                   FROM items i
                   JOIN sections s ON s.id = i.section_id
              LEFT JOIN answers a ON a.item_id = i.id AND a.assessment_id = ?
                  WHERE i.id = ?',
                [$assessmentId, (int) $ownerKey]
            );
            if (!$it) {
                return null;
            }
            $induk = self::jalurInduk((int) $ownerKey);
            $info = [
                'judul'        => $it['title'],
                'kode'         => $it['code'],
                'bagian'       => $it['bagian'],
                'induk'        => $induk,
                'punya_status' => true,
                'status'       => (string) ($it['status'] ?? ''),
                'keterangan'   => (string) ($it['keterangan'] ?? ''),
            ];
        } else {
            [$tableCode, $rowNo] = array_pad(explode(':', $ownerKey, 2), 2, '');
            $def = Forms::get($tableCode);
            $row = DB::one('SELECT data FROM form_rows WHERE table_code = ? AND row_no = ?', [$tableCode, (int) $rowNo]);
            if (!$def || !$row) {
                return null;
            }
            $data = json_decode($row['data'], true) ?: [];
            $labelCol = array_values(array_diff(array_keys($def['cols']), ['no']))[0] ?? 'no';
            $info = [
                'judul'        => trim((string) ($data[$labelCol] ?? '')),
                'kode'         => (string) ($data['no'] ?? $rowNo),
                'bagian'       => $def['title'],
                'induk'        => '',
                'punya_status' => false,
                'status'       => '',
                'keterangan'   => '',
            ];
        }

        $folder = DB::one(
            'SELECT nama, drive_id, drive_link FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
            [$assessmentId, $ownerType, $ownerKey]
        );
        $docs = DB::all(
            'SELECT * FROM documents WHERE assessment_id = ? AND owner_type = ? AND owner_key = ? ORDER BY id',
            [$assessmentId, $ownerType, $ownerKey]
        );

        $info['folder'] = $folder ? [
            'nama'  => $folder['nama'],
            'link'  => $folder['drive_link'],
            'lokal' => empty($folder['drive_id']),
        ] : null;

        $info['berkas'] = array_map(fn($d) => [
            'id'     => (int) $d['id'],
            'nama'   => $d['nama_file'],
            'ukuran' => Storage::formatUkuran((int) $d['ukuran']),
            'link'   => tautanBerkas($d),
            'ikon'   => ikonBerkas($d['nama_file']),
        ], $docs);

        return $info;
    }

    /** Rangkaian judul poin induk, untuk konteks di jendela popup. */
    private static function jalurInduk(int $itemId): string
    {
        $jalur = [];
        $id = (int) DB::val('SELECT parent_id FROM items WHERE id = ?', [$itemId], 0);
        $batas = 0;
        while ($id && $batas++ < 5) {
            $p = DB::one('SELECT id, parent_id, label, title FROM items WHERE id = ?', [$id]);
            if (!$p) {
                break;
            }
            array_unshift($jalur, trim($p['label'] . ' ' . $p['title']));
            $id = (int) $p['parent_id'];
        }
        return implode(' › ', $jalur);
    }

    /** Daftar seluruh folder poin beserta tautannya (untuk rekap / lampiran). */
    public static function daftarTautan(int $assessmentId): array
    {
        $out = [];

        $rows = DB::all(
            "SELECT f.*, i.code, i.title, s.title AS bagian, s.ordering,
                    (SELECT COUNT(*) FROM documents d
                      WHERE d.assessment_id = f.assessment_id AND d.owner_type = 'item' AND d.owner_key = f.owner_key) AS jumlah
               FROM drive_folders f
               JOIN items i ON i.id = CAST(f.owner_key AS UNSIGNED)
               JOIN sections s ON s.id = i.section_id
              WHERE f.assessment_id = ? AND f.owner_type = 'item'
           ORDER BY s.ordering, i.ordering",
            [$assessmentId]
        );
        foreach ($rows as $r) {
            $out[] = [
                'bagian' => $r['bagian'],
                'kode'   => $r['code'],
                'judul'  => $r['title'],
                'jumlah' => (int) $r['jumlah'],
                'link'   => $r['drive_link'],
                'nama'   => $r['nama'],
            ];
        }

        $rows = DB::all(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM documents d
                      WHERE d.assessment_id = f.assessment_id AND d.owner_type = 'row' AND d.owner_key = f.owner_key) AS jumlah
               FROM drive_folders f
              WHERE f.assessment_id = ? AND f.owner_type = 'row'
           ORDER BY f.owner_key",
            [$assessmentId]
        );
        foreach ($rows as $r) {
            [$tableCode, $rowNo] = array_pad(explode(':', $r['owner_key'], 2), 2, '');
            $def = Forms::get($tableCode);
            $out[] = [
                'bagian' => 'Profil RS',
                'kode'   => $rowNo,
                'judul'  => ($def['title'] ?? $tableCode) . ' — ' . $r['nama'],
                'jumlah' => (int) $r['jumlah'],
                'link'   => $r['drive_link'],
                'nama'   => $r['nama'],
            ];
        }

        return $out;
    }

    // --- tabel profil -------------------------------------------------

    public static function formRows(string $tableCode, int $assessmentId): array
    {
        $def = Forms::get($tableCode);
        if (!$def) {
            return [];
        }
        $rows = DB::all('SELECT row_no, data FROM form_rows WHERE table_code = ? ORDER BY row_no', [$tableCode]);
        $vals = [];
        foreach (
            DB::all(
                'SELECT row_no, col_code, v FROM form_values WHERE assessment_id = ? AND table_code = ?',
                [$assessmentId, $tableCode]
            ) as $v
        ) {
            $vals[(int) $v['row_no']][$v['col_code']] = $v['v'];
        }

        $docs = [];
        $folders = [];
        if (!empty($def['upload'])) {
            foreach (
                DB::all(
                    "SELECT * FROM documents WHERE assessment_id = ? AND owner_type = 'row' AND owner_key LIKE ? ORDER BY id",
                    [$assessmentId, $tableCode . ':%']
                ) as $d
            ) {
                $docs[(int) explode(':', $d['owner_key'])[1]][] = $d;
            }
            foreach (
                DB::all(
                    "SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = 'row' AND owner_key LIKE ?",
                    [$assessmentId, $tableCode . ':%']
                ) as $f
            ) {
                $folders[(int) explode(':', $f['owner_key'])[1]] = $f;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $no = (int) $r['row_no'];
            $out[] = [
                'row_no'    => $no,
                'data'      => json_decode($r['data'], true) ?: [],
                'values'    => $vals[$no] ?? [],
                'documents' => $docs[$no] ?? [],
                'folder'    => $folders[$no] ?? null,
            ];
        }
        return $out;
    }

    /** Judul kolom (memperhitungkan header yang dapat diubah pengguna). */
    public static function headerLabel(string $tableCode, string $col, int $assessmentId): string
    {
        $def = Forms::get($tableCode);
        $default = $def['cols'][$col] ?? $col;
        if (in_array($col, $def['header_editable'] ?? [], true)) {
            $v = DB::val(
                'SELECT v FROM form_values WHERE assessment_id = ? AND table_code = ? AND row_no = 0 AND col_code = ?',
                [$assessmentId, $tableCode, $col]
            );
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return $default;
    }
}
