<?php
// forgot_password.php
session_start();
require 'db_config.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);

    $conn = get_db_connection();
    // 1) Найти пользователя по логину и email
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE login = ? AND email = ?");
    $stmt->bind_param("ss", $login, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // 2) Генерируем новый пароль
        $newPassPlain = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        // 3) Хешируем его
        $newHash = password_hash($newPassPlain, PASSWORD_DEFAULT);

        // 4) Обновляем хеш в БД
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newHash, $user['user_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        // 5) Отправляем письмо
        $subject = 'Ваш новый пароль';
        $message = <<<EOT
Здравствуйте, {$login}!

Ваш пароль был сброшен.
Новый временный пароль: {$newPassPlain}

Пожалуйста, авторизуйтесь и при необходимости смените пароль в настройках.
EOT;

        $headers  = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8";

        if (mail($email, $subject, $message, $headers)) {
            $success = 'Новый пароль отправлен на вашу почту.';
        } else {
            $error = 'Не удалось отправить письмо. Попробуйте позже.';
        }
    } else {
        $conn->close();
        $error = 'Пользователь с такими данными не найден.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Восстановление пароля</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #8A2BE2, #4B0082);
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        color: #fff;
    }
    .form-container {
        background: #fff;
        color: #333;
        padding: 40px 30px;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        width: 360px;
        max-width: 100%;
    }
    .form-container h1 {
        color: #8A2BE2;
        margin-bottom: 20px;
        text-align: center;
    }
    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
    }
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }
    .btn {
        width: 100%;
        padding: 12px;
        background: #8A2BE2;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background .3s;
        margin-top: 10px;
    }
    .btn:hover { background: #4B0082; }
    .message {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 6px;
        font-size: 14px;
        text-align: center;
    }
    .error { background: #ffe6e6; color: #a00; }
    .success { background: #e6ffe6; color: #080; }
    .back {
        text-align: center;
        margin-top: 15px;
    }
    .back a {
        color: #8A2BE2;
        text-decoration: none;
    }
    .back a:hover { color: #4B0082; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Восстановление пароля</h1>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="login">Логин</label>
        <input type="text" id="login" name="login" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn">Сбросить пароль</button>
    </form>

    <div class="back">
      <a href="index.php">← Назад на страницу входа</a>
    </div>
  </div>
</body>
</html>
