<?php
/** @var array $dok */
/** @var array $riwayat */
/** @var array $distribusi */
/** @var array|null $folder */
/** @var Storage $storage */
$def = $dok['def'];
$judul = $dok['nomor'] . ' — ' . $CFG['app']['name'];
$terkunci = $dok['status'] === 'dicabut' || !Auth::canEdit();
require __DIR__ . '/partials/head.php';
?>

<input type="hidden" id="dok-id" value="<?= (int) $dok['id'] ?>">

<div class="aksi-atas no-print">
    <div class="judul"><?= e($def['nama']) ?></div>
    <a class="btn" href="?p=dokumen&amp;jenis=<?= e($dok['jenis']) ?>">← Daftar</a>
    <a class="btn" href="?p=dokumen_cetak&amp;id=<?= (int) $dok['id'] ?>" target="_blank">🖨️ Cetak / PDF</a>
    <a class="btn utama" href="?p=dokumen_unduh&amp;id=<?= (int) $dok['id'] ?>">📄 Unduh Word</a>
</div>

<?php if ($dok['status'] === 'dicabut'): ?>
    <div class="pesan galat no-print" style="max-width:21cm">
        <b>Naskah ini sudah tidak berlaku (absolute)</b> dan tidak dapat diubah lagi.
        <?php if ($dok['dicabut_oleh_id']): ?>
            Penggantinya: <a href="?p=dokumen_edit&amp;id=<?= (int) $dok['dicabut_oleh_id'] ?>">buka revisi terbaru</a>.
        <?php endif; ?>
    </div>
<?php elseif (!Auth::canEdit()): ?>
    <div class="pesan info no-print" style="max-width:21cm">Akun Anda hanya dapat melihat naskah.</div>
<?php endif; ?>

<!-- =============================================== identitas naskah -->
<div class="kartu no-print">
    <h2>
        <span class="badge s-<?= e($dok['status']) ?>"><?= e(Naskah::statusLabel($dok['status'])) ?></span>
        <span class="nomor-dok" style="font-size:14px">&nbsp;<?= e($dok['nomor']) ?></span>
        <?php if ((int) $dok['revisi'] > 0): ?>
            <span class="badge b-na">Revisi ke-<?= (int) $dok['revisi'] ?></span>
        <?php endif; ?>
    </h2>

    <label class="f" for="k-judul">Judul naskah</label>
    <input class="f" id="k-judul" data-dok-kepala="judul" value="<?= e($dok['judul']) ?>"
           maxlength="500" <?= $terkunci ? 'disabled' : '' ?>>

    <div class="dok-kepala-grid">
        <div>
            <label class="f" for="k-nomor">Nomor naskah</label>
            <input class="f" id="k-nomor" data-dok-kepala="nomor" value="<?= e($dok['nomor']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="f" for="k-bagian">Bagian penerbit</label>
            <select class="f" id="k-bagian" data-dok-kepala="bagian" <?= $terkunci ? 'disabled' : '' ?>>
                <?php foreach (Naskah::bagian() as $kode => $nama): ?>
                    <option value="<?= e($kode) ?>" <?= $dok['bagian'] === $kode ? 'selected' : '' ?>>
                        <?= e($kode) ?> — <?= e($nama) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="f" for="k-klas">Klasifikasi salinan</label>
            <select class="f" id="k-klas" data-dok-kepala="klasifikasi" <?= $terkunci ? 'disabled' : '' ?>>
                <?php foreach (Naskah::klasifikasiOptions() as $kode => $nama): ?>
                    <option value="<?= e($kode) ?>" <?= $dok['klasifikasi'] === $kode ? 'selected' : '' ?>><?= e($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="f" for="k-terbit">Tanggal ditetapkan</label>
            <input class="f" type="date" id="k-terbit" data-dok-kepala="tanggal_terbit"
                   value="<?= e($dok['tanggal_terbit']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="f" for="k-berlaku">Mulai berlaku</label>
            <input class="f" type="date" id="k-berlaku" data-dok-kepala="tanggal_berlaku"
                   value="<?= e($dok['tanggal_berlaku']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="f" for="k-tinjau">Rencana peninjauan</label>
            <input class="f" type="date" id="k-tinjau" data-dok-kepala="tanggal_tinjau"
                   value="<?= e($dok['tanggal_tinjau']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>

        <div>
            <label class="f" for="k-siap">Disiapkan oleh</label>
            <input class="f" id="k-siap" data-dok-kepala="disiapkan_oleh" value="<?= e($dok['disiapkan_oleh']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="f" for="k-periksa">Diperiksa oleh</label>
            <input class="f" id="k-periksa" data-dok-kepala="diperiksa_oleh" value="<?= e($dok['diperiksa_oleh']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
        <div>
            <label class="f" for="k-sah">Disahkan oleh (nama)</label>
            <input class="f" id="k-sah" data-dok-kepala="disahkan_oleh" value="<?= e($dok['disahkan_oleh']) ?>" <?= $terkunci ? 'disabled' : '' ?>>
        </div>
    </div>

    <label class="f" for="k-jabatan">Jabatan penanda tangan</label>
    <input class="f" id="k-jabatan" data-dok-kepala="jabatan_pengesah" value="<?= e($dok['jabatan_pengesah']) ?>" <?= $terkunci ? 'disabled' : '' ?>>

    <label class="f" for="k-dasar">Regulasi induk / dasar penetapan</label>
    <input class="f" id="k-dasar" data-dok-kepala="dasar_dokumen" value="<?= e($dok['dasar_dokumen']) ?>"
           placeholder="Mis. Peraturan Direktur Nomor 001/PERDIR/SSM/2026" <?= $terkunci ? 'disabled' : '' ?>>

    <p class="petunjuk" style="font-size:12px;color:#6b7481;margin-top:10px">
        Semua isian tersimpan sendiri beberapa saat setelah Anda berhenti mengetik.
        Menurut Pedoman Tata Naskah, <?= e($def['nama']) ?> disiapkan oleh
        <b><?= e($def['pengesahan']['disiapkan']) ?></b>, diperiksa oleh
        <b><?= e($def['pengesahan']['diperiksa']) ?></b>, dan disahkan oleh
        <b><?= e($def['pengesahan']['disahkan']) ?></b>.
        Huruf naskah: <?= e($def['huruf']) ?>, kertas <?= e($def['kertas']) ?>.
    </p>
</div>

<!-- =================================================== isi naskah ---->
<div class="kartu no-print">
    <h2>Isi Naskah</h2>

    <?php if (!empty($def['kerangka']) && !$terkunci): ?>
        <form method="post" action="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>" style="margin-bottom:12px">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="dokumen_kerangka">
            <input type="hidden" name="id" value="<?= (int) $dok['id'] ?>">
            <label class="f" for="kerangka">Isi kerangka baku</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <select class="f" name="kerangka" id="kerangka" style="flex:1 1 260px">
                    <?php foreach (array_keys($def['kerangka']) as $namaKerangka): ?>
                        <option value="<?= e($namaKerangka) ?>"><?= e($namaKerangka) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">➕ Tambahkan kerangka</button>
            </div>
        </form>
    <?php endif; ?>

    <?php foreach ($def['blok'] as $b): ?>
        <div class="blok-edit">
            <h3><?= e($b['judul']) ?></h3>
            <?php if (!empty($b['petunjuk'])): ?>
                <p class="petunjuk"><?= e($b['petunjuk']) ?></p>
            <?php else: ?>
                <p class="petunjuk"><?= e(match ($b['tipe']) {
                    'daftar' => 'Satu baris satu butir — penomoran dibuat otomatis saat dicetak.',
                    'diktum' => 'Satu baris satu diktum. Label KESATU, KEDUA, … dibuat otomatis; tulis "LABEL : isi" bila ingin menentukan sendiri.',
                    'tabel'  => 'Baris pertama menjadi judul kolom. Pisahkan kolom dengan tanda | (pipa).',
                    'bab'    => 'Baris berawalan "BAB", huruf, atau angka menjadi judul; sisanya menjadi paragraf.',
                    default  => 'Satu baris satu paragraf.',
                }) ?></p>
            <?php endif; ?>
            <textarea class="f" rows="6" data-dok-blok="<?= e($b['kode']) ?>"
                      <?= $terkunci ? 'disabled' : '' ?>><?= e($dok['isi'][$b['kode']]['isi'] ?? '') ?></textarea>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================================================ hasil scan ------->
<div class="kartu no-print">
    <h2>Hasil Scan &amp; Lampiran</h2>
    <p class="petunjuk" style="font-size:12px;color:#6b7481;margin:0 0 10px">
        Simpan naskah yang sudah ditandatangani dan dibubuhi cap di sini. Berkas otomatis
        diunggah ke folder Google Drive naskah ini, sehingga tautannya dapat dibagikan.
    </p>

    <?php if (Auth::canEdit()): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
            <label class="f" for="dok-kategori" style="margin:0">Simpan sebagai</label>
            <select class="f" id="dok-kategori" style="width:auto">
                <option value="scan">Hasil scan naskah bertanda tangan</option>
                <option value="lampiran">Lampiran</option>
            </select>
        </div>
        <div class="zona-unggah" id="dok-zona" data-id="<?= (int) $dok['id'] ?>">
            <div class="zona-ikon">⬆️</div>
            <div><b>Klik untuk memilih berkas</b> — bisa beberapa sekaligus</div>
            <div class="zona-kecil">atau seret dan lepas berkas ke area ini · semua jenis berkas diterima</div>
        </div>
    <?php endif; ?>

    <div id="dok-folder" class="modal-folder">
        <?php if ($folder && $folder['drive_link']): ?>
            <a class="chip-folder" href="<?= e($folder['drive_link']) ?>" target="_blank" rel="noopener">
                📂 <?= e($folder['nama']) ?>
            </a>
        <?php endif; ?>
    </div>

    <ul class="dok-berkas" id="dok-berkas">
        <?php foreach ($dok['berkas'] as $b):
            $tautan = $b['drive_link'] ?: url(['p' => 'dokumen_berkas', 'id' => $b['id']]); ?>
            <li data-id="<?= (int) $b['id'] ?>">
                <span class="nb"><?= e(ikonBerkas($b['nama_file'])) ?>
                    <a href="<?= e($tautan) ?>" target="_blank" rel="noopener"><?= e($b['nama_file']) ?></a>
                </span>
                <div class="meta">
                    <?= $b['kategori'] === 'scan' ? 'Hasil scan' : 'Lampiran' ?> ·
                    <?= e(Storage::formatUkuran((int) $b['ukuran'])) ?> ·
                    <?= e(date('d/m/Y H:i', strtotime($b['uploaded_at']))) ?>
                    <?php if (Auth::canEdit()): ?>
                        · <a href="#" class="hapus-berkas" data-id="<?= (int) $b['id'] ?>">Hapus</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <div id="dok-kosong" style="color:#6b7481;font-size:13px;<?= $dok['berkas'] ? 'display:none' : '' ?>">
        Belum ada berkas tersimpan untuk naskah ini.
    </div>
</div>

<!-- ============================================ pengendalian dokumen -->
<div class="kartu no-print">
    <h2>Pengendalian Dokumen</h2>

    <?php if (Auth::canEdit()): ?>
    <div class="dok-kepala-grid" style="grid-template-columns:1fr 1fr">
        <form method="post" action="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="dokumen_status">
            <input type="hidden" name="id" value="<?= (int) $dok['id'] ?>">
            <label class="f" for="s-status">Ubah status</label>
            <select class="f" name="status" id="s-status">
                <?php foreach (Naskah::statusOptions() as $kode => $nama): ?>
                    <option value="<?= e($kode) ?>" <?= $dok['status'] === $kode ? 'selected' : '' ?>><?= e($nama) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="f" for="s-catatan">Catatan</label>
            <input class="f" name="catatan" id="s-catatan" placeholder="mis. disahkan dalam rapat direksi">
            <p></p>
            <button class="btn utama" type="submit">💾 Simpan status</button>
        </form>

        <form method="post" action="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>"
              onsubmit="return confirm('Buat revisi baru? Naskah ini akan ditandai tidak berlaku (absolute).')">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="dokumen_revisi">
            <input type="hidden" name="id" value="<?= (int) $dok['id'] ?>">
            <label class="f" for="r-catatan">Buat revisi</label>
            <input class="f" name="catatan" id="r-catatan" placeholder="Alasan revisi">
            <p class="petunjuk" style="font-size:12px;color:#6b7481;margin:6px 0 10px">
                Naskah lama ditandai <b>Tidak Berlaku (Absolute)</b> dan seluruh isinya disalin
                ke naskah baru dengan nomor revisi bertambah satu.
            </p>
            <button class="btn" type="submit">🔁 Buat revisi baru</button>
        </form>
    </div>
    <?php endif; ?>

    <h3>Distribusi Salinan</h3>
    <?php if ($distribusi): ?>
        <table class="rapi">
            <thead><tr><th>Unit</th><th>Salinan ke</th><th>Penerima</th><th>Tanggal</th></tr></thead>
            <tbody>
            <?php foreach ($distribusi as $ds): ?>
                <tr>
                    <td><?= e($ds['unit']) ?></td>
                    <td><?= e($ds['salinan_ke']) ?></td>
                    <td><?= e($ds['penerima']) ?></td>
                    <td><?= e(tanggalIndo($ds['tanggal'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#6b7481;font-size:13px">Belum ada salinan terkendali yang didistribusikan.</p>
    <?php endif; ?>

    <?php if (Auth::canEdit()): ?>
        <form method="post" action="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="dokumen_distribusi">
            <input type="hidden" name="id" value="<?= (int) $dok['id'] ?>">
            <div class="dok-kepala-grid">
                <div>
                    <label class="f" for="d-unit">Unit tujuan</label>
                    <input class="f" name="unit" id="d-unit" placeholder="mis. Keperawatan">
                </div>
                <div>
                    <label class="f" for="d-salinan">Salinan ke</label>
                    <input class="f" name="salinan_ke" id="d-salinan" placeholder="mis. 02">
                </div>
                <div>
                    <label class="f" for="d-penerima">Nama penerima</label>
                    <input class="f" name="penerima" id="d-penerima">
                </div>
            </div>
            <p></p>
            <button class="btn" type="submit">➕ Catat distribusi</button>
        </form>
    <?php endif; ?>

    <h3>Riwayat</h3>
    <ul class="riwayat-naskah">
        <?php foreach ($riwayat as $r): ?>
            <li>
                <b><?= e($r['aksi']) ?></b> — <?= e($r['catatan']) ?>
                <div class="waktu"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?> · <?= e($r['oleh']) ?></div>
            </li>
        <?php endforeach; ?>
        <?php if (!$riwayat): ?><li style="color:#6b7481">Belum ada riwayat.</li><?php endif; ?>
    </ul>

    <?php if (Auth::isAdmin()): ?>
        <h3>Hapus Naskah</h3>
        <form method="post" action="?p=dokumen_edit&amp;id=<?= (int) $dok['id'] ?>"
              onsubmit="return confirm('Hapus naskah ini beserta seluruh berkas scan-nya? Tindakan ini tidak dapat dibatalkan.')">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="dokumen_hapus">
            <input type="hidden" name="id" value="<?= (int) $dok['id'] ?>">
            <button class="btn bahaya" type="submit">🗑️ Hapus naskah</button>
        </form>
    <?php endif; ?>
</div>

<!-- ================================================== pratinjau ------>
<div class="aksi-atas no-print">
    <div class="judul">Pratinjau Naskah</div>
    <button class="btn" type="button" onclick="location.reload()">🔄 Perbarui pratinjau</button>
</div>

<div class="kertas naskah <?= ($def['huruf'] ?? '') === 'Arial' ? 'huruf-arial' : '' ?>">
    <?= RenderNaskah::naskah($dok) ?>
</div>

<?php require __DIR__ . '/partials/foot_dokumen.php'; ?>
