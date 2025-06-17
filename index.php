<?php
// index.php
session_start();
require 'db_config.php';

// 1) Восстанавливаем $_SESSION['user_id'] из куки, если нужно
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id'] = intval($_COOKIE['user_id']);
}

// 2) Если user_id теперь установлен, подтягиваем роль из БД
if (isset($_SESSION['user_id'])) {
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT role FROM users WHERE user_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($role);
        if ($stmt->fetch()) {
            $_SESSION['role'] = $role;
        }
        $stmt->close();
    }
    $conn->close();
}

// 3) Если у нас есть и user_id, и role — редиректим на нужный дашборд
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: dashboard.php');
    } else {
        header('Location: dashboard_user.php');
    }
    exit;
}

// 4) Иначе — нет авторизации, кидаем на форму логина
header('Location: login.php');
exit;



