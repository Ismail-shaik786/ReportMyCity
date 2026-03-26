<?php
/**
 * ReportMyCity — JWT Middleware 
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthMiddleware {
    private static function getSecret() {
        return $_ENV['JWT_SECRET'] ?? 'default_reportmycity_secret_key_change_me';
    }

    /**
     * Generate JWT
     */
    public static function generateToken($userData) {
        $payload = [
            'iss' => 'reportmycity',
            'aud' => 'reportmycity_users',
            'iat' => time(),
            'exp' => time() + (86400 * 30), // 30 days
            'data' => $userData
        ];
        return JWT::encode($payload, self::getSecret(), 'HS256');
    }

    /**
     * Verify JWT and return data or false
     */
    public static function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecret(), 'HS256'));
            return (array) $decoded->data;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if current request has valid session or token
     * Sets PHP $_SESSION if valid token found and no session exists (bridge)
     */
    public static function checkAccess($allowedRoles = []) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = null;

        // 1. Check Bearer Token (API Standard)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $user = self::verifyToken($matches[1]);
        } 
        // 2. Check Cookie (Web Standard for this app)
        elseif (isset($_COOKIE['auth_token'])) {
            $user = self::verifyToken($_COOKIE['auth_token']);
        }

        // 3. Fallback to PHP Session (Existing UI)
        if (!$user && isset($_SESSION['user_id'])) {
            $user = [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'] ?? 'citizen',
                'name' => $_SESSION['user_name'] ?? ''
            ];
        }

        if (!$user) {
            return false; // Not authenticated
        }

        // Hydrate session if logging in via API
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'] ?? 'User';
        }

        if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
            return false; // Not authorized
        }

        return $user;
    }

    /**
     * Terminate auth
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        setcookie('auth_token', '', time() - 3600, '/');
    }
}
