<?php
/** @var array $ringkasan */
/** @var array $assessment */
/** @var Storage $storage */
$judul = 'Beranda — ' . $CFG['app']['name'];
$driveAktif = $storage->driveAktif();
$rootFolder = DB::one("SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = 'root'", [$assessment['id']]);
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Beranda &amp; Rekapitulasi</div>
    <a class="btn utama" href="?p=cetak" target="_blank">🖨️ Cetak / Simpan PDF</a>
    <a class="btn" href="?p=unduh&amp;bagian=all">📄 Unduh Word (.docx)</a>
</div>

<?php if (!$driveAktif): ?>
    <div class="pesan galat no-print">
        <b>Google Drive belum aktif.</b> Berkas untuk sementara disimpan di server (folder <code>data/uploads</code>).
        Buka <a href="?p=pengaturan">Pengaturan → Google Drive</a> untuk menghubungkan akun agar tautan folder Drive otomatis terbentuk.
    </div>
<?php endif; ?>

<div class="kartu">
    <h2><?= e($assessment['nama_rs']) ?> — Tahun <?= (int) $assessment['tahun'] ?></h2>
    <div class="grid k4">
        <div class="stat">
            <div class="angka"><?= (int) $ringkasan['persen'] ?>%</div>
            <div class="ket">Kelengkapan pengisian</div>
            <div class="bar"><i style="width:<?= (int) $ringkasan['persen'] ?>%"></i></div>
        </div>
        <div class="stat">
            <div class="angka"><?= number_format($ringkasan['terisi'], 0, ',', '.') ?></div>
            <div class="ket">Poin terisi dari <?= number_format($ringkasan['total'], 0, ',', '.') ?> poin</div>
        </div>
        <div class="stat">
            <div class="angka"><?= number_format($ringkasan['dokumen'], 0, ',', '.') ?></div>
            <div class="ket">Dokumen terunggah</div>
        </div>
        <div class="stat">
            <div class="angka"><?= number_format($ringkasan['folder'], 0, ',', '.') ?></div>
            <div class="ket">Folder <?= $driveAktif ? 'Google Drive' : 'lokal' ?> terbentuk</div>
        </div>
    </div>

    <?php if ($rootFolder): ?>
        <p style="margin-top:14px">
            <b>Folder utama periode ini:</b><br>
            <?php if ($rootFolder['drive_link']): ?>
                <a class="chip-folder" href="<?= e($rootFolder['drive_link']) ?>" target="_blank" rel="noopener">
                    📂 <?= e($rootFolder['nama']) ?>
                </a>
                <span style="font-size:12px;color:#6b7481">— dapat diakses siapa saja yang memiliki tautan.</span>
            <?php else: ?>
                <span class="chip-folder lokal">📁 <?= e($rootFolder['nama']) ?> (penyimpanan lokal)</span>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <form method="post" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="siapkan_folder">
            <button class="btn utama">📂 Siapkan folder induk periode ini</button>
            <span style="font-size:12px;color:#6b7481">Folder per poin dibuat otomatis saat unggah pertama.</span>
        </form>
    <?php endif; ?>
</div>

<div class="kartu">
    <h2>Kemajuan per Bagian</h2>
    <table class="rapi">
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Bagian</th>
                <th style="width:110px">Poin terisi</th>
                <th style="width:100px">Dokumen</th>
                <th style="width:190px">Kemajuan</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ringkasan['sections'] as $i => $s): ?>
                <tr>
                    <td><?= romawi($i + 1) ?></td>
                    <td><a href="?p=bagian&amp;id=<?= (int) $s['id'] ?>"><?= e($s['title']) ?></a></td>
                    <td><?= (int) $s['terisi'] ?> / <?= (int) $s['total'] ?></td>
                    <td><?= (int) $s['berkas'] ?> poin</td>
                    <td>
                        <div class="bar"><i style="width:<?= (int) $s['persen'] ?>%"></i></div>
                        <span style="font-size:11.5px;color:#6b7481"><?= (int) $s['persen'] ?>%</span>
                    </td>
                    <td><a class="btn kecil" href="?p=bagian&amp;id=<?= (int) $s['id'] ?>">Isi →</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="kartu">
    <h2>Alur Pengisian</h2>
    <ol style="line-height:1.9;font-size:13.5px;padding-left:20px;margin:0">
        <li>Lengkapi <a href="?p=profil">Data Dasar &amp; Profil RS</a> — termasuk unggah dokumen perizinan.</li>
        <li>Isi seluruh bagian penilaian mandiri (menu di sisi kiri). Setiap poin dapat diberi keterangan dan dokumen pendukung.</li>
        <li>Lengkapi <a href="?p=sdm">SDM &amp; Ketenagaan</a> serta nama penandatangan dokumen.</li>
        <li>Buka <a href="?p=cetak" target="_blank">Cetak / Simpan PDF</a> untuk menghasilkan dokumen akhir
            (gunakan <b>Ctrl+P → Save as PDF</b>).</li>
    </ol>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>
