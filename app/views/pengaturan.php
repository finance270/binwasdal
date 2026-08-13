<?php
/** @var array $periode */
/** @var array $users */
/** @var array $log */
/** @var GoogleDrive $drive */
/** @var Storage $storage */
$judul = 'Pengaturan — ' . $CFG['app']['name'];
$mode = Settings::get('gdrive_auth_mode', $CFG['drive']['auth_mode']);
$rootId = Settings::get('gdrive_root_folder_id', $CFG['drive']['root_folder_id']);
$clientId = Settings::get('gdrive_client_id', $CFG['drive']['client_id']);
$clientSecret = Settings::get('gdrive_client_secret', $CFG['drive']['client_secret']);
$adaRefresh = (bool) Settings::get('gdrive_refresh_token', $CFG['drive']['refresh_token']);
$adaSa = (bool) Settings::get('gdrive_sa_json', $CFG['drive']['sa_json']);

$skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$redirectUri = $skema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'], '?');
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print"><div class="judul">Pengaturan</div></div>

<!-- --------------------------------------------- Versi & pembaruan kode -->
<?php
$v = appInfo();
$oc = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$ocAktif = is_array($oc) && !empty($oc['opcache_enabled']);
$ocValidasi = (bool) ini_get('opcache.validate_timestamps');
?>
<div class="kartu">
    <h2>🧾 Versi Aplikasi</h2>
    <div class="tabel-gulir"><table class="rapi">
        <tr>
            <th style="width:230px">Versi</th>
            <td><b><?= e($v['versi']) ?></b> — <?= e($v['catatan']) ?> (<?= e($v['tanggal']) ?>)</td>
        </tr>
        <?php foreach (berkasProgram() as $f => $t): ?>
            <tr>
                <th><code><?= e($f) ?></code></th>
                <td><?= $t ? e(date('d/m/Y H:i:s', $t)) : '<span style="color:#b3261e">berkas tidak ditemukan</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th>OPcache</th>
            <td>
                <?php if (!$ocAktif): ?>
                    Tidak aktif — setiap perubahan berkas langsung berlaku.
                <?php elseif ($ocValidasi): ?>
                    Aktif, memeriksa perubahan berkas otomatis
                    (<code>validate_timestamps=1</code>, <code>revalidate_freq=<?= e((string) ini_get('opcache.revalidate_freq')) ?></code>).
                <?php else: ?>
                    <b style="color:#b3261e">Aktif tanpa pemeriksaan perubahan berkas</b>
                    (<code>validate_timestamps=0</code>) — berkas PHP yang baru diunggah
                    <b>tidak akan berlaku</b> sampai kode dimuat ulang atau container di-restart.
                <?php endif; ?>
            </td>
        </tr>
    </table></div>

    <p style="font-size:13px;color:#59616d;line-height:1.7;margin-bottom:8px">
        Setelah mengunggah berkas hasil <b>Download ZIP</b> dari GitHub ke server, cocokkan
        <b>waktu berkas</b> di atas dengan waktu Anda mengunggah. Bila waktunya masih lama,
        berarti berkas belum tergantikan (biasanya karena tertaruh di folder lain).
        Bila waktunya sudah baru tetapi tampilan belum berubah, tekan tombol di bawah ini.
    </p>
    <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="muat_ulang_kode">
        <button class="btn utama">🔄 Muat ulang kode program</button>
        <span style="font-size:12.5px;color:#6b7481">
            Mengosongkan cache OPcache — setara dengan me-restart container, tanpa perlu masuk ke terminal.
        </span>
    </form>
</div>

<!-- ------------------------------------------------------- Google Drive -->
<div class="kartu">
    <h2>🔗 Google Drive</h2>
    <p style="font-size:13px;color:#59616d;margin-top:0">
        Setiap poin penilaian mendapat folder sendiri di dalam folder induk. Folder otomatis dibagikan
        <b>“siapa saja yang memiliki link dapat melihat”</b>, sehingga tautan yang muncul di aplikasi bisa
        langsung dibuka oleh Suku Dinas Kesehatan maupun pihak lain.
    </p>

    <div id="hasil-tes" class="pesan info" style="display:none"></div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="simpan_drive">

        <label class="f">Metode autentikasi</label>
        <select class="f" name="auth_mode">
            <option value="oauth" <?= $mode === 'oauth' ? 'selected' : '' ?>>OAuth akun Google (disarankan — folder di My Drive)</option>
            <option value="service_account" <?= $mode === 'service_account' ? 'selected' : '' ?>>Service Account (folder harus di Shared Drive)</option>
            <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>>Nonaktif (simpan berkas di server saja)</option>
        </select>

        <label class="f">ID / Tautan folder induk Google Drive</label>
        <input class="f" name="root_folder_id" value="<?= e($rootId) ?>"
               placeholder="https://drive.google.com/drive/folders/xxxxxxxx atau ID-nya saja">

        <div class="grid" style="grid-template-columns:1fr 1fr">
            <div>
                <label class="f">Client ID (OAuth)</label>
                <input class="f" name="client_id" value="<?= e($clientId) ?>" autocomplete="off">
            </div>
            <div>
                <label class="f">Client Secret (OAuth)</label>
                <input class="f" name="client_secret" value="<?= e($clientSecret) ?>" autocomplete="off">
            </div>
        </div>

        <label class="f">Refresh Token <?= $adaRefresh ? '<span style="color:#1e7d43">(sudah tersimpan — kosongkan bila tidak diubah)</span>' : '' ?></label>
        <input class="f" name="refresh_token" value="" autocomplete="off" placeholder="1//0g...">

        <label class="f">Kunci JSON Service Account <?= $adaSa ? '<span style="color:#1e7d43">(sudah tersimpan)</span>' : '' ?></label>
        <textarea class="f" name="sa_json" placeholder='{"type":"service_account", ...}'></textarea>

        <div style="margin-top:14px;display:flex;gap:8px">
            <button class="btn utama">💾 Simpan</button>
            <button type="button" class="btn" id="tombol-tes">🔌 Tes koneksi</button>
        </div>
    </form>

    <?php
    $berkasLokal = (int) DB::val(
        'SELECT COUNT(*) FROM documents WHERE assessment_id = ? AND drive_id IS NULL AND local_path IS NOT NULL',
        [$assessment['id']]
    );
    ?>
    <?php if ($berkasLokal > 0): ?>
        <div class="pesan info" style="margin-top:14px">
            Ada <b><?= $berkasLokal ?> berkas</b> yang masih tersimpan di server (diunggah sebelum Google Drive aktif).
            <form method="post" style="display:inline-block;margin-left:8px">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
                <input type="hidden" name="aksi" value="sinkron_drive">
                <button class="btn"<?= $storage->driveAktif() ? '' : ' disabled' ?>>☁️ Pindahkan ke Google Drive</button>
            </form>
            <span style="font-size:12px">Diproses 25 berkas tiap klik.</span>
        </div>
    <?php endif; ?>

    <h3>Cara mendapatkan Refresh Token (OAuth)</h3>
    <ol style="font-size:13px;line-height:1.75;padding-left:20px">
        <li>Buka <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank">Google Cloud Console</a> → aktifkan <b>Google Drive API</b>.</li>
        <li>Menu <b>Credentials</b> → <b>Create Credentials</b> → <b>OAuth client ID</b> → tipe <b>Web application</b>.</li>
        <li>Tambahkan <b>Authorized redirect URI</b> berikut (salin persis):
            <br><code style="background:#f0f2f5;padding:2px 6px;border-radius:4px"><?= e($redirectUri) ?></code>
            <br><span style="color:#6b7481">Bila aplikasi diakses dari alamat lain, daftarkan alamat tersebut juga.</span>
        </li>
        <li>Isi <b>Client ID</b> dan <b>Client Secret</b> di atas lalu klik <b>Simpan</b>.</li>
        <li>Klik tombol <b>Minta izin Google</b> di bawah, setujui akses, lalu salin parameter <code>code</code> dari alamat browser dan tempel di kotak berikutnya.</li>
    </ol>

    <?php if ($clientId): ?>
        <?php
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/drive',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
        ?>
        <p><a class="btn utama" href="<?= e($authUrl) ?>" target="_blank">🔓 Minta izin Google</a></p>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <input type="hidden" name="aksi" value="tukar_kode_oauth">
            <input type="hidden" name="redirect_uri" value="<?= e($redirectUri) ?>">
            <div style="flex:1;min-width:320px">
                <label class="f">Kode otorisasi (parameter <code>code</code> dari alamat browser)</label>
                <input class="f" name="kode" placeholder="4/0Ade..." required>
            </div>
            <button class="btn">🔑 Tukar jadi Refresh Token</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($_GET['code'])): ?>
        <div class="pesan sukses" style="margin-top:12px">
            Kode otorisasi terdeteksi di alamat halaman ini. Salin nilai berikut ke kotak di atas:<br>
            <code style="word-break:break-all"><?= e((string) $_GET['code']) ?></code>
        </div>
    <?php endif; ?>
</div>

<!-- ---------------------------------------------------------- Periode -->
<div class="kartu">
    <h2>🗓️ Periode Penilaian</h2>
    <div class="tabel-gulir"><table class="rapi">
        <thead><tr><th>Rumah Sakit</th><th style="width:80px">Tahun</th><th style="width:90px">Status</th><th style="width:230px">Folder Drive</th><th style="width:90px"></th></tr></thead>
        <tbody>
        <?php foreach ($periode as $p): ?>
            <tr>
                <td><?= e($p['nama_rs']) ?><?= $p['id'] == $assessment['id'] ? ' <b style="color:#1e7d43">(aktif)</b>' : '' ?></td>
                <td><?= (int) $p['tahun'] ?></td>
                <td><?= $p['status'] === 'final' ? 'Final' : 'Draft' ?></td>
                <td><?= $p['drive_folder_link'] ? '<a href="' . e($p['drive_folder_link']) . '" target="_blank">Buka folder</a>' : '<span style="color:#8a929e">—</span>' ?></td>
                <td>
                    <?php if ($p['id'] != $assessment['id']): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
                            <input type="hidden" name="aksi" value="pilih_periode">
                            <input type="hidden" name="assessment_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="kembali" value="pengaturan">
                            <button class="btn kecil">Pakai</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <h3>Buat periode baru</h3>
    <form method="post" class="grid" style="grid-template-columns:2fr 1fr 2fr auto;align-items:end">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="buat_periode">
        <div><label class="f">Nama Rumah Sakit</label><input class="f" name="nama_rs" value="<?= e($assessment['nama_rs']) ?>" required></div>
        <div><label class="f">Tahun</label><input class="f" type="number" name="tahun" value="<?= (int) date('Y') + 1 ?>" required></div>
        <div><label class="f">Wilayah</label><input class="f" name="wilayah" value="<?= e($assessment['wilayah']) ?>"></div>
        <div><button class="btn utama">➕ Buat</button></div>
        <div style="grid-column:1/-1;font-size:12.5px">
            <label style="font-weight:400"><input type="checkbox" name="salin" value="1"> Salin isian bawaan dari dokumen Word resmi</label>
        </div>
    </form>

    <h3>Status dokumen periode aktif</h3>
    <form method="post" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="status_dokumen">
        <select class="f" name="status" style="width:180px">
            <option value="draft" <?= $assessment['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="final" <?= $assessment['status'] === 'final' ? 'selected' : '' ?>>Final (siap cetak)</option>
        </select>
        <button class="btn">Simpan</button>
    </form>
</div>

<!-- --------------------------------------------------------- Pengguna -->
<?php if (Auth::isAdmin()): ?>
<div class="kartu">
    <h2>👤 Pengguna</h2>
    <div class="tabel-gulir"><table class="rapi">
        <thead><tr><th>Nama Pengguna</th><th>Nama</th><th style="width:110px">Peran</th><th style="width:80px">Aktif</th><th style="width:90px"></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['nama']) ?></td>
                <td><?= e($u['role']) ?></td>
                <td><?= $u['aktif'] ? 'Ya' : 'Tidak' ?></td>
                <td>
                    <?php if ((int) $u['id'] !== (Auth::user()['id'] ?? 0)): ?>
                        <form method="post" onsubmit="return confirm('Hapus pengguna ini?')">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
                            <input type="hidden" name="aksi" value="hapus_pengguna">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn kecil bahaya">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <h3>Tambah pengguna</h3>
    <form method="post" class="grid" style="grid-template-columns:1fr 1fr 1fr 1fr auto;align-items:end">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
        <input type="hidden" name="aksi" value="simpan_pengguna">
        <div><label class="f">Nama pengguna</label><input class="f" name="username" required></div>
        <div><label class="f">Nama lengkap</label><input class="f" name="nama"></div>
        <div><label class="f">Kata sandi</label><input class="f" type="password" name="password" required></div>
        <div><label class="f">Peran</label>
            <select class="f" name="role">
                <option value="editor">Editor (isi &amp; unggah)</option>
                <option value="admin">Admin</option>
                <option value="viewer">Viewer (hanya lihat)</option>
            </select>
        </div>
        <div><button class="btn utama">➕ Tambah</button></div>
    </form>
</div>
<?php endif; ?>

<!-- ------------------------------------------------------------- Log -->
<div class="kartu">
    <h2>📜 Aktivitas Terakhir</h2>
    <div class="tabel-gulir"><table class="rapi">
        <thead><tr><th style="width:150px">Waktu</th><th style="width:120px">Pengguna</th><th style="width:120px">Aksi</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                <td><?= e($l['username']) ?></td>
                <td><?= e($l['aksi']) ?></td>
                <td><?= e($l['keterangan']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<script>
document.getElementById('tombol-tes')?.addEventListener('click', function () {
    var kotak = document.getElementById('hasil-tes');
    kotak.style.display = 'block';
    kotak.className = 'pesan info';
    kotak.textContent = 'Menghubungi Google Drive…';
    var fd = new FormData();
    fd.append('csrf', document.querySelector('meta[name="csrf"]').content);
    fetch(window.location.pathname + '?p=api_tes_drive', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
            kotak.className = 'pesan ' + (r.ok ? 'sukses' : 'galat');
            kotak.textContent = r.pesan;
        })
        .catch(function () { kotak.className = 'pesan galat'; kotak.textContent = 'Gagal menghubungi server.'; });
});
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
