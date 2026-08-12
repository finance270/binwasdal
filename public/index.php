<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$page = $_GET['p'] ?? 'beranda';

// ---------------------------------------------------------------------
// Pemasangan otomatis saat pertama kali dijalankan
// ---------------------------------------------------------------------
try {
    $perluPasang = Installer::needsInstall();
} catch (Throwable $e) {
    $perluPasang = true;
}

if ($perluPasang) {
    if ($page !== 'pasang') {
        redirect(url(['p' => 'pasang']));
    }
    $log = [];
    $galat = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $log = Installer::run($CFG);
        } catch (Throwable $e) {
            $galat = $e->getMessage();
        }
        if (!$galat) {
            redirect(url(['p' => 'login']));
        }
    }
    view('pasang', ['CFG' => $CFG, 'log' => $log, 'galat' => $galat]);
    exit;
}

// ---------------------------------------------------------------------
// Otentikasi
// ---------------------------------------------------------------------
if ($page === 'login') {
    $galat = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (Auth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect(url(['p' => 'beranda']));
        }
        $galat = 'Nama pengguna atau kata sandi salah.';
    }
    view('login', ['CFG' => $CFG, 'galat' => $galat]);
    exit;
}

if ($page === 'logout') {
    Auth::logout();
    redirect(url(['p' => 'login']));
}

Auth::require();

$assessment = Assessment::current();
$storage    = new Storage($CFG);

// ---------------------------------------------------------------------
// API (dipanggil lewat AJAX)
// ---------------------------------------------------------------------
if (str_starts_with($page, 'api_')) {
    if (!Auth::checkCsrf($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_out(['ok' => false, 'pesan' => 'Sesi kedaluwarsa, muat ulang halaman.'], 419);
    }
    // hanya api_detail yang boleh diakses akun "viewer"
    if ($page !== 'api_detail' && !Auth::canEdit()) {
        json_out(['ok' => false, 'pesan' => 'Akun Anda hanya dapat melihat.'], 403);
    }

    switch ($page) {
        // --- isi jendela popup sebuah poin --------------------------
        case 'api_detail':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            $detail = Assessment::detailPoin((int) $assessment['id'], $type, $key);
            if (!$detail) {
                json_out(['ok' => false, 'pesan' => 'Poin tidak ditemukan.'], 404);
            }
            $detail['ok'] = true;
            $detail['boleh_edit'] = Auth::canEdit();
            json_out($detail);

        // --- simpan jawaban poin checklist -------------------------
        case 'api_jawaban':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $ket    = (string) ($_POST['keterangan'] ?? '');
            if (!array_key_exists($status, statusOptions())) {
                json_out(['ok' => false, 'pesan' => 'Status tidak valid.'], 422);
            }
            if (!DB::val('SELECT 1 FROM items WHERE id = ?', [$itemId])) {
                json_out(['ok' => false, 'pesan' => 'Poin tidak ditemukan.'], 404);
            }
            DB::q(
                'INSERT INTO answers (assessment_id, item_id, status, keterangan, updated_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), updated_by = VALUES(updated_by)',
                [$assessment['id'], $itemId, $status, $ket, Auth::username()]
            );
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- simpan sel tabel profil --------------------------------
        case 'api_sel':
            $table = (string) ($_POST['table_code'] ?? '');
            $rowNo = (int) ($_POST['row_no'] ?? -1);
            $col   = (string) ($_POST['col_code'] ?? '');
            $val   = (string) ($_POST['v'] ?? '');
            $def   = Forms::get($table);
            if (!$def) {
                json_out(['ok' => false, 'pesan' => 'Tabel tidak dikenal.'], 404);
            }
            $bolehHeader = $rowNo === 0 && in_array($col, $def['header_editable'] ?? [], true);
            if (!$bolehHeader && (!in_array($col, $def['editable'], true) || $rowNo < 1)) {
                json_out(['ok' => false, 'pesan' => 'Kolom tidak dapat diubah.'], 422);
            }
            DB::q(
                'INSERT INTO form_values (assessment_id, table_code, row_no, col_code, v)
                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
                [$assessment['id'], $table, $rowNo, $col, $val]
            );
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- simpan data dasar --------------------------------------
        case 'api_profil':
            $k = (string) ($_POST['k'] ?? '');
            $v = (string) ($_POST['v'] ?? '');
            if (!DB::val('SELECT 1 FROM profil_values WHERE assessment_id = ? AND k = ?', [$assessment['id'], $k])) {
                json_out(['ok' => false, 'pesan' => 'Isian tidak dikenal.'], 404);
            }
            DB::q('UPDATE profil_values SET v = ? WHERE assessment_id = ? AND k = ?', [$v, $assessment['id'], $k]);
            if ($k === 'nama_rumah_sakit' && trim($v) !== '') {
                DB::q('UPDATE assessments SET nama_rs = ? WHERE id = ?', [trim($v), $assessment['id']]);
            }
            json_out(['ok' => true, 'waktu' => date('H:i:s')]);

        // --- buat / ambil folder Google Drive untuk sebuah poin -----
        case 'api_folder':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            $folder = $storage->pointFolder($assessment, $type, $key);
            if (!$folder) {
                json_out(['ok' => false, 'pesan' => $storage->lastError ?: 'Gagal membuat folder.'], 500);
            }
            json_out([
                'ok'     => true,
                'folder' => [
                    'nama' => $folder['nama'],
                    'link' => $folder['drive_link'],
                    'id'   => $folder['drive_id'],
                    'lokal' => $folder['drive_id'] ? false : true,
                ],
            ]);

        // --- unggah beberapa berkas sekaligus ----------------------
        case 'api_unggah':
            $type = (string) ($_POST['owner_type'] ?? 'item');
            $key  = (string) ($_POST['owner_key'] ?? '');
            if (empty($_FILES['berkas'])) {
                json_out(['ok' => false, 'pesan' => 'Tidak ada berkas yang dikirim.'], 422);
            }
            $files = $_FILES['berkas'];
            $jml = is_array($files['name']) ? count($files['name']) : 0;
            $berhasil = [];
            $gagal = [];
            for ($i = 0; $i < $jml; $i++) {
                $satu = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size'     => $files['size'][$i],
                    'error'    => $files['error'][$i],
                ];
                $res = $storage->simpanBerkas($assessment, $type, $key, $satu);
                if ($res['ok']) {
                    $d = $res['dokumen'];
                    $berhasil[] = [
                        'id'     => (int) $d['id'],
                        'nama'   => $d['nama_file'],
                        'ukuran' => Storage::formatUkuran((int) $d['ukuran']),
                        'link'   => tautanBerkas($d),
                        'ikon'   => ikonBerkas($d['nama_file']),
                    ];
                } else {
                    $gagal[] = $satu['name'] . ': ' . $res['pesan'];
                }
            }
            $folder = DB::one(
                'SELECT * FROM drive_folders WHERE assessment_id = ? AND owner_type = ? AND owner_key = ?',
                [$assessment['id'], $type, $key]
            );
            json_out([
                'ok'      => count($berhasil) > 0,
                'berkas'  => $berhasil,
                'gagal'   => $gagal,
                'pesan'   => count($berhasil) . ' berkas tersimpan' . ($gagal ? ', ' . count($gagal) . ' gagal' : '') . '.',
                'folder'  => $folder ? [
                    'nama'  => $folder['nama'],
                    'link'  => $folder['drive_link'],
                    'lokal' => $folder['drive_id'] ? false : true,
                ] : null,
            ]);

        case 'api_hapus_berkas':
            $res = $storage->hapusBerkas((int) $assessment['id'], (int) ($_POST['id'] ?? 0));
            json_out($res, $res['ok'] ? 200 : 404);

        case 'api_tes_drive':
            if (!Auth::isAdmin()) {
                json_out(['ok' => false, 'pesan' => 'Hanya admin.'], 403);
            }
            json_out(GoogleDrive::fromSettings($CFG)->testConnection());

        default:
            json_out(['ok' => false, 'pesan' => 'Endpoint tidak dikenal.'], 404);
    }
}

// ---------------------------------------------------------------------
// Unduh berkas lokal
// ---------------------------------------------------------------------
if ($page === 'berkas') {
    $doc = DB::one('SELECT * FROM documents WHERE id = ? AND assessment_id = ?', [(int) ($_GET['id'] ?? 0), $assessment['id']]);
    if (!$doc || !$doc['local_path']) {
        http_response_code(404);
        exit('Berkas tidak ditemukan.');
    }
    $full = rtrim($CFG['app']['upload_dir'], '/') . '/' . $doc['local_path'];
    if (!is_file($full)) {
        http_response_code(404);
        exit('Berkas tidak ada di penyimpanan.');
    }
    header('Content-Type: ' . ($doc['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: inline; filename="' . rawurlencode($doc['nama_file']) . '"');
    readfile($full);
    exit;
}

// ---------------------------------------------------------------------
// Aksi POST biasa
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !str_starts_with($page, 'api_')) {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Silakan muat ulang halaman.');
    }
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'pilih_periode') {
        Assessment::setCurrent((int) $_POST['assessment_id']);
        redirect(url(['p' => $_POST['kembali'] ?? 'beranda']));
    }

    if ($aksi === 'buat_periode') {
        Auth::requireEdit();
        $nama = trim((string) $_POST['nama_rs']);
        $tahun = (int) $_POST['tahun'];
        if ($nama === '' || $tahun < 2000) {
            flash('Nama rumah sakit dan tahun wajib diisi.', 'galat');
            redirect(url(['p' => 'pengaturan']));
        }
        if (DB::val('SELECT 1 FROM assessments WHERE nama_rs = ? AND tahun = ?', [$nama, $tahun])) {
            flash('Periode untuk rumah sakit dan tahun tersebut sudah ada.', 'galat');
            redirect(url(['p' => 'pengaturan']));
        }
        $id = Assessment::create($nama, $tahun, trim((string) ($_POST['wilayah'] ?? '')), !empty($_POST['salin']));
        Assessment::setCurrent($id);
        flash('Periode penilaian baru dibuat.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'simpan_drive') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        Settings::set('gdrive_auth_mode', (string) $_POST['auth_mode']);
        Settings::set('gdrive_root_folder_id', GoogleDrive::parseFolderId((string) $_POST['root_folder_id']));
        Settings::set('gdrive_client_id', trim((string) $_POST['client_id']));
        Settings::set('gdrive_client_secret', trim((string) $_POST['client_secret']));
        if (trim((string) $_POST['refresh_token']) !== '') {
            Settings::set('gdrive_refresh_token', trim((string) $_POST['refresh_token']));
        }
        if (trim((string) $_POST['sa_json']) !== '') {
            Settings::set('gdrive_sa_json', trim((string) $_POST['sa_json']));
        }
        Settings::forget('gdrive_token');
        Settings::forget('gdrive_token_exp');
        flash('Pengaturan Google Drive disimpan.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'tukar_kode_oauth') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $kode = trim((string) $_POST['kode']);
        $redirect = trim((string) $_POST['redirect_uri']);
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code'          => $kode,
                'client_id'     => Settings::get('gdrive_client_id', ''),
                'client_secret' => Settings::get('gdrive_client_secret', ''),
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $res = json_decode((string) curl_exec($ch), true) ?: [];
        curl_close($ch);
        if (!empty($res['refresh_token'])) {
            Settings::set('gdrive_refresh_token', $res['refresh_token']);
            Settings::set('gdrive_auth_mode', 'oauth');
            Settings::forget('gdrive_token');
            flash('Refresh token berhasil disimpan. Google Drive siap dipakai.');
        } else {
            flash('Gagal menukar kode: ' . json_encode($res, JSON_UNESCAPED_UNICODE)
                . ' — pastikan meminta akses dengan prompt=consent&access_type=offline.', 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'status_dokumen') {
        Auth::requireEdit();
        DB::q('UPDATE assessments SET status = ? WHERE id = ?', [$_POST['status'] === 'final' ? 'final' : 'draft', $assessment['id']]);
        flash('Status dokumen diperbarui.');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'simpan_pengguna') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $username = trim((string) $_POST['username']);
        $pass = (string) $_POST['password'];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::q('UPDATE users SET nama = ?, role = ?, aktif = ? WHERE id = ?', [
                trim((string) $_POST['nama']), (string) $_POST['role'], isset($_POST['aktif']) ? 1 : 0, $id,
            ]);
            if ($pass !== '') {
                DB::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            flash('Pengguna diperbarui.');
        } else {
            if ($username === '' || $pass === '') {
                flash('Nama pengguna dan kata sandi wajib diisi.', 'galat');
                redirect(url(['p' => 'pengaturan']));
            }
            if (DB::val('SELECT 1 FROM users WHERE username = ?', [$username])) {
                flash('Nama pengguna sudah dipakai.', 'galat');
                redirect(url(['p' => 'pengaturan']));
            }
            DB::insert('users', [
                'username'      => $username,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'nama'          => trim((string) $_POST['nama']),
                'role'          => (string) $_POST['role'],
            ]);
            flash('Pengguna ditambahkan.');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'hapus_pengguna') {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Hanya admin.');
        }
        $id = (int) $_POST['id'];
        if ($id !== (Auth::user()['id'] ?? 0)) {
            DB::q('DELETE FROM users WHERE id = ?', [$id]);
            flash('Pengguna dihapus.');
        } else {
            flash('Tidak dapat menghapus akun yang sedang dipakai.', 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'sinkron_drive') {
        Auth::requireEdit();
        $res = $storage->sinkronKeDrive($assessment);
        flash($res['pesan'], $res['gagal'] > 0 ? 'galat' : 'sukses');
        redirect(url(['p' => 'pengaturan']));
    }

    if ($aksi === 'siapkan_folder') {
        Auth::requireEdit();
        $f = $storage->assessmentFolder($assessment);
        if ($f) {
            flash('Folder utama siap: ' . $f['nama'] . ($f['drive_link'] ? '' : ' (penyimpanan lokal)'));
        } else {
            flash('Gagal menyiapkan folder: ' . $storage->lastError, 'galat');
        }
        redirect(url(['p' => 'pengaturan']));
    }
}

// ---------------------------------------------------------------------
// Halaman
// ---------------------------------------------------------------------
$data = [
    'CFG'        => $CFG,
    'assessment' => $assessment,
    'storage'    => $storage,
    'page'       => $page,
    'profil'     => Assessment::profilMap((int) $assessment['id']),
];

switch ($page) {
    case 'beranda':
        $data['ringkasan'] = Assessment::ringkasan((int) $assessment['id']);
        view('beranda', $data);
        break;

    case 'profil':
        $data['fields'] = Assessment::profil((int) $assessment['id']);
        $data['tables'] = Forms::profilTables();
        view('profil', $data);
        break;

    case 'sdm':
        $data['tables'] = Forms::sdmTables();
        view('sdm', $data);
        break;

    case 'bagian':
        $sec = Assessment::section((int) ($_GET['id'] ?? 0));
        if (!$sec) {
            $sections = Assessment::sections();
            redirect(url(['p' => 'bagian', 'id' => $sections[0]['id'] ?? 0]));
        }
        $data['sec']  = $sec;
        $data['tree'] = Assessment::tree((int) $sec['id'], (int) $assessment['id']);
        view('bagian', $data);
        break;

    case 'cetak':
        $data['bagian'] = $_GET['bagian'] ?? 'all';
        view('cetak', $data);
        break;

    case 'tautan':
        $data['daftar'] = Assessment::daftarTautan((int) $assessment['id']);
        view('tautan', $data);
        break;

    case 'pengaturan':
        $data['periode']  = Assessment::listAll();
        $data['users']    = Auth::isAdmin() ? DB::all('SELECT * FROM users ORDER BY id') : [];
        $data['log']      = DB::all('SELECT * FROM activity_log ORDER BY id DESC LIMIT 30');
        $data['drive']    = GoogleDrive::fromSettings($CFG);
        view('pengaturan', $data);
        break;

    default:
        http_response_code(404);
        view('galat', $data + ['pesan' => 'Halaman tidak ditemukan.']);
}
