<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $u = DB::one('SELECT * FROM users WHERE username = ? AND aktif = 1', [$username]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            return false;
        }
        $_SESSION['user'] = [
            'id'       => (int) $u['id'],
            'username' => $u['username'],
            'nama'     => $u['nama'],
            'role'     => $u['role'],
        ];
        session_regenerate_id(true);
        Log::write($u['username'], 'login', 'Masuk aplikasi');
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Log::write(self::user()['username'], 'logout', 'Keluar aplikasi');
        }
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function username(): string
    {
        return $_SESSION['user']['username'] ?? '';
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function canEdit(): bool
    {
        return in_array(self::user()['role'] ?? '', ['admin', 'editor'], true);
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: ?p=login');
            exit;
        }
    }

    public static function requireEdit(): void
    {
        self::require();
        if (!self::canEdit()) {
            http_response_code(403);
            exit('Akses ditolak: akun Anda hanya dapat melihat.');
        }
    }

    // --- CSRF --------------------------------------------------------

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

class Log
{
    public static function write(string $username, string $aksi, string $keterangan = ''): void
    {
        try {
            DB::insert('activity_log', [
                'username'   => $username,
                'aksi'       => $aksi,
                'keterangan' => $keterangan,
            ]);
        } catch (Throwable $e) {
            // pencatatan log tidak boleh menggagalkan aksi utama
        }
    }
}
