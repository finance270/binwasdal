<?php

/**
 * Komponen tampilan yang dipakai berulang: kotak kendali unggah + keterangan.
 */
class Render
{
    /**
     * Sel ringkas pada kolom "Hasil Self Assessment".
     * Hanya menampilkan penanda (Ada / Belum Ada / kosong) dan jumlah berkas.
     * Pengisian dan daftar unggahan dibuka lewat jendela popup.
     *
     * @param array $o {
     *   owner_type   : 'item'|'row'
     *   owner_key    : id item atau "<table_code>:<row_no>"
     *   item_id      : id item (bila owner_type = item)
     *   status       : status jawaban
     *   keterangan   : teks keterangan
     *   documents    : daftar dokumen
     *   folder       : baris drive_folders
     *   punya_status : sel ini memakai status + keterangan (default true)
     * }
     */
    public static function selPoin(array $o): string
    {
        $ownerType = $o['owner_type'];
        $ownerKey  = (string) $o['owner_key'];
        $itemId    = $o['item_id'] ?? null;
        $status    = (string) ($o['status'] ?? '');
        $ket       = trim((string) ($o['keterangan'] ?? ''));
        $docs      = $o['documents'] ?? [];
        $folder    = $o['folder'] ?? null;
        $punyaStatus = $o['punya_status'] ?? true;

        $jml = count($docs);
        $kosong = $status === '' && $ket === '' && $jml === 0;

        $h = '<div class="poin-sel' . ($kosong ? ' kosong' : '') . '"'
            . ' data-owner-type="' . e($ownerType) . '"'
            . ' data-owner-key="' . e($ownerKey) . '"'
            . ($itemId ? ' data-item="' . (int) $itemId . '"' : '')
            . ' data-status="' . e($status) . '"'
            . ' data-berkas="' . $jml . '"'
            . ' role="button" tabindex="0"'
            . ' title="Klik untuk mengisi keterangan dan mengunggah dokumen">';

        $h .= '<div class="ringkas-baris">';
        if ($punyaStatus && $status !== '') {
            $h .= '<span class="badge b-' . e($status) . '">' . e(statusLabel($status)) . '</span>';
        }
        if ($jml > 0) {
            $h .= '<span class="lampiran">📎 ' . $jml . ' berkas</span>';
        }
        if ($kosong) {
            $h .= '<span class="isi-hint">＋ klik untuk mengisi</span>';
        }
        $h .= '</div>';

        if ($ket !== '') {
            $h .= '<div class="ket-ringkas">' . enl($ket) . '</div>';
        }

        // hanya muncul saat halaman ini dicetak langsung
        if ($folder && !empty($folder['drive_link'])) {
            $h .= '<div class="tautan-cetak url-cetak">📂 ' . e($folder['drive_link']) . '</div>';
        }
        if ($docs) {
            $h .= '<ul class="berkas-cetak">';
            foreach ($docs as $d) {
                $h .= '<li>' . e($d['nama_file']) . '</li>';
            }
            $h .= '</ul>';
        }

        $h .= '</div>';
        return $h;
    }

    /** Sel tabel yang dapat diisi langsung (contenteditable). */
    public static function sel(string $tableCode, int $rowNo, string $col, ?string $nilai, string $kosong = '—'): string
    {
        $editable = Auth::canEdit() ? ' contenteditable="true"' : '';
        return '<div class="sel" data-tabel="' . e($tableCode) . '" data-baris="' . $rowNo . '" data-kolom="' . e($col) . '"'
            . $editable . ' data-kosong="' . e($kosong) . '">' . e((string) $nilai) . '</div>';
    }

    /** Sel untuk data dasar rumah sakit. */
    public static function selProfil(string $key, ?string $nilai, string $kosong = '—'): string
    {
        $editable = Auth::canEdit() ? ' contenteditable="true"' : '';
        return '<div class="sel" data-profil="' . e($key) . '"' . $editable . ' data-kosong="' . e($kosong) . '">' . e((string) $nilai) . '</div>';
    }

    /** Satu tabel profil lengkap dengan header, isian, dan tombol unggah per baris. */
    public static function tabelForm(string $code, int $assessmentId, bool $tanpaJudul = false): string
    {
        $def = Forms::get($code);
        if (!$def) {
            return '';
        }
        $rows = Assessment::formRows($code, $assessmentId);
        $cols = $def['cols'];

        $h = $tanpaJudul ? '' : '<div class="sub-bab">' . e($def['title']) . '</div>';
        $h .= '<div class="tabel-gulir"><table class="w"><thead><tr>';
        foreach ($cols as $c => $label) {
            $w = $def['widths'][$c] ?? null;
            $judul = Assessment::headerLabel($code, $c, $assessmentId);
            $editableHeader = in_array($c, $def['header_editable'] ?? [], true);
            $kelasTh = ($def['align'][$c] ?? '') === 'center' ? ' class="tengah"' : '';
            $h .= '<th' . $kelasTh . ($w ? ' style="width:' . $w . '%"' : '') . '>';
            $h .= $editableHeader ? self::sel($code, 0, $c, $judul, $label) : e($judul);
            $h .= '</th>';
        }
        if (!empty($def['upload'])) {
            $h .= '<th style="width:' . (int) ($def['upload_width'] ?? 30) . '%">'
                . e($def['upload_title'] ?? 'Dokumen Pendukung') . '</th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $h .= '<tr>';
            foreach ($cols as $c => $label) {
                $align = ($def['align'][$c] ?? '') === 'center' ? ' class="tengah"' : '';
                $h .= '<td' . $align . '>';
                if (in_array($c, $def['fixed'], true)) {
                    $h .= e((string) ($r['data'][$c] ?? ''));
                } else {
                    $nilai = $r['values'][$c] ?? ($r['data'][$c] ?? '');
                    $h .= self::sel($code, $r['row_no'], $c, $nilai);
                }
                $h .= '</td>';
            }
            if (!empty($def['upload'])) {
                $h .= '<td>' . self::selPoin([
                    'owner_type'   => 'row',
                    'owner_key'    => $code . ':' . $r['row_no'],
                    'documents'    => $r['documents'],
                    'folder'       => $r['folder'],
                    'punya_status' => false,
                ]) . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
        if (!empty($def['note'])) {
            $h .= '<p style="font-size:10pt;font-style:italic">' . e($def['note']) . '</p>';
        }
        return $h;
    }

    /** Versi cetak (tanpa tombol) untuk tabel profil. */
    public static function tabelFormCetak(string $code, int $assessmentId, bool $tanpaJudul = false): string
    {
        $def = Forms::get($code);
        if (!$def) {
            return '';
        }
        $rows = Assessment::formRows($code, $assessmentId);
        $cols = $def['cols'];

        $h = $tanpaJudul ? '' : '<div class="sub-bab">' . e($def['title']) . '</div>';
        $h .= '<div class="tabel-gulir"><table class="w"><thead><tr>';
        foreach ($cols as $c => $label) {
            $w = $def['widths'][$c] ?? null;
            $kelasTh = ($def['align'][$c] ?? '') === 'center' ? ' class="tengah"' : '';
            $h .= '<th' . $kelasTh . ($w ? ' style="width:' . $w . '%"' : '') . '>'
                . e(Assessment::headerLabel($code, $c, $assessmentId)) . '</th>';
        }
        if (!empty($def['upload'])) {
            $h .= '<th style="width:' . (int) ($def['upload_width'] ?? 30) . '%">'
                . e($def['upload_title'] ?? 'Dokumen Pendukung') . '</th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $h .= '<tr>';
            foreach ($cols as $c => $label) {
                $align = ($def['align'][$c] ?? '') === 'center' ? ' class="tengah"' : '';
                $nilai = in_array($c, $def['fixed'], true)
                    ? ($r['data'][$c] ?? '')
                    : ($r['values'][$c] ?? ($r['data'][$c] ?? ''));
                $h .= '<td' . $align . '>' . enl((string) $nilai) . '</td>';
            }
            if (!empty($def['upload'])) {
                $h .= '<td>' . self::berkasCetak($r['documents'], $r['folder']) . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
        return $h;
    }

    /** Ringkasan berkas + tautan folder untuk mode cetak. */
    public static function berkasCetak(array $docs, ?array $folder, string $keterangan = ''): string
    {
        $h = '';
        if ($keterangan !== '') {
            $h .= '<div>' . enl($keterangan) . '</div>';
        }
        if ($folder && !empty($folder['drive_link'])) {
            $h .= '<div class="tautan-cetak">📂 Folder: <a href="' . e($folder['drive_link']) . '">' . e($folder['drive_link']) . '</a></div>';
        }
        if ($docs) {
            $h .= '<ul style="margin:2px 0 0 14px;padding:0;font-size:9pt">';
            foreach ($docs as $d) {
                $h .= '<li>' . e($d['nama_file']) . '</li>';
            }
            $h .= '</ul>';
        }
        if ($h === '') {
            $h = '<span style="color:#666">—</span>';
        }
        return $h;
    }

    /** Baris-baris pohon poin untuk halaman isian. */
    public static function barisPoin(array $nodes, bool $cetak = false): string
    {
        $h = '';
        foreach ($nodes as $n) {
            $level = (int) $n['level'];
            $kelas = 'poin ' . ($level === 0 ? 'utama' : 'anak');
            $ind = $level > 0 ? ' ind-' . min($level, 3) : '';

            $h .= '<tr class="' . $kelas . '" data-status="' . e((string) ($n['status'] ?? '')) . '"'
                . ' data-berkas="' . count($n['documents']) . '">';
            $h .= '<td class="no">' . ($level === 0 ? e($n['label']) : '') . '</td>';
            $h .= '<td class="uraian' . $ind . '">';
            if ($level > 0) {
                $h .= '<span class="lbl">' . e($n['label']) . '</span>';
            } else {
                // hanya tampil pada tata letak ponsel, saat kolom "No" disembunyikan
                $h .= '<span class="lbl lbl-hp">' . e($n['label']) . '</span>';
            }
            $h .= e($n['title']) . '</td>';

            $h .= '<td class="hasil">';
            if ($cetak) {
                $h .= self::berkasCetak($n['documents'], $n['folder'], self::statusTeks($n) . (string) $n['keterangan']);
            } else {
                $h .= self::selPoin([
                    'owner_type' => 'item',
                    'owner_key'  => (string) $n['id'],
                    'item_id'    => (int) $n['id'],
                    'status'     => (string) ($n['status'] ?? ''),
                    'keterangan' => (string) ($n['keterangan'] ?? ''),
                    'documents'  => $n['documents'],
                    'folder'     => $n['folder'],
                ]);
            }
            $h .= '</td></tr>';

            if (!empty($n['children'])) {
                $h .= self::barisPoin($n['children'], $cetak);
            }
        }
        return $h;
    }

    private static function statusTeks(array $n): string
    {
        $s = (string) ($n['status'] ?? '');
        if ($s === '') {
            return '';
        }
        return '[' . statusLabel($s) . '] ';
    }
}
