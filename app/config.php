<?php
/**
 * Konfigurasi aplikasi.
 * Semua nilai dapat ditimpa lewat environment variable (docker-compose / CasaOS).
 */

function env(string $key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return $v;
}

return [
    'app' => [
        'name'      => env('APP_NAME', 'Self Assessment Binwasdal RS'),
        'timezone'  => env('APP_TIMEZONE', 'Asia/Jakarta'),
        'debug'     => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'root'      => dirname(__DIR__),
        'upload_dir' => env('UPLOAD_DIR', dirname(__DIR__) . '/data/uploads'),
        // batas ukuran per berkas (byte). Default 1 GB agar video termuat.
        'max_upload' => (int) env('MAX_UPLOAD_SIZE', 1024 * 1024 * 1024),
        // Daftar jenis berkas yang diizinkan. Nilai "*" berarti semua jenis
        // diterima — termasuk video, audio, dan gambar dari ponsel.
        'allowed_ext' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ALLOWED_EXT', '*'))
        ))),
        // Jenis berkas yang selalu ditolak karena dapat dijalankan di server
        // atau dieksekusi di peramban atas nama aplikasi.
        'blocked_ext' => array_values(array_filter(array_map('strtolower', array_map(
            'trim',
            explode(',', env(
                'BLOCKED_EXT',
                'php,php3,php4,php5,php7,php8,phtml,phps,pht,phar,inc,'
                . 'cgi,pl,py,rb,sh,bash,zsh,ksh,'
                . 'exe,com,bat,cmd,msi,scr,vbs,vbe,ws,wsf,wsh,ps1,psm1,'
                . 'jar,war,dll,so,dylib,app,apk,deb,rpm,'
                . 'htaccess,htpasswd,ini,conf,'
                . 'html,htm,xhtml,shtml,svg,xml,xsl,swf'
            ))
        )))),
    ],
    'db' => [
        'host' => env('DB_HOST', 'mariadb'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_NAME', 'binwasdal'),
        'user' => env('DB_USER', 'binwasdal'),
        'pass' => env('DB_PASS', 'binwasdal'),
        'charset' => 'utf8mb4',
    ],
    'drive' => [
        // Folder induk Google Drive (dari tautan yang diberikan).
        'root_folder_id' => env('GDRIVE_ROOT_FOLDER_ID', '1f-KUg6Rq1CeZ4ZkksrPF7UbPiLI-GJjZ'),
        // oauth | service_account | off
        'auth_mode'     => env('GDRIVE_AUTH_MODE', 'oauth'),
        'client_id'     => env('GDRIVE_CLIENT_ID', ''),
        'client_secret' => env('GDRIVE_CLIENT_SECRET', ''),
        'refresh_token' => env('GDRIVE_REFRESH_TOKEN', ''),
        'sa_json'       => env('GDRIVE_SERVICE_ACCOUNT_JSON', ''),
    ],
    'auth' => [
        'default_user' => env('ADMIN_USER', 'admin'),
        'default_pass' => env('ADMIN_PASS', 'admin123'),
    ],
];
