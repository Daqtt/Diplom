<?php
session_start();
require 'db_config.php';
require 'cookie_utils.php';

// Подключаем файл с ключом и IV
$config = include __DIR__ . '/conf/key.php';

// Настройки AES-256-CBC
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY',    hex2bin($config['encryption_key_hex']));
define('ENCRYPTION_IV',     hex2bin($config['encryption_iv_hex']));

/**
 * Шифрует строку
 */
function encrypt_data(string $data): string
{
    return openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}

/**
 * Пытается расшифровать; если не удалось — возвращает оригинал
 */
function try_decrypt(string $ciphertext, string $original): string
{
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false || $decrypted === null ? $original : $decrypted;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($login === '' || $pass === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $conn = get_db_connection();

        // Готовим оба варианта: зашифрованный и «как есть»
        $encLogin = encrypt_data($login);
        $encPass  = encrypt_data($pass);

        // Пытаемся найти пользователя по encrypted ИЛИ по plain
        $stmt = $conn->prepare(
            "SELECT user_id, role
             FROM users
             WHERE (login = ? AND password = ?)
                OR (login = ? AND password = ?)"
        );
        $stmt->bind_param('ssss', $encLogin, $encPass, $login, $pass);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            // Расшифровываем роль (если зашифрована) или оставляем как есть
            $role = try_decrypt($row['role'], $row['role']);

            loginUser((int)$row['user_id'], $role);

            if ($role === 'admin') {
                header('Location: dashboard.php');
            } else {
                header('Location: dashboard_user.php');
            }
            exit;
        }

        $error = 'Неверный логин или пароль.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Вход</title>
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
    .password-container {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      top: 50%;
      right: 12px;
      transform: translateY(-50%);
      font-size: 20px;
      color: #999;
      cursor: pointer;
      user-select: none;
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
    .forgot {
      text-align: center;
      margin-top: 10px;
    }
    .forgot a {
      color: #8A2BE2;
      text-decoration: none;
      font-size: 14px;
    }
    .forgot a:hover { color: #4B0082; }
    .error {
      background: #ffe6e6;
      color: #a00;
      padding: 10px;
      border-radius: 6px;
      font-size: 14px;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Вход</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label for="login">Логин</label>
        <input
          type="text"
          id="login"
          name="login"
          required
          autocomplete="username"
          value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
        >
      </div>
      <div class="form-group password-container">
        <label for="password">Пароль</label>
        <input
          type="password"
          id="password"
          name="password"
          required
          autocomplete="current-password"
        >
        <span class="toggle-password" id="togglePassword">👁</span>
      </div>
      <button type="submit" class="btn">Войти</button>
    </form>
    <div class="forgot">
      <a href="forgot_password.php">Забыли пароль?</a>
    </div>
  </div>
  <script>
    document.getElementById('togglePassword').addEventListener('click', function() {
      const pwd = document.getElementById('password');
      pwd.type = pwd.type === 'password' ? 'text' : 'password';
    });
  </script>
</body>
</html>
