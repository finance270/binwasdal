<?php /** @var array $CFG */ /** @var array $log */ /** @var ?string $galat */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pemasangan — <?= e($CFG['app']['name']) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=7">
</head>
<body style="background:#eef1f5">
<div style="max-width:720px;margin:50px auto;padding:0 16px">
    <div class="kartu">
        <h2>🏥 Pemasangan Aplikasi Self Assessment Binwasdal RS</h2>
        <p style="font-size:13.5px;line-height:1.7">
            Proses ini akan membuat tabel database dan mengisi seluruh struktur dokumen
            <b>Self Assessment Pembinaan dan Pengawasan Rumah Sakit</b> (63 poin utama dan 900+ sub-poin)
            sesuai dokumen Word resmi.
        </p>

        <table class="rapi" style="margin:14px 0">
            <tr><th style="width:180px">Host database</th><td><?= e($CFG['db']['host'] . ':' . $CFG['db']['port']) ?></td></tr>
            <tr><th>Nama database</th><td><?= e($CFG['db']['name']) ?></td></tr>
            <tr><th>Pengguna database</th><td><?= e($CFG['db']['user']) ?></td></tr>
            <tr><th>Folder unggahan</th><td><?= e($CFG['app']['upload_dir']) ?></td></tr>
            <tr><th>Folder induk Drive</th><td><?= e($CFG['drive']['root_folder_id']) ?></td></tr>
            <tr><th>Akun awal</th><td><?= e($CFG['auth']['default_user']) ?> / <?= e($CFG['auth']['default_pass']) ?></td></tr>
        </table>

        <?php if ($galat): ?>
            <div class="pesan galat"><b>Gagal:</b> <?= e($galat) ?></div>
            <p style="font-size:13px">
                Periksa variabel lingkungan <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>,
                <code>DB_PASS</code> pada container, lalu coba lagi.
            </p>
        <?php endif; ?>

        <?php if ($log): ?>
            <div class="pesan sukses"><?= implode('<br>', array_map('e', $log)) ?></div>
        <?php endif; ?>

        <form method="post">
            <button class="btn utama" style="padding:9px 18px">🚀 Mulai Pemasangan</button>
        </form>
    </div>
</div>
</body>
</html>
