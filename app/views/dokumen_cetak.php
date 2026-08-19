<?php
/** @var array $dok */
$def = $dok['def'];
$judul = 'Cetak ' . $dok['nomor'] . ' — ' . $CFG['app']['name'];
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul"><?= e($def['nama']) ?> — <?= e($dok['nomor']) ?></div>
    <a class="btn" href="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>">✏️ Ubah</a>
    <a class="btn" href="?p=dokumen_unduh&amp;id=<?= (int) $dok['id'] ?>">📄 Unduh Word</a>
    <button class="btn utama" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</div>

<div class="pesan info no-print">
    Pada jendela cetak pilih ukuran kertas <b><?= e($def['kertas']) ?></b>, orientasi
    <b>Potrait</b>, dan matikan “Headers and footers” agar hasilnya bersih seperti naskah asli.
    Huruf naskah mengikuti ketentuan jenis ini: <b><?= e($def['huruf']) ?></b>.
</div>

<div class="kertas naskah <?= ($def['huruf'] ?? '') === 'Arial' ? 'huruf-arial' : '' ?>">
    <?= RenderNaskah::naskah($dok) ?>
</div>

<?php require __DIR__ . '/partials/foot_dokumen.php'; ?>
