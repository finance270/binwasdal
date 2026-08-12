<?php
/** @var array $fields */
/** @var array $tables */
/** @var array $assessment */
$judul = 'Data Dasar & Profil RS — ' . $CFG['app']['name'];
$aid = (int) $assessment['id'];
$extra = Installer::extraProfilFields();
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Data Dasar &amp; Profil Rumah Sakit</div>
    <a class="btn" href="?p=cetak&amp;bagian=profil" target="_blank">🖨️ Cetak bagian ini</a>
</div>

<div class="pesan info no-print">
    Klik langsung pada kolom isian untuk mengubah — tersimpan otomatis.
    Pada tabel <b>Legalitas Perizinan</b> dan <b>Sarana Prasarana</b>, klik kolom
    <b>Berkas Pendukung</b> untuk membuka jendela unggah dokumen.
</div>

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
                <td><?= Render::selProfil($f['k'], $f['v']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?= Render::tabelForm('kompetensi', $aid) ?>

    <div class="sub-bab">Kapasitas Tempat Tidur</div>
    <table class="data-dasar">
        <tr>
            <td class="label">SK Tempat Tidur Rumah Sakit Nomor</td>
            <td class="pemisah">:</td>
            <td><?= Render::selProfil('sk_tempat_tidur', $profil['sk_tempat_tidur'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label">SK Tempat Tidur KRIS (Kelas Rawat Inap Standar) Nomor</td>
            <td class="pemisah">:</td>
            <td><?= Render::selProfil('sk_kris', $profil['sk_kris'] ?? '') ?></td>
        </tr>
    </table>
    <?= Render::tabelForm('tempat_tidur', $aid, true) ?>

    <?= Render::tabelForm('perizinan', $aid) ?>
    <?= Render::tabelForm('sarana', $aid) ?>
    <?= Render::tabelForm('kinerja', $aid) ?>
    <?= Render::tabelForm('penyakit_rj', $aid) ?>
    <?= Render::tabelForm('penyakit_ri', $aid) ?>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>
