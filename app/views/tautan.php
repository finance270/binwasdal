<?php
/** @var array $daftar */
/** @var array $assessment */
/** @var Storage $storage */
$judul = 'Daftar Tautan Folder — ' . $CFG['app']['name'];
$rootFolder = DB::one("SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = 'root'", [$assessment['id']]);
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Daftar Tautan Folder Dokumen</div>
    <button class="btn" id="salin-semua">📋 Salin semua tautan</button>
    <button class="btn utama" onclick="window.print()">🖨️ Cetak daftar</button>
</div>

<div class="kartu">
    <h2>Folder Induk Periode Ini</h2>
    <?php if ($rootFolder && $rootFolder['drive_link']): ?>
        <p>
            <a class="chip-folder" href="<?= e($rootFolder['drive_link']) ?>" target="_blank" rel="noopener">📂 <?= e($rootFolder['nama']) ?></a><br>
            <code style="font-size:12px;word-break:break-all"><?= e($rootFolder['drive_link']) ?></code>
        </p>
        <p style="font-size:12.5px;color:#59616d">
            Seluruh folder di dalamnya sudah dibagikan <b>“siapa saja yang memiliki link dapat melihat”</b>.
        </p>
    <?php elseif ($rootFolder): ?>
        <p><span class="chip-folder lokal">📁 <?= e($rootFolder['nama']) ?> — penyimpanan lokal, Google Drive belum aktif.</span></p>
    <?php else: ?>
        <p style="color:#8a929e">Folder belum dibuat. Unggah dokumen pertama atau tekan tombol di beranda.</p>
    <?php endif; ?>
</div>

<div class="kartu">
    <h2>Tautan per Poin (<?= count($daftar) ?> folder)</h2>
    <table class="rapi" id="tabel-tautan">
        <thead>
            <tr>
                <th style="width:130px">Bagian</th>
                <th style="width:70px">Kode</th>
                <th>Poin / Baris</th>
                <th style="width:70px">Berkas</th>
                <th style="width:330px">Tautan Folder</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$daftar): ?>
            <tr><td colspan="5" style="color:#8a929e">Belum ada folder. Folder dibuat otomatis saat dokumen pertama diunggah pada sebuah poin.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftar as $d): ?>
            <tr>
                <td style="font-size:12px"><?= e($d['bagian']) ?></td>
                <td><?= e($d['kode']) ?></td>
                <td><?= e($d['judul']) ?></td>
                <td style="text-align:center"><?= (int) $d['jumlah'] ?></td>
                <td>
                    <?php if ($d['link']): ?>
                        <a href="<?= e($d['link']) ?>" target="_blank" rel="noopener" style="font-size:12px;word-break:break-all"><?= e($d['link']) ?></a>
                    <?php else: ?>
                        <span style="color:#8a929e;font-size:12px">lokal: <?= e($d['nama']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('salin-semua').addEventListener('click', function () {
    var baris = [];
    document.querySelectorAll('#tabel-tautan tbody tr').forEach(function (tr) {
        var td = tr.querySelectorAll('td');
        if (td.length < 5) return;
        var a = td[4].querySelector('a');
        if (!a) return;
        baris.push(td[1].innerText.trim() + ' ' + td[2].innerText.trim() + ' — ' + a.href);
    });
    navigator.clipboard.writeText(baris.join('\n')).then(function () {
        alert(baris.length + ' tautan disalin ke papan klip.');
    }, function () {
        alert('Gagal menyalin. Salin manual dari tabel.');
    });
});
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
