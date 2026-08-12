<?php

/**
 * Klien Google Drive API v3 tanpa Composer.
 * Mendukung dua cara autentikasi:
 *   - oauth           : OAuth 2.0 refresh token milik akun Google pemilik folder (disarankan)
 *   - service_account : kunci JSON service account (folder harus di Shared Drive)
 *
 * Bila belum dikonfigurasi, aplikasi otomatis memakai penyimpanan lokal
 * sehingga fitur unggah tetap berjalan.
 */
class GoogleDrive
{
    private array $cfg;
    private ?string $token = null;
    public string $lastError = '';

    private const API   = 'https://www.googleapis.com/drive/v3';
    private const UPL   = 'https://www.googleapis.com/upload/drive/v3';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    /** Ambil konfigurasi efektif: nilai di database menimpa environment variable. */
    public static function fromSettings(array $appCfg): self
    {
        $cfg = $appCfg['drive'];
        foreach ([
            'auth_mode'      => 'gdrive_auth_mode',
            'client_id'      => 'gdrive_client_id',
            'client_secret'  => 'gdrive_client_secret',
            'refresh_token'  => 'gdrive_refresh_token',
            'sa_json'        => 'gdrive_sa_json',
            'root_folder_id' => 'gdrive_root_folder_id',
        ] as $key => $settingKey) {
            $v = Settings::get($settingKey);
            if ($v !== null && $v !== '') {
                $cfg[$key] = $v;
            }
        }
        return new self($cfg);
    }

    public function rootFolderId(): string
    {
        return trim((string) ($this->cfg['root_folder_id'] ?? ''));
    }

    public function isConfigured(): bool
    {
        $mode = $this->cfg['auth_mode'] ?? 'off';
        if ($mode === 'oauth') {
            return $this->cfg['client_id'] && $this->cfg['client_secret'] && $this->cfg['refresh_token'];
        }
        if ($mode === 'service_account') {
            return (bool) trim((string) $this->cfg['sa_json']);
        }
        return false;
    }

    // -----------------------------------------------------------------
    // Token
    // -----------------------------------------------------------------

    public function accessToken(): ?string
    {
        if ($this->token) {
            return $this->token;
        }
        $cached = Settings::get('gdrive_token');
        $exp    = (int) Settings::get('gdrive_token_exp', '0');
        if ($cached && $exp > time() + 60) {
            return $this->token = $cached;
        }

        $mode = $this->cfg['auth_mode'] ?? 'off';
        $res = $mode === 'service_account' ? $this->tokenFromServiceAccount() : $this->tokenFromRefreshToken();
        if (!$res) {
            return null;
        }
        Settings::set('gdrive_token', $res['access_token']);
        Settings::set('gdrive_token_exp', (string) (time() + (int) ($res['expires_in'] ?? 3600)));
        return $this->token = $res['access_token'];
    }

    private function tokenFromRefreshToken(): ?array
    {
        $res = $this->httpForm(self::TOKEN, [
            'client_id'     => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'refresh_token' => $this->cfg['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);
        if (!isset($res['access_token'])) {
            $this->lastError = 'Gagal memperbarui token OAuth: ' . json_encode($res, JSON_UNESCAPED_UNICODE);
            return null;
        }
        return $res;
    }

    private function tokenFromServiceAccount(): ?array
    {
        $key = json_decode((string) $this->cfg['sa_json'], true);
        if (!is_array($key) || empty($key['private_key']) || empty($key['client_email'])) {
            $this->lastError = 'Kunci JSON service account tidak valid.';
            return null;
        }
        $now = time();
        $claim = [
            'iss'   => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud'   => self::TOKEN,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];
        $jwt = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $this->b64(json_encode($claim));
        $sig = '';
        if (!openssl_sign($jwt, $sig, $key['private_key'], 'sha256')) {
            $this->lastError = 'Gagal menandatangani JWT service account.';
            return null;
        }
        $assertion = $jwt . '.' . $this->b64($sig);

        $res = $this->httpForm(self::TOKEN, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ]);
        if (!isset($res['access_token'])) {
            $this->lastError = 'Gagal mengambil token service account: ' . json_encode($res, JSON_UNESCAPED_UNICODE);
            return null;
        }
        return $res;
    }

    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    // -----------------------------------------------------------------
    // Operasi Drive
    // -----------------------------------------------------------------

    /** Cari folder bernama $name di dalam $parentId; buat bila belum ada. Mengembalikan [id, link]. */
    public function ensureFolder(string $name, string $parentId): ?array
    {
        $name = $this->safeName($name);
        $q = sprintf(
            "mimeType='%s' and trashed=false and name='%s' and '%s' in parents",
            self::FOLDER_MIME,
            str_replace("'", "\\'", $name),
            $parentId
        );
        $res = $this->api('GET', '/files?' . http_build_query([
            'q'                         => $q,
            'fields'                    => 'files(id,name,webViewLink)',
            'pageSize'                  => 5,
            'supportsAllDrives'         => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]));
        if (isset($res['files'][0]['id'])) {
            $f = $res['files'][0];
            return ['id' => $f['id'], 'link' => $f['webViewLink'] ?? $this->folderLink($f['id'])];
        }

        $res = $this->api('POST', '/files?' . http_build_query([
            'fields'            => 'id,webViewLink',
            'supportsAllDrives' => 'true',
        ]), [
            'name'     => $name,
            'mimeType' => self::FOLDER_MIME,
            'parents'  => [$parentId],
        ]);
        if (!isset($res['id'])) {
            $this->lastError = 'Gagal membuat folder "' . $name . '": ' . json_encode($res, JSON_UNESCAPED_UNICODE);
            return null;
        }
        $this->shareAnyone($res['id']);
        return ['id' => $res['id'], 'link' => $res['webViewLink'] ?? $this->folderLink($res['id'])];
    }

    /** Jadikan berkas/folder dapat diakses siapa saja yang memiliki tautan. */
    public function shareAnyone(string $fileId): bool
    {
        $res = $this->api('POST', '/files/' . rawurlencode($fileId) . '/permissions?' . http_build_query([
            'supportsAllDrives' => 'true',
            'sendNotificationEmail' => 'false',
        ]), ['role' => 'reader', 'type' => 'anyone']);
        return isset($res['id']) || isset($res['kind']);
    }

    public function uploadFile(string $localPath, string $name, string $mime, string $parentId): ?array
    {
        $token = $this->accessToken();
        if (!$token) {
            return null;
        }
        $meta = json_encode([
            'name'    => $this->safeName($name),
            'parents' => [$parentId],
        ], JSON_UNESCAPED_UNICODE);

        $boundary = '----binwasdal' . bin2hex(random_bytes(8));
        $body  = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n";
        $body .= "--$boundary\r\nContent-Type: $mime\r\n\r\n";
        $body .= file_get_contents($localPath);
        $body .= "\r\n--$boundary--";

        $url = self::UPL . '/files?' . http_build_query([
            'uploadType'        => 'multipart',
            'fields'            => 'id,name,webViewLink,webContentLink,size,mimeType',
            'supportsAllDrives' => 'true',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/related; boundary=' . $boundary,
                'Content-Length: ' . strlen($body),
            ],
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($out === false) {
            $this->lastError = 'cURL: ' . $err;
            return null;
        }
        $res = json_decode($out, true);
        if (!isset($res['id'])) {
            $this->lastError = 'Gagal mengunggah "' . $name . '": ' . $out;
            return null;
        }
        return $res;
    }

    public function deleteFile(string $fileId): bool
    {
        $this->api('DELETE', '/files/' . rawurlencode($fileId) . '?supportsAllDrives=true');
        return true;
    }

    /** Verifikasi konfigurasi: cek token + akses ke folder induk. */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'pesan' => 'Google Drive belum dikonfigurasi.'];
        }
        if (!$this->accessToken()) {
            return ['ok' => false, 'pesan' => $this->lastError ?: 'Gagal mendapatkan access token.'];
        }
        $root = $this->rootFolderId();
        if ($root === '') {
            return ['ok' => false, 'pesan' => 'ID folder induk Google Drive belum diisi.'];
        }
        $res = $this->api('GET', '/files/' . rawurlencode($root) . '?' . http_build_query([
            'fields'            => 'id,name,mimeType,webViewLink',
            'supportsAllDrives' => 'true',
        ]));
        if (!isset($res['id'])) {
            return ['ok' => false, 'pesan' => 'Folder induk tidak dapat diakses: ' . json_encode($res, JSON_UNESCAPED_UNICODE)];
        }
        return ['ok' => true, 'pesan' => 'Terhubung. Folder induk: "' . ($res['name'] ?? '-') . '".', 'folder' => $res];
    }

    public function folderLink(string $id): string
    {
        return 'https://drive.google.com/drive/folders/' . $id . '?usp=sharing';
    }

    public function fileLink(string $id): string
    {
        return 'https://drive.google.com/file/d/' . $id . '/view?usp=sharing';
    }

    /** Ambil ID folder dari tautan Google Drive yang ditempel pengguna. */
    public static function parseFolderId(string $input): string
    {
        $input = trim($input);
        if (preg_match('~/folders/([A-Za-z0-9_-]{10,})~', $input, $m)) {
            return $m[1];
        }
        if (preg_match('~[?&]id=([A-Za-z0-9_-]{10,})~', $input, $m)) {
            return $m[1];
        }
        return $input;
    }

    private function safeName(string $name): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 200, 'UTF-8');
        } else {
            $name = substr($name, 0, 200);
        }
        return $name === '' ? 'Dokumen' : $name;
    }

    // -----------------------------------------------------------------
    // HTTP
    // -----------------------------------------------------------------

    private function api(string $method, string $path, ?array $json = null): array
    {
        $token = $this->accessToken();
        if (!$token) {
            return ['error' => ['message' => $this->lastError ?: 'Tidak ada access token']];
        }
        $ch = curl_init(self::API . $path);
        $headers = ['Authorization: Bearer ' . $token];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 60,
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($out === false) {
            $this->lastError = 'cURL: ' . $err;
            return ['error' => ['message' => $err]];
        }
        if (trim((string) $out) === '') {
            return ['ok' => true];
        }
        $res = json_decode($out, true);
        if (!is_array($res)) {
            return ['error' => ['message' => $out]];
        }
        if (isset($res['error'])) {
            $this->lastError = $res['error']['message'] ?? json_encode($res['error']);
        }
        return $res;
    }

    private function httpForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            return ['error' => $err];
        }
        return json_decode($out, true) ?: ['error' => $out];
    }
}
