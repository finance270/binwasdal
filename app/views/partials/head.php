<?php
/** @var array $CFG */
/** @var array $assessment */
$sections = Assessment::sections();
$pesanKilat = flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#123a68">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="csrf" content="<?= e(Auth::csrf()) ?>">
<meta name="unggah-maks" content="<?= batasUnggah($CFG) ?>">
<meta name="unggah-ext" content="<?= e(implode(',', $CFG['app']['allowed_ext'])) ?>">
<title><?= e($judul ?? $CFG['app']['name']) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= asetVersi('assets/css/app.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>🏥</text></svg>">
</head>
<body>

<div class="topbar no-print">
    <button type="button" class="tombol-menu" id="tombol-menu" aria-label="Buka menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

    <div class="merek">
        <span class="merek-panjang">Self Assessment Binwasdal RS</span>
        <span class="merek-pendek">Binwasdal RS</span>
        <small>Pembinaan &amp; Pengawasan Rumah Sakit — Jakarta Pusat</small>
    </div>
    <div class="spacer"></div>

    <form method="post" class="periode">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="pilih_periode">
        <input type="hidden" name="kembali" value="<?= e($page ?? 'beranda') ?>">
        <span>Periode</span>
        <select name="assessment_id" onchange="this.form.submit()">
            <?php foreach (Assessment::listAll() as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $a['id'] == $assessment['id'] ? 'selected' : '' ?>>
                    <?= e($a['nama_rs']) ?> — <?= (int) $a['tahun'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <span class="akun"><?= e(Auth::user()['nama'] ?: Auth::username()) ?> (<?= e(Auth::user()['role']) ?>)</span>
    <a class="keluar" href="?p=logout">Keluar</a>
</div>

<div class="sidebar-latar no-print" id="sidebar-latar"></div>

<div class="sidebar no-print" id="sidebar">
    <div class="grup">Dokumen</div>
    <a href="?p=beranda" class="<?= ($page ?? '') === 'beranda' ? 'aktif' : '' ?>">🏠 Beranda &amp; Rekapitulasi</a>
    <a href="?p=profil" class="<?= ($page ?? '') === 'profil' ? 'aktif' : '' ?>">
        📋 Data Dasar &amp; Profil RS
        <span class="mini">Perizinan, tempat tidur, kinerja, 10 penyakit</span>
    </a>

    <div class="grup">Penilaian Mandiri</div>
    <?php foreach ($sections as $i => $s): ?>
        <a href="?p=bagian&amp;id=<?= (int) $s['id'] ?>"
           class="<?= (($page ?? '') === 'bagian' && (int) ($_GET['id'] ?? 0) === (int) $s['id']) ? 'aktif' : '' ?>">
            <?= romawi($i + 1) ?>. <?= e($s['title']) ?>
        </a>
    <?php endforeach; ?>

    <div class="grup">Ketenagaan</div>
    <a href="?p=sdm" class="<?= ($page ?? '') === 'sdm' ? 'aktif' : '' ?>">👥 SDM &amp; Rekapitulasi Tenaga</a>

    <div class="grup">Keluaran</div>
    <a href="?p=cetak" class="<?= ($page ?? '') === 'cetak' ? 'aktif' : '' ?>">🖨️ Cetak / Simpan PDF</a>
    <a href="?p=unduh&amp;bagian=all">📄 Unduh Word (.docx)</a>
    <a href="?p=tautan" class="<?= ($page ?? '') === 'tautan' ? 'aktif' : '' ?>">🔗 Daftar Tautan Folder</a>
    <a href="?p=pengaturan" class="<?= ($page ?? '') === 'pengaturan' ? 'aktif' : '' ?>">⚙️ Pengaturan</a>

    <?php $__v = appInfo(); $__t = waktuBerkasProgram(); ?>
    <div class="versi-app" title="Waktu berkas program di server — berubah bila unggahan manual berhasil">
        Versi <b><?= e($__v['versi']) ?></b><br>
        berkas server: <?= $__t ? e(date('d/m/Y H:i', $__t)) : '—' ?>
    </div>
</div>

<div class="konten">
<?php if ($pesanKilat): ?>
    <div class="pesan <?= e($pesanKilat['tipe']) ?> no-print"><?= e($pesanKilat['pesan']) ?></div>
<?php endif; ?>
