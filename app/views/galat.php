<?php
$judul = 'Halaman tidak ditemukan';
require __DIR__ . '/partials/head.php';
?>
<div class="kartu">
    <h2>😕 <?= e($pesan ?? 'Terjadi kesalahan.') ?></h2>
    <p><a class="btn" href="?p=beranda">← Kembali ke beranda</a></p>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
