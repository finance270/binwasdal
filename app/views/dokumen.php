<?php
/** @var array $daftar */
/** @var array $ringkasan */
/** @var array $saring */
/** @var array $tahun */
/** @var Storage $storage */
$judul = 'Dokumen Internal — ' . $CFG['app']['name'];
$jenisTerpilih = $saring['jenis'] !== '' ? Naskah::get($saring['jenis']) : null;
$semuaJenis = Naskah::jenis();
$semuaBagian = Naskah::bagian();
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">
        <?= $jenisTerpilih ? e($jenisTerpilih['nama']) : 'Daftar Dokumen Internal' ?>
    </div>
    <a class="btn" href="?p=dokumen_induk">📑 Daftar Induk</a>
    <a class="btn" href="?p=dokumen_tata">📘 Pedoman Tata Naskah</a>
</div>

<?php if (!$storage->driveAktif()): ?>
    <div class="pesan galat no-print">
        <b>Google Drive belum aktif.</b> Hasil scan untuk sementara disimpan di server
        (folder <code>data/uploads</code>). Buka <a href="?p=pengaturan">Pengaturan → Google Drive</a>
        agar tautan folder Drive terbentuk otomatis.
    </div>
<?php endif; ?>

<div class="grid k4 no-print" style="max-width:21cm;margin:0 auto 16px">
    <div class="stat">
        <div class="angka"><?= (int) $ringkasan['total'] ?></div>
        <div class="ket">Seluruh naskah</div>
    </div>
    <div class="stat">
        <div class="angka"><?= (int) ($ringkasan['per_status']['disahkan'] ?? 0) ?></div>
        <div class="ket">Disahkan / berlaku</div>
    </div>
    <div class="stat">
        <div class="angka"><?= (int) ($ringkasan['per_status']['draft'] ?? 0) + (int) ($ringkasan['per_status']['diperiksa'] ?? 0) ?></div>
        <div class="ket">Masih draf / diperiksa</div>
    </div>
    <div class="stat">
        <div class="angka"><?= (int) $ringkasan['berkas'] ?></div>
        <div class="ket">Berkas scan tersimpan</div>
    </div>
</div>

<?php if ((int) $ringkasan['perlu_tinjau'] > 0): ?>
    <div class="pesan info no-print" style="max-width:21cm">
        <b><?= (int) $ringkasan['perlu_tinjau'] ?> dokumen</b> sudah melewati tanggal peninjauan.
        Naskah yang berlaku sebaiknya ditinjau ulang paling lama setiap 3 tahun.
    </div>
<?php endif; ?>

<!-- ------------------------------------------------------ buat naskah -->
<?php if (Auth::canEdit()): ?>
<div class="kartu no-print">
    <h2>Buat Naskah Baru</h2>
    <form method="post" action="?p=dokumen">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="dokumen_buat">
        <div class="dok-kepala-grid">
            <div>
                <label class="f" for="b-jenis">Jenis naskah</label>
                <select class="f" name="jenis" id="b-jenis" required>
                    <?php foreach (Naskah::kelompok() as $namaKelompok => $kodeJenis): ?>
                        <optgroup label="<?= e($namaKelompok) ?>">
                            <?php foreach ($kodeJenis as $kj): if (!isset($semuaJenis[$kj])) { continue; } ?>
                                <option value="<?= e($kj) ?>" <?= $saring['jenis'] === $kj ? 'selected' : '' ?>>
                                    <?= e($semuaJenis[$kj]['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="f" for="b-bagian">Bagian / unit penerbit</label>
                <select class="f" name="bagian" id="b-bagian">
                    <?php foreach ($semuaBagian as $kode => $nama): ?>
                        <option value="<?= e($kode) ?>" <?= $kode === 'DIR' ? 'selected' : '' ?>>
                            <?= e($kode) ?> — <?= e($nama) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="f" for="b-tahun">Tahun</label>
                <input class="f" type="number" name="tahun" id="b-tahun" value="<?= (int) date('Y') ?>" min="2000" max="2100">
            </div>
        </div>
        <label class="f" for="b-judul">Judul naskah (tanpa kata “tentang”)</label>
        <input class="f" type="text" name="judul" id="b-judul" maxlength="500"
               placeholder="Mis. Kebijakan Identifikasi Pasien" required>
        <p class="petunjuk" style="font-size:12px;color:#6b7481;margin:6px 0 10px">
            Nomor naskah dibentuk otomatis mengikuti rumus penomoran pada Pedoman Tata Naskah
            dan dihitung ulang setiap tahun.
        </p>
        <button class="btn utama" type="submit">➕ Buat &amp; Isi Naskah</button>
    </form>
</div>
<?php endif; ?>

<!-- ---------------------------------------------------------- penapis -->
<form method="get" class="dok-tapis no-print">
    <input type="hidden" name="p" value="dokumen">
    <div class="kolom">
        <label class="f" for="t-jenis">Jenis</label>
        <select class="f" name="jenis" id="t-jenis">
            <option value="">Semua jenis</option>
            <?php foreach ($semuaJenis as $kode => $j): ?>
                <option value="<?= e($kode) ?>" <?= $saring['jenis'] === $kode ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="kolom">
        <label class="f" for="t-bagian">Bagian</label>
        <select class="f" name="bagian" id="t-bagian">
            <option value="">Semua bagian</option>
            <?php foreach ($semuaBagian as $kode => $nama): ?>
                <option value="<?= e($kode) ?>" <?= $saring['bagian'] === $kode ? 'selected' : '' ?>><?= e($kode) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="kolom">
        <label class="f" for="t-status">Status</label>
        <select class="f" name="status" id="t-status">
            <option value="">Semua status</option>
            <?php foreach (Naskah::statusOptions() as $kode => $nama): ?>
                <option value="<?= e($kode) ?>" <?= $saring['status'] === $kode ? 'selected' : '' ?>><?= e($nama) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="kolom">
        <label class="f" for="t-tahun">Tahun</label>
        <select class="f" name="tahun" id="t-tahun">
            <option value="">Semua</option>
            <?php foreach ($tahun as $th): ?>
                <option value="<?= (int) $th ?>" <?= (string) $saring['tahun'] === (string) $th ? 'selected' : '' ?>><?= (int) $th ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="kolom lebar">
        <label class="f" for="t-cari">Cari nomor / judul</label>
        <input class="f" type="search" name="cari" id="t-cari" value="<?= e($saring['cari']) ?>" placeholder="mis. identifikasi pasien">
    </div>
    <div class="kolom" style="flex:0 0 auto">
        <button class="btn utama" type="submit">🔍 Tampilkan</button>
    </div>
</form>

<!-- ----------------------------------------------------------- daftar -->
<div class="kartu">
    <h2>
        <?= count($daftar) ?> naskah ditemukan
        <?= $saring['cari'] !== '' ? '— pencarian “' . e($saring['cari']) . '”' : '' ?>
    </h2>

    <?php if (!$daftar): ?>
        <p style="color:#6b7481">
            Belum ada naskah yang sesuai. Buat naskah baru lewat formulir di atas.
        </p>
    <?php else: ?>
        <table class="rapi daftar-naskah">
            <thead>
                <tr>
                    <th style="width:32%">Nomor &amp; Judul</th>
                    <th style="width:13%">Jenis</th>
                    <th style="width:7%">Bagian</th>
                    <th style="width:13%">Status</th>
                    <th style="width:6%">Rev.</th>
                    <th style="width:6%">Scan</th>
                    <th style="width:23%">Tindakan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $d): $j = Naskah::get($d['jenis']); ?>
                <tr class="<?= $d['status'] === 'dicabut' ? 'dicabut' : '' ?>">
                    <td>
                        <div class="nomor-dok"><?= e($d['nomor']) ?></div>
                        <div class="judul-dok-kecil"><?= e($d['judul']) ?></div>
                        <div class="meta">
                            Terbit <?= e(tanggalIndo($d['tanggal_terbit'])) ?>
                            <?php if ($d['tanggal_tinjau']): ?>
                                · Tinjau <?= e(tanggalIndo($d['tanggal_tinjau'])) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-l="Jenis"><?= e($j['nama'] ?? $d['jenis']) ?></td>
                    <td data-l="Bagian"><?= e($d['bagian']) ?></td>
                    <td data-l="Status">
                        <span class="badge s-<?= e($d['status']) ?>"><?= e(Naskah::statusLabel($d['status'])) ?></span>
                        <?php if ($d['status'] !== 'dicabut'): ?>
                            <div class="meta"><?= e(Naskah::klasifikasiOptions()[$d['klasifikasi']] ?? '') ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-l="Revisi"><?= (int) $d['revisi'] ?></td>
                    <td data-l="Berkas scan"><?= (int) $d['jml_berkas'] ?></td>
                    <td class="tindakan">
                        <a class="btn kecil" href="?p=dokumen_edit&amp;id=<?= (int) $d['id'] ?>">✏️ Ubah</a>
                        <a class="btn kecil" href="?p=dokumen_cetak&amp;id=<?= (int) $d['id'] ?>" target="_blank">🖨️ Cetak</a>
                        <a class="btn kecil" href="?p=dokumen_unduh&amp;id=<?= (int) $d['id'] ?>">📄 Word</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/foot_dokumen.php'; ?>
