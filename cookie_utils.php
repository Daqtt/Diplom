<?php
// cookie_utils.php


const COOKIE_DURATION = 6 * 30 * 24 * 60 * 60; // 6 мес. ≈ 15552000 сек.

/**
 * Логинит пользователя:
 *  — регистрирует новую сессию
 *  — кладёт $_SESSION['user_id'], $_SESSION['role']
 *  — ставит httponly-куку user_id на $duration секунд
 */
function loginUser(int $userId, string $role, int $duration = 3600): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role']    = $role;
    setcookie('user_id', (string)$userId, time() + $duration, '/', '', false, true);
}

/**
 * Логаутит пользователя:
 *  — уничтожает сессию и PHPSESSID-куку
 *  — удаляет нашу user_id-куку
 */
function logoutUser(): void {
    // 1) Очистка сессии
    $_SESSION = [];
    session_unset();
    session_destroy();

    // 2) Удаляем PHPSESSID-куку
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    // 3) Удаляем пользовательскую куку
    setcookie('user_id', '', time() - 42000, '/');
    unset($_COOKIE['user_id']);
}
