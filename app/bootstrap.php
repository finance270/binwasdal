<?php

declare(strict_types=1);

$CFG = require __DIR__ . '/config.php';

date_default_timezone_set($CFG['app']['timezone']);
mb_internal_encoding('UTF-8');

if ($CFG['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require __DIR__ . '/lib/DB.php';
require __DIR__ . '/lib/Settings.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/Forms.php';
require __DIR__ . '/lib/Installer.php';
require __DIR__ . '/lib/GoogleDrive.php';
require __DIR__ . '/lib/Storage.php';
require __DIR__ . '/lib/DocxWriter.php';
require __DIR__ . '/lib/Assessment.php';
require __DIR__ . '/lib/Naskah.php';
require __DIR__ . '/lib/Dokumen.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/lib/Render.php';
require __DIR__ . '/lib/RenderNaskah.php';
require __DIR__ . '/lib/EksporWord.php';
require __DIR__ . '/lib/EksporNaskah.php';

DB::init($CFG['db']);
Auth::start();

if (!is_dir($CFG['app']['upload_dir'])) {
    @mkdir($CFG['app']['upload_dir'], 0775, true);
}
