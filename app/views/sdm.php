<?php
/** @var array $assessment */
$judul = 'SDM & Ketenagaan — ' . $CFG['app']['name'];
$aid = (int) $assessment['id'];
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Sumber Daya Manusia / Ketenagakerjaan RS</div>
    <a class="btn" href="?p=cetak&amp;bagian=sdm" target="_blank">🖨️ Cetak bagian ini</a>
</div>

<div class="pesan info no-print">
    Isi kolom <b>Jumlah</b>, <b>Purna Waktu</b>, dan <b>Paruh Waktu</b> sesuai kondisi rumah sakit. Tersimpan otomatis.
</div>

<div class="kertas">
    <?= Render::tabelForm('sdm_detail', $aid) ?>
    <?= Render::tabelForm('rekap_sdm', $aid) ?>

    <div style="margin-top:30px;display:flex;justify-content:flex-end">
        <div style="text-align:center;min-width:8cm">
            <div>
                <?= Render::selProfil('kota_ttd', $profil['kota_ttd'] ?? 'Jakarta', 'Jakarta') ?>,
                <?= Render::selProfil('tanggal_ttd', $profil['tanggal_ttd'] ?? '', 'tanggal') ?>
            </div>
            <div>Mengetahui,</div>
            <div>Kepala/Direktur Rumah Sakit</div>
            <div style="height:2.4cm;display:flex;align-items:center;justify-content:center;color:#888;font-size:10pt">
                (Materai dan Tanda Tangan)
            </div>
            <div style="font-weight:700;text-decoration:underline">
                <?= Render::selProfil('nama_direktur_ttd', $profil['nama_direktur_ttd'] ?? ($profil['nama_direktur_rs'] ?? ''), 'Nama Direktur Rumah Sakit') ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>
