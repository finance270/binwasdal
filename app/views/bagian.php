<?php
/** @var array $sec */
/** @var array $tree */
/** @var array $assessment */
$judul = $sec['title'] . ' — ' . $CFG['app']['name'];
$sections = Assessment::sections();
$idx = 0;
foreach ($sections as $i => $s) {
    if ((int) $s['id'] === (int) $sec['id']) {
        $idx = $i;
    }
}
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul"><?= romawi($idx + 1) ?>. <?= e($sec['title']) ?></div>
    <input type="search" id="cari-poin" class="f" style="width:230px" placeholder="Cari poin…">
    <select id="saring-status" class="f" style="width:170px">
        <option value="">Semua poin</option>
        <option value="belum">Belum diisi</option>
        <option value="ada">Ada / Sesuai</option>
        <option value="sebagian">Sebagian</option>
        <option value="tidak_ada">Belum Ada</option>
        <option value="na">Tidak Berlaku</option>
        <option value="ada_berkas">Sudah ada berkas</option>
    </select>
    <a class="btn" href="?p=cetak&amp;bagian=<?= (int) $sec['id'] ?>" target="_blank">🖨️ Cetak bagian ini</a>
    <a class="btn" href="?p=unduh&amp;bagian=<?= (int) $sec['id'] ?>">📄 Word</a>
</div>

<div class="pesan info no-print" style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
    <span><b>Cara pakai:</b> klik kolom <b>Hasil Self Assessment</b> pada baris mana pun untuk membuka jendela pengisian.</span>
    <span>Di jendela itu Anda memilih status, menulis keterangan, dan mengunggah beberapa dokumen sekaligus.</span>
    <span>Folder Google Drive poin tersebut dibuat otomatis pada unggahan pertama.</span>
</div>

<div class="kertas">
    <div class="bab"><?= romawi($idx + 1) ?>. <?= e($sec['title']) ?></div>
    <?php if (!empty($sec['subtitle'])): ?>
        <div class="sub-bab"><?= e($sec['subtitle']) ?></div>
    <?php endif; ?>

    <div class="tabel-gulir">
    <table class="w poin-tabel">
        <thead>
            <tr>
                <th style="width:46px">No</th>
                <th style="width:47%">Unit Pelayanan / Program</th>
                <th>Hasil Self Assessment Rumah Sakit</th>
            </tr>
        </thead>
        <tbody>
            <?= Render::barisPoin($tree) ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>
