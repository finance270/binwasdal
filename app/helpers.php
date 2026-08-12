<?php

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape sekaligus ubah baris baru jadi <br>. */
function enl(?string $s): string
{
    return nl2br(e($s));
}

function url(array $params = []): string
{
    return '?' . http_build_query($params);
}

/**
 * Penanda versi berkas aset (CSS/JS).
 * Memakai waktu ubah berkas, sehingga peramban otomatis mengambil versi baru
 * setiap kali berkas diperbarui — tanpa perlu menekan Ctrl+Shift+R.
 */
function asetVersi(string $relatif): string
{
    $path = dirname(__DIR__) . '/public/' . ltrim($relatif, '/');
    $t = @filemtime($path);
    return $t ? (string) $t : (string) time();
}

/** Informasi versi aplikasi dari app/version.php. */
function appInfo(): array
{
    static $info = null;
    if ($info === null) {
        $info = require __DIR__ . '/version.php';
    }
    return $info;
}

/**
 * Berkas program yang dipakai sebagai patokan "kapan aplikasi terakhir diunggah".
 * @return array<string,int|false> jalur relatif => waktu ubah
 */
function berkasProgram(): array
{
    $root = dirname(__DIR__);
    $daftar = [
        'public/index.php',
        'public/assets/css/app.css',
        'public/assets/js/app.js',
        'app/lib/Render.php',
        'app/version.php',
    ];
    $out = [];
    foreach ($daftar as $f) {
        $out[$f] = @filemtime($root . '/' . $f);
    }
    return $out;
}

/** Waktu berkas program terbaru — untuk memastikan unggahan manual sudah masuk. */
function waktuBerkasProgram(): int
{
    $t = array_filter(berkasProgram());
    return $t ? max($t) : 0;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $pesan = null, string $tipe = 'sukses'): ?array
{
    if ($pesan !== null) {
        $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Label status jawaban. */
function statusLabel(string $s): string
{
    return [
        ''          => 'Belum diisi',
        'ada'       => 'Ada / Sesuai',
        'sebagian'  => 'Sebagian',
        'tidak_ada' => 'Belum Ada',
        'na'        => 'Tidak Berlaku',
    ][$s] ?? $s;
}

function statusOptions(): array
{
    return ['' => '— pilih —', 'ada' => 'Ada / Sesuai', 'sebagian' => 'Sebagian', 'tidak_ada' => 'Belum Ada', 'na' => 'Tidak Berlaku'];
}

/** Format tanggal Indonesia: 12 Agustus 2026 */
function tanggalIndo(?string $tanggal): string
{
    if (!$tanggal) {
        return '';
    }
    $ts = strtotime($tanggal);
    if (!$ts) {
        return $tanggal;
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__FILE__) . '/views/' . $name . '.php';
}

/** Angka romawi untuk penomoran bab. */
function romawi(int $n): string
{
    $peta = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90,
             'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
    $out = '';
    foreach ($peta as $r => $v) {
        while ($n >= $v) {
            $out .= $r;
            $n -= $v;
        }
    }
    return $out;
}

/** Ikon sederhana berdasarkan ekstensi berkas. */
function ikonBerkas(string $nama): string
{
    $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    return match (true) {
        $ext === 'pdf' => '📕',
        in_array($ext, ['doc', 'docx'], true) => '📘',
        in_array($ext, ['xls', 'xlsx', 'csv'], true) => '📗',
        in_array($ext, ['ppt', 'pptx'], true) => '📙',
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => '🖼️',
        in_array($ext, ['zip', 'rar'], true) => '🗜️',
        default => '📄',
    };
}

/** Tautan yang dipakai untuk membuka berkas (Drive atau lokal). */
function tautanBerkas(array $doc): string
{
    if (!empty($doc['drive_link'])) {
        return $doc['drive_link'];
    }
    return url(['p' => 'berkas', 'id' => $doc['id']]);
}
