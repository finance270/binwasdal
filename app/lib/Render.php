<?php

/**
 * Komponen tampilan yang dipakai berulang: kotak kendali unggah + keterangan.
 */
class Render
{
    /**
     * @param array $o {
     *   owner_type : 'item'|'row'
     *   owner_key  : id item atau "<table_code>:<row_no>"
     *   item_id    : id item (bila owner_type = item) — dipakai untuk simpan jawaban
     *   status     : status jawaban
     *   keterangan : teks keterangan
     *   documents  : daftar dokumen
     *   folder     : baris drive_folders
     *   status_tampil : tampilkan pilihan status (default true)
     *   ket_tampil    : tampilkan kotak keterangan (default true)
     *   ringkas       : mode ringkas (untuk baris tabel profil)
     * }
     */
    public static function kendali(array $o): string
    {
        $ownerType = $o['owner_type'];
        $ownerKey  = (string) $o['owner_key'];
        $itemId    = $o['item_id'] ?? null;
        $status    = (string) ($o['status'] ?? '');
        $ket       = (string) ($o['keterangan'] ?? '');
        $docs      = $o['documents'] ?? [];
        $folder    = $o['folder'] ?? null;
        $tampilStatus = $o['status_tampil'] ?? true;
        $tampilKet    = $o['ket_tampil'] ?? true;
        $bolehEdit = Auth::canEdit();

        $h = '<div class="kendali-wrap" data-owner-type="' . e($ownerType) . '" data-owner-key="' . e($ownerKey) . '"'
            . ($itemId ? ' data-item="' . (int) $itemId . '"' : '') . '>';
        $h .= '<div class="kendali">';

        // status versi cetak (tampil hanya saat dicetak)
        if ($tampilStatus && $status !== '') {
            $h .= '<div class="status-cetak">[' . e(statusLabel($status)) . ']</div>';
        }

        // baris 1: status + tombol
        $h .= '<div class="baris-kendali no-print">';
        if ($tampilStatus) {
            $h .= '<select class="status s-' . e($status ?: 'kosong') . '"' . ($bolehEdit ? '' : ' disabled') . '>';
            foreach (statusOptions() as $v => $label) {
                $h .= '<option value="' . e($v) . '"' . ($v === $status ? ' selected' : '') . '>' . e($label) . '</option>';
            }
            $h .= '</select>';
        }
        if ($bolehEdit) {
            $h .= '<button type="button" class="btn btn-unggah" title="Pilih satu atau beberapa berkas">⬆️ Unggah</button>';
            $h .= '<button type="button" class="btn btn-folder kecil" title="Buat / buka folder Google Drive poin ini">📂 Folder</button>';
        }
        $h .= '</div>';

        // baris 2: keterangan
        if ($tampilKet) {
            if ($bolehEdit) {
                $h .= '<textarea class="ket no-print" rows="1" placeholder="Keterangan / hasil self assessment…">' . e($ket) . '</textarea>';
            }
            $h .= '<div class="ket-cetak" style="display:none">' . enl($ket) . '</div>';
        }

        // baris 3: tautan folder
        $h .= '<div class="baris-kendali baris-folder">';
        if ($folder) {
            if (!empty($folder['drive_link'])) {
                $h .= '<a class="chip-folder" href="' . e($folder['drive_link']) . '" target="_blank" rel="noopener">📂 Buka folder Google Drive</a>';
                $h .= '<span class="tautan-cetak url-cetak">📂 ' . e($folder['drive_link']) . '</span>';
            } else {
                $h .= '<span class="chip-folder lokal">📁 Tersimpan lokal (Drive belum aktif)</span>';
            }
        }
        $h .= '</div>';

        // baris 4: daftar berkas
        if ($docs) {
            $h .= '<ul class="berkas">';
            foreach ($docs as $d) {
                $h .= '<li data-doc="' . (int) $d['id'] . '">'
                    . '<span>' . ikonBerkas($d['nama_file']) . '</span>'
                    . '<a href="' . e(tautanBerkas($d)) . '" target="_blank" rel="noopener">' . e($d['nama_file']) . '</a>'
                    . '<span class="ukuran">(' . e(Storage::formatUkuran((int) $d['ukuran'])) . ')</span>'
                    . ($bolehEdit ? '<button type="button" class="hapus no-print" title="Hapus berkas">✕</button>' : '')
                    . '</li>';
            }
            $h .= '</ul>';
        } else {
            $h .= '<ul class="berkas"></ul>';
        }

        $h .= '</div></div>';
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
        $h .= '<table class="w"><thead><tr>';
        foreach ($cols as $c => $label) {
            $w = $def['widths'][$c] ?? null;
            $judul = Assessment::headerLabel($code, $c, $assessmentId);
            $editableHeader = in_array($c, $def['header_editable'] ?? [], true);
            $h .= '<th' . ($w ? ' style="width:' . $w . '%"' : '') . '>';
            $h .= $editableHeader ? self::sel($code, 0, $c, $judul, $label) : e($judul);
            $h .= '</th>';
        }
        if (!empty($def['upload'])) {
            $h .= '<th style="width:26%">Dokumen Pendukung</th>';
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
                $h .= '<td>' . self::kendali([
                    'owner_type'    => 'row',
                    'owner_key'     => $code . ':' . $r['row_no'],
                    'documents'     => $r['documents'],
                    'folder'        => $r['folder'],
                    'status_tampil' => false,
                    'ket_tampil'    => false,
                ]) . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';
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
        $h .= '<table class="w"><thead><tr>';
        foreach ($cols as $c => $label) {
            $w = $def['widths'][$c] ?? null;
            $h .= '<th' . ($w ? ' style="width:' . $w . '%"' : '') . '>' . e(Assessment::headerLabel($code, $c, $assessmentId)) . '</th>';
        }
        if (!empty($def['upload'])) {
            $h .= '<th style="width:26%">Dokumen Pendukung</th>';
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
        $h .= '</tbody></table>';
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

            $h .= '<tr class="' . $kelas . '">';
            $h .= '<td class="no">' . ($level === 0 ? e($n['label']) : '') . '</td>';
            $h .= '<td class="uraian' . $ind . '">';
            if ($level > 0) {
                $h .= '<span class="lbl">' . e($n['label']) . '</span>';
            }
            $h .= e($n['title']) . '</td>';

            $h .= '<td class="hasil">';
            if ($cetak) {
                $h .= self::berkasCetak($n['documents'], $n['folder'], self::statusTeks($n) . (string) $n['keterangan']);
            } else {
                $h .= self::kendali([
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
