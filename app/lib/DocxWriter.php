<?php

/**
 * Penulis berkas .docx (Office Open XML) sederhana — tanpa pustaka luar.
 *
 * Cukup untuk kebutuhan dokumen Self Assessment: paragraf, judul, tabel
 * bergaris dengan lebar kolom tetap, teks tebal/miring, dan tautan.
 * Hasilnya berkas Word asli yang dapat dibuka dan diedit di Microsoft Word,
 * LibreOffice, maupun Google Docs.
 *
 * Ukuran memakai satuan twip (1/20 pt): A4 = 11906 x 16838.
 */
class DocxWriter
{
    /** Lebar area teks setelah dikurangi margin kiri-kanan. */
    public const LEBAR_ISI = 9638;

    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    private string $isi = '';
    private array $tautan = [];   // rId => URL
    private int $rid = 10;

    /** Huruf & ukuran bawaan seluruh dokumen (dapat diubah per naskah). */
    public string $hurufBawaan = 'Arial';
    public float $ukuranBawaan = 11;

    // -----------------------------------------------------------------
    // Paragraf
    // -----------------------------------------------------------------

    /**
     * @param array $o tebal, miring, ukuran (pt), rata (left|center|right|both),
     *                 spasiSebelum, spasiSesudah (twip), garisBawah, huruf
     */
    public function paragraf(string $teks, array $o = []): self
    {
        $this->isi .= $this->buatParagraf($teks, $o);
        return $this;
    }

    public function judul(string $teks, array $o = []): self
    {
        return $this->paragraf($teks, $o + [
            'tebal' => true, 'ukuran' => 13, 'rata' => 'center', 'spasiSesudah' => 60,
        ]);
    }

    public function bab(string $teks): self
    {
        $this->isi .= '<w:p><w:pPr>'
            . '<w:keepNext/>'
            . '<w:pBdr><w:bottom w:val="single" w:sz="12" w:space="2" w:color="000000"/></w:pBdr>'
            . '<w:spacing w:before="320" w:after="120" w:line="240" w:lineRule="auto"/>'
            . '</w:pPr>' . $this->run($teks, ['tebal' => true, 'ukuran' => 12]) . '</w:p>';
        return $this;
    }

    public function subBab(string $teks): self
    {
        return $this->paragraf($teks, ['tebal' => true, 'spasiSebelum' => 200, 'spasiSesudah' => 60]);
    }

    /** Garis mendatar selebar halaman (pemisah kop naskah). */
    public function garis(int $tebal = 12): self
    {
        $this->isi .= '<w:p><w:pPr>'
            . '<w:pBdr><w:bottom w:val="single" w:sz="' . $tebal . '" w:space="1" w:color="000000"/></w:pBdr>'
            . '<w:spacing w:before="0" w:after="120" w:line="240" w:lineRule="auto"/>'
            . '</w:pPr></w:p>';
        return $this;
    }

    public function kosong(int $tinggi = 1): self
    {
        for ($i = 0; $i < $tinggi; $i++) {
            $this->isi .= '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:p>';
        }
        return $this;
    }

    public function pindahHalaman(): self
    {
        $this->isi .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    // -----------------------------------------------------------------
    // Tabel
    // -----------------------------------------------------------------

    /**
     * @param int[]   $lebar   lebar tiap kolom dalam twip
     * @param array[] $baris   setiap baris = daftar sel; sel berupa string atau
     *                         ['teks'=>..,'tebal'=>..,'rata'=>..,'arsir'=>'DFE6EE',
     *                          'baris'=>[..] (beberapa paragraf), 'tautan'=>URL]
     * @param bool    $ulangHeader baris pertama diulang di tiap halaman
     */
    public function tabel(array $lebar, array $baris, bool $ulangHeader = true, bool $garis = true, bool $jagaUtuh = false): self
    {
        $total = array_sum($lebar);
        // Urutan elemen mengikuti skema OOXML (CT_TblPrBase):
        // tblW -> tblBorders -> tblLayout -> tblCellMar
        $t = '<w:tbl><w:tblPr>'
            . '<w:tblW w:w="' . $total . '" w:type="dxa"/>';
        if ($garis) {
            $t .= '<w:tblBorders>';
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $sisi) {
                $t .= '<w:' . $sisi . ' w:val="single" w:sz="6" w:space="0" w:color="000000"/>';
            }
            $t .= '</w:tblBorders>';
        }
        $t .= '<w:tblLayout w:type="fixed"/>';
        $t .= '<w:tblCellMar>'
            . '<w:top w:w="40" w:type="dxa"/><w:left w:w="80" w:type="dxa"/>'
            . '<w:bottom w:w="40" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($lebar as $w) {
            $t .= '<w:gridCol w:w="' . (int) $w . '"/>';
        }
        $t .= '</w:tblGrid>';

        foreach ($baris as $i => $sel) {
            $t .= '<w:tr>';
            if ($i === 0 && $ulangHeader) {
                $t .= '<w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>';
            } elseif ($jagaUtuh) {
                $t .= '<w:trPr><w:cantSplit/></w:trPr>';
            }
            // posisi kolom dihitung sendiri agar sel gabungan (kolom => n)
            // tetap mendapat lebar yang benar
            $kolomKe = 0;
            foreach (array_values($sel) as $isiSel) {
                $span = is_array($isiSel) ? max(1, (int) ($isiSel['kolom'] ?? 1)) : 1;
                $w = 0;
                for ($j = 0; $j < $span; $j++) {
                    $w += (int) ($lebar[$kolomKe + $j] ?? end($lebar));
                }
                $t .= $this->sel($isiSel, $w);
                $kolomKe += $span;
            }
            $t .= '</w:tr>';
        }
        $this->isi .= $t . '</w:tbl>';
        // paragraf kosong agar dua tabel berurutan tidak menyatu
        $this->isi .= '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/>'
            . '<w:rPr><w:sz w:val="10"/></w:rPr></w:pPr></w:p>';
        return $this;
    }

    private function sel($isiSel, int $lebar): string
    {
        $o = is_array($isiSel) ? $isiSel : ['teks' => (string) $isiSel];
        // Urutan elemen mengikuti skema OOXML (CT_TcPr):
        // tcW -> gridSpan -> vMerge -> shd -> vAlign
        $s = '<w:tc><w:tcPr><w:tcW w:w="' . $lebar . '" w:type="dxa"/>';
        if (!empty($o['kolom']) && (int) $o['kolom'] > 1) {
            $s .= '<w:gridSpan w:val="' . (int) $o['kolom'] . '"/>';
        }
        if (!empty($o['gabung'])) {
            $s .= '<w:vMerge' . ($o['gabung'] === 'mulai' ? ' w:val="restart"' : '') . '/>';
        }
        if (!empty($o['arsir'])) {
            $s .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $o['arsir'] . '"/>';
        }
        $s .= '<w:vAlign w:val="' . ($o['vRata'] ?? 'top') . '"/></w:tcPr>';

        $paragrafs = $o['baris'] ?? [$o];
        $adaIsi = false;
        foreach ($paragrafs as $p) {
            $p = is_array($p) ? $p : ['teks' => (string) $p];
            $p += ['ukuran' => $o['ukuran'] ?? 10, 'tebal' => $o['tebal'] ?? false,
                   'rata' => $o['rata'] ?? 'left', 'spasiSesudah' => 0];
            $teks = (string) ($p['teks'] ?? '');
            if ($teks === '' && empty($p['tautan']) && empty($p['runs'])) {
                continue;
            }
            $s .= $this->buatParagraf($teks, $p);
            $adaIsi = true;
        }
        if (!$adaIsi) {
            $s .= '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:p>';
        }
        return $s . '</w:tc>';
    }

    // -----------------------------------------------------------------
    // Bagian dalam
    // -----------------------------------------------------------------

    private function buatParagraf(string $teks, array $o): string
    {
        // Urutan elemen mengikuti skema OOXML (CT_PPrBase):
        // spacing -> ind -> jc
        $p = '<w:p><w:pPr><w:spacing'
            . ' w:before="' . (int) ($o['spasiSebelum'] ?? 0) . '"'
            . ' w:after="' . (int) ($o['spasiSesudah'] ?? 40) . '"'
            . ' w:line="240" w:lineRule="auto"/>';
        if (!empty($o['indentKiri']) || !empty($o['gantung'])) {
            // "gantung" membuat baris kedua dan seterusnya sejajar di bawah teks,
            // bukan di bawah nomor — seperti daftar bernomor pada naskah dinas.
            $p .= '<w:ind w:left="' . (int) ($o['indentKiri'] ?? 0) . '"'
                . (!empty($o['gantung']) ? ' w:hanging="' . (int) $o['gantung'] . '"' : '') . '/>';
        }
        if (!empty($o['rata']) && $o['rata'] !== 'left') {
            $p .= '<w:jc w:val="' . $o['rata'] . '"/>';
        }
        $p .= '</w:pPr>';

        if (!empty($o['runs'])) {
            // beberapa potongan teks dengan gaya berbeda dalam satu paragraf
            foreach ($o['runs'] as $bagian) {
                $bagian = is_array($bagian) ? $bagian : ['teks' => (string) $bagian];
                $bagian += ['ukuran' => $o['ukuran'] ?? 11];
                if (!empty($bagian['tautan'])) {
                    $p .= $this->runTautan((string) ($bagian['teks'] ?? $bagian['tautan']), $bagian['tautan'], $bagian);
                } else {
                    $p .= $this->run((string) ($bagian['teks'] ?? ''), $bagian);
                }
            }
        } elseif (!empty($o['tautan'])) {
            $p .= $this->runTautan($teks !== '' ? $teks : $o['tautan'], $o['tautan'], $o);
        } else {
            // dukung baris baru di dalam satu paragraf
            $bagian = preg_split("/\r\n|\n|\r/", $teks);
            foreach ($bagian as $i => $b) {
                if ($i > 0) {
                    $p .= '<w:r><w:br/></w:r>';
                }
                $p .= $this->run($b, $o);
            }
        }
        return $p . '</w:p>';
    }

    private function run(string $teks, array $o = []): string
    {
        $r = '<w:r>' . $this->rpr($o) . '<w:t xml:space="preserve">' . $this->esc($teks) . '</w:t></w:r>';
        return $r;
    }

    private function runTautan(string $teks, string $url, array $o = []): string
    {
        $id = 'rId' . (++$this->rid);
        $this->tautan[$id] = $url;
        $o['warna'] = '1A4F8A';
        $o['garisBawah'] = true;
        return '<w:hyperlink r:id="' . $id . '">' . $this->run($teks, $o) . '</w:hyperlink>';
    }

    private function rpr(array $o): string
    {
        // Urutan elemen mengikuti skema OOXML (CT_RPr):
        // rFonts -> b -> i -> color -> sz -> szCs -> u
        $r = '<w:rPr>';
        if (!empty($o['huruf'])) {
            $r .= '<w:rFonts w:ascii="' . $this->esc($o['huruf']) . '" w:hAnsi="' . $this->esc($o['huruf']) . '"/>';
        }
        if (!empty($o['tebal'])) {
            $r .= '<w:b/>';
        }
        if (!empty($o['miring'])) {
            $r .= '<w:i/>';
        }
        if (!empty($o['warna'])) {
            $r .= '<w:color w:val="' . $o['warna'] . '"/>';
        }
        $uk = (int) round(((float) ($o['ukuran'] ?? 11)) * 2);
        $r .= '<w:sz w:val="' . $uk . '"/><w:szCs w:val="' . $uk . '"/>';
        if (!empty($o['garisBawah'])) {
            $r .= '<w:u w:val="single"/>';
        }
        return $r . '</w:rPr>';
    }

    private function esc(string $s): string
    {
        $s = str_replace(["\x00", "\x0B", "\x0C"], '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    // -----------------------------------------------------------------
    // Keluaran
    // -----------------------------------------------------------------

    /** Menyusun seluruh berkas .docx dan mengembalikannya sebagai string biner. */
    public function keluarkan(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas Word sementara.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsUtama());
        $zip->addFromString('word/document.xml', $this->dokumen());
        $zip->addFromString('word/styles.xml', $this->gaya());
        $zip->addFromString('word/_rels/document.xml.rels', $this->relsDokumen());
        $zip->close();

        $isi = file_get_contents($tmp);
        @unlink($tmp);
        return $isi;
    }

    private function dokumen(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document ' . self::NS . '><w:body>'
            . $this->isi
            . '<w:sectPr>'
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"'
            . ' w:header="708" w:footer="708" w:gutter="0"/>'
            . '<w:cols w:space="708"/>'
            . '</w:sectPr></w:body></w:document>';
    }

    private function gaya(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles ' . self::NS . '>'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="' . $this->esc($this->hurufBawaan) . '" w:hAnsi="' . $this->esc($this->hurufBawaan)
            . '" w:eastAsia="' . $this->esc($this->hurufBawaan) . '" w:cs="' . $this->esc($this->hurufBawaan) . '"/>'
            . '<w:sz w:val="' . (int) round($this->ukuranBawaan * 2) . '"/>'
            . '<w:szCs w:val="' . (int) round($this->ukuranBawaan * 2) . '"/><w:lang w:val="id-ID"/>'
            . '</w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="40" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="character" w:styleId="Hyperlink"><w:name w:val="Hyperlink"/>'
            . '<w:rPr><w:color w:val="1A4F8A"/><w:u w:val="single"/></w:rPr></w:style>'
            . '</w:styles>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function relsUtama(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function relsDokumen(): string
    {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->tautan as $id => $url) {
            $r .= '<Relationship Id="' . $id . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"'
                . ' Target="' . $this->esc($url) . '" TargetMode="External"/>';
        }
        return $r . '</Relationships>';
    }
}
