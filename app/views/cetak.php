<?php
/** @var array $assessment */
/** @var string $bagian */
$judul = 'Cetak — ' . $assessment['nama_rs'] . ' ' . $assessment['tahun'];
$aid = (int) $assessment['id'];
$sections = Assessment::sections();
$fields = Assessment::profil($aid);
$extra = Installer::extraProfilFields();

$tampilProfil = in_array($bagian, ['all', 'profil'], true);
$tampilSdm    = in_array($bagian, ['all', 'sdm'], true);
$sectionIds   = [];
if ($bagian === 'all') {
    $sectionIds = array_column($sections, 'id');
} elseif (ctype_digit((string) $bagian)) {
    $sectionIds = [(int) $bagian];
}
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Pratinjau Cetak</div>
    <select class="f" style="width:280px" onchange="location.href='?p=cetak&bagian='+this.value">
        <option value="all" <?= $bagian === 'all' ? 'selected' : '' ?>>Seluruh dokumen</option>
        <option value="profil" <?= $bagian === 'profil' ? 'selected' : '' ?>>Data Dasar &amp; Profil RS</option>
        <?php foreach ($sections as $i => $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (string) $bagian === (string) $s['id'] ? 'selected' : '' ?>>
                <?= romawi($i + 1) ?>. <?= e($s['title']) ?>
            </option>
        <?php endforeach; ?>
        <option value="sdm" <?= $bagian === 'sdm' ? 'selected' : '' ?>>SDM &amp; Ketenagaan</option>
    </select>
    <button class="btn utama" onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
    <a class="btn" href="?p=beranda">← Kembali</a>
</div>

<div class="pesan info no-print">
    Untuk menyimpan sebagai PDF: tekan tombol di atas (atau <b>Ctrl + P</b>), lalu pilih tujuan
    <b>“Save as PDF” / “Microsoft Print to PDF”</b>. Ukuran kertas <b>A4</b>, margin <b>Default</b>,
    dan centang <b>Background graphics</b> agar arsiran tabel ikut tercetak.
</div>

<!-- ======================= HALAMAN JUDUL & DATA DASAR ======================= -->
<?php if ($tampilProfil): ?>
<div class="kertas">
    <div class="judul-dok">
        Dokumen Self Assessment Pembinaan dan Pengawasan<br>
        <span class="sub">Rumah Sakit di <?= e($assessment['wilayah']) ?> Tahun <?= (int) $assessment['tahun'] ?></span>
    </div>
    <hr class="garis-judul">

    <div class="sub-bab">Data Dasar Rumah Sakit</div>
    <table class="data-dasar">
        <?php foreach ($fields as $f): ?>
            <?php if (isset($extra[$f['k']])) {
                continue;
            } ?>
            <tr>
                <td class="label"><?= e($f['label']) ?></td>
                <td class="pemisah">:</td>
                <td><?= e($f['v']) !== '' ? e($f['v']) : '&nbsp;' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?= Render::tabelFormCetak('kompetensi', $aid) ?>

    <div class="sub-bab">Kapasitas Tempat Tidur</div>
    <table class="data-dasar">
        <tr>
            <td class="label">SK Tempat Tidur Rumah Sakit Nomor</td>
            <td class="pemisah">:</td>
            <td><?= e($profil['sk_tempat_tidur'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label">SK Tempat Tidur KRIS (Kelas Rawat Inap Standar) Nomor</td>
            <td class="pemisah">:</td>
            <td><?= e($profil['sk_kris'] ?? '') ?></td>
        </tr>
    </table>
    <?= Render::tabelFormCetak('tempat_tidur', $aid, true) ?>
    <?= Render::tabelFormCetak('perizinan', $aid) ?>
    <?= Render::tabelFormCetak('sarana', $aid) ?>
    <?= Render::tabelFormCetak('kinerja', $aid) ?>
    <?= Render::tabelFormCetak('penyakit_rj', $aid) ?>
    <?= Render::tabelFormCetak('penyakit_ri', $aid) ?>
</div>
<?php endif; ?>

<!-- ============================ BAGIAN PENILAIAN ============================ -->
<?php foreach ($sections as $i => $s): ?>
    <?php if (!in_array((int) $s['id'], array_map('intval', $sectionIds), true)) {
        continue;
    } ?>
    <?php $tree = Assessment::tree((int) $s['id'], $aid); ?>
    <div class="kertas">
        <div class="bab"><?= romawi($i + 1) ?>. <?= e($s['title']) ?></div>
        <?php if (!empty($s['subtitle'])): ?>
            <div class="sub-bab"><?= e($s['subtitle']) ?></div>
        <?php endif; ?>
        <table class="w">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th style="width:52%">Unit Pelayanan / Program</th>
                    <th>Hasil Self Assessment Rumah Sakit</th>
                </tr>
            </thead>
            <tbody><?= Render::barisPoin($tree, true) ?></tbody>
        </table>
    </div>
<?php endforeach; ?>

<!-- =============================== KETENAGAAN =============================== -->
<?php if ($tampilSdm): ?>
<div class="kertas">
    <?= Render::tabelFormCetak('sdm_detail', $aid) ?>
    <?= Render::tabelFormCetak('rekap_sdm', $aid) ?>

    <div style="margin-top:36px;display:flex;justify-content:flex-end">
        <div style="text-align:center;min-width:8cm">
            <div><?= e($profil['kota_ttd'] ?? 'Jakarta') ?>, <?= e($profil['tanggal_ttd'] ?? '') ?></div>
            <div>Mengetahui,</div>
            <div>Kepala/Direktur <?= e($assessment['nama_rs']) ?></div>
            <div style="height:2.6cm"></div>
            <div style="font-weight:700;text-decoration:underline">
                <?= e($profil['nama_direktur_ttd'] ?: ($profil['nama_direktur_rs'] ?? '')) ?>
            </div>
            <div style="font-size:9.5pt">(Materai dan Tanda Tangan)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/foot.php'; ?>
