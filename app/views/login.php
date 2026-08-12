<?php /** @var array $CFG */ /** @var ?string $galat */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — <?= e($CFG['app']['name']) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=3">
</head>
<body>
<div class="login-wrap">
    <form class="login-box" method="post">
        <h1>🏥 Self Assessment Binwasdal RS</h1>
        <p class="sub">Pembinaan dan Pengawasan Rumah Sakit<br>Wilayah Kota Administrasi Jakarta Pusat</p>

        <?php if ($galat): ?>
            <div class="pesan galat" style="margin-bottom:12px"><?= e($galat) ?></div>
        <?php endif; ?>

        <label class="f">Nama Pengguna</label>
        <input class="f" name="username" autofocus required>

        <label class="f">Kata Sandi</label>
        <input class="f" type="password" name="password" required>

        <button class="btn utama" style="width:100%;justify-content:center;margin-top:16px;padding:9px">Masuk</button>
    </form>
</div>
</body>
</html>
