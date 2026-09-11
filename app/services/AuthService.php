<?php

declare(strict_types=1);

class AuthService
{
    private DatabaseService $db;

    public function __construct(?DatabaseService $db = null)
    {
        $this->db = $db ?: new DatabaseService();
    }

    public function login(string $email, string $password): array
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM user WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if ((int) $user['status'] !== 1) {
            return ['success' => false, 'message' => 'This account is disabled.'];
        }

        if (!verifyPassword($password, (string) $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        $allowedTypes = appConfig()['auth']['allowed_user_types'];
        if (!in_array((int) $user['user_type'], $allowedTypes, true)) {
            return ['success' => false, 'message' => 'This user type is not allowed to access this portal.'];
        }

        $this->startSession($user);
        return ['success' => true, 'user' => $user];
    }

    public function startSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_type'] = (int) $user['user_type'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = getUserFullName($user);
        $_SESSION['is_logged_in'] = true;

        $this->db->execute(
            'UPDATE user SET last_login_at = CURRENT_TIMESTAMP, last_login_ip = ? WHERE id = ?',
            [getClientIpAddress(), (int) $user['id']]
        );
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['is_logged_in']) && !empty($_SESSION['user_id']);
    }
}

function verifyPassword(string $plainPassword, string $storedHash): bool
{
    if ($storedHash === '') {
        return false;
    }

    if (password_get_info($storedHash)['algo'] !== null) {
        return password_verify($plainPassword, $storedHash);
    }

    if (preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1) {
        return hash_equals($storedHash, md5($plainPassword));
    }

    return false;
}
