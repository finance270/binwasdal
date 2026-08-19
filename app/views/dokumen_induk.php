<?php
/** @var array $daftar */
/** @var array $saring */
/** @var array $tahun */
$judul = 'Daftar Induk Dokumen — ' . $CFG['app']['name'];
$ident = RenderNaskah::identitas();

// dikelompokkan menurut jenis, mengikuti urutan tingkatan regulasi
$perJenis = [];
foreach ($daftar as $d) {
    $perJenis[$d['jenis']][] = $d;
}
$urutJenis = array_keys(Naskah::jenis());
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Daftar Induk Dokumen</div>
    <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="p" value="dokumen_induk">
        <select class="f" name="tahun" style="width:auto" onchange="this.form.submit()">
            <option value="">Semua tahun</option>
            <?php foreach ($tahun as $th): ?>
                <option value="<?= (int) $th ?>" <?= (string) $saring['tahun'] === (string) $th ? 'selected' : '' ?>><?= (int) $th ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <button class="btn utama" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</div>

<div class="pesan info no-print">
    Daftar induk adalah rekaman pengendalian dokumen: seluruh naskah yang pernah diterbitkan
    beserta status keberlakuannya. Naskah bertanda <b>Tidak Berlaku</b> tetap ditampilkan agar
    riwayat revisi dapat ditelusuri saat penilaian akreditasi.
</div>

<div class="kertas">
    <div class="judul-dok">
        Daftar Induk Dokumen Internal<br>
        <span class="sub"><?= e($ident['nama']) ?></span>
    </div>
    <p style="text-align:center;margin:0 0 4px">
        <?= $saring['tahun'] !== '' ? 'Tahun ' . (int) $saring['tahun'] : 'Seluruh Tahun Terbit' ?>
        &nbsp;·&nbsp; Dicetak <?= e(tanggalIndo(date('Y-m-d'))) ?>
    </p>
    <hr class="garis-judul">

    <?php if (!$daftar): ?>
        <p>Belum ada dokumen internal yang tercatat.</p>
    <?php endif; ?>

    <?php foreach ($urutJenis as $kodeJenis):
        if (empty($perJenis[$kodeJenis])) { continue; }
        $j = Naskah::get($kodeJenis); ?>

        <div class="bab"><?= e($j['nama']) ?></div>
        <p style="font-size:9.5pt;margin:-4px 0 8px;color:#333">
            Rumus penomoran: <code><?= e($j['format']) ?></code>
            <?php if ($j['level'] > 0): ?> · Tingkat regulasi <?= (int) $j['level'] ?><?php endif; ?>
            · Huruf <?= e($j['huruf']) ?>
        </p>

        <table class="w">
            <thead>
                <tr>
                    <th style="width:6%">No.</th>
                    <th style="width:18%">Nomor Dokumen</th>
                    <th style="width:29%">Judul</th>
                    <th style="width:10%">Bagian</th>
                    <th style="width:7%">Rev.</th>
                    <th style="width:14%">Terbit</th>
                    <th style="width:16%">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($perJenis[$kodeJenis] as $i => $d): ?>
                <tr>
                    <td class="tengah"><?= $i + 1 ?></td>
                    <td><?= e($d['nomor']) ?></td>
                    <td><?= e($d['judul']) ?></td>
                    <td class="tengah"><?= e($d['bagian']) ?></td>
                    <td class="tengah"><?= (int) $d['revisi'] ?></td>
                    <td class="tengah-bungkus"><?= e(tanggalIndo($d['tanggal_terbit'])) ?></td>
                    <td>
                        <?= e(Naskah::statusLabel($d['status'])) ?>
                        <?php if ($d['status'] !== 'dicabut'): ?>
                            <br><span style="font-size:8.5pt"><?= e(Naskah::klasifikasiOptions()[$d['klasifikasi']] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <table class="data-dasar" style="margin-top:26px">
        <tr>
            <td class="label">&nbsp;</td>
            <td class="pemisah"></td>
            <td>
                <?= e($ident['kota']) ?>, <?= e(tanggalIndo(date('Y-m-d'))) ?><br>
                <?= e($ident['jabatan']) ?> <?= e($ident['nama']) ?>,
                <div style="height:2cm"></div>
                <b style="text-decoration:underline"><?= e($ident['direktur'] !== '' ? $ident['direktur'] : '(………………………………)') ?></b>
            </td>
        </tr>
    </table>
</div>

<?php require __DIR__ . '/partials/foot_dokumen.php'; ?>
