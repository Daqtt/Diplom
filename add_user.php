<?php
// ========== add_user.php ==========
session_start();


require_once 'db_config.php';
// Подключаем защищённый конфиг с ключом и IV
$config = include __DIR__ . '/conf/key.php'; 

// ====== Настройки шифрования ======
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY',    hex2bin($config['encryption_key_hex']));
define('ENCRYPTION_IV',     hex2bin($config['encryption_iv_hex']));

/**
 * Шифрует строку данным ключом и IV
 */
function encrypt_data(string $data): string
{
    return openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}

/**
 * Расшифровывает строку 
 */
function decrypt_data(string $data): string
{
    return openssl_decrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn    = get_db_connection();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сырые значения из формы
    $raw_full_name         = trim($_POST['full_name'] ?? '');
    $raw_organization_name = trim($_POST['organization_name'] ?? '');
    $raw_role              = $_POST['role'] ?? '';
    $raw_login             = trim($_POST['login'] ?? '');
    $raw_password          = $_POST['password'] ?? '';
    $raw_email             = trim($_POST['email'] ?? '');

    // Валидация
    if ($raw_full_name === ''
        || $raw_organization_name === ''
        || !in_array($raw_role, ['user','admin'], true)
        || $raw_login === ''
        || $raw_password === ''
        || $raw_email === ''
    ) {
        $error = 'Пожалуйста, заполните все поля корректно.';
    } else {
        // Шифруем данные
        $full_name         = encrypt_data($raw_full_name);
        $organization_name = encrypt_data($raw_organization_name);
        $role              = encrypt_data($raw_role);
        $login             = encrypt_data($raw_login);
        $password          = encrypt_data($raw_password);
        $email             = encrypt_data($raw_email);

        // Вставка в базу
        $stmt = $conn->prepare("
            INSERT INTO users
              (full_name, organization_name, role, login, password, email)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'ssssss',
            $full_name,
            $organization_name,
            $role,
            $login,
            $password,
            $email
        );

        if ($stmt->execute()) {
            $success = 'Пользователь успешно добавлен.';
            $_POST   = []; // очистить форму
        } else {
            $error = 'Ошибка при добавлении: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить пользователя</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      margin: 0; padding: 20px;
      display: flex; justify-content: center; align-items: center;
      min-height: 100vh;
    }
    .form-container {
      background: rgba(255,255,255,0.95);
      padding: 40px; border-radius: 20px;
      max-width: 600px; width: 100%;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center; margin-bottom: 30px;
      color: #4B0082;
    }
    label {
      display: block; margin-bottom: 16px;
      color: #333; font-weight: 500;
    }
    input, select {
      width: 100%; padding: 14px; margin-top: 8px;
      border: 1px solid #ccc; border-radius: 12px;
      font-size: 16px;
    }
    .button-group {
      display: flex; gap: 10px; margin-top: 20px;
    }
    .btn-cancel, .btn-main {
      flex: 1; display: flex; align-items: center;
      justify-content: center; height: 52px;
      font-size: 16px; font-weight: 500;
      border-radius: 12px; cursor: pointer;
      text-decoration: none; border: none;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff; transition: background 0.3s;
    }
    .btn-cancel:hover, .btn-main:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .success, .error {
      margin-bottom: 16px; padding: 10px;
      border-radius: 8px; font-weight: 500;
    }
    .success { background: #dff0d8; color: #3c763d; }
    .error   { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить пользователя</h1>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Ф.И.О.
        <input type="text" name="full_name" placeholder="Иванов Иван Иванович"
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
      </label>

      <label>Организация
        <input type="text" name="organization_name" placeholder="Название организации"
               value="<?= htmlspecialchars($_POST['organization_name'] ?? '') ?>" required>
      </label>

      <label>Роль
        <select name="role" required>
          <option value="">— Выберите роль —</option>
          <option value="user"  <?= (($_POST['role'] ?? '') === 'user')  ? 'selected' : '' ?>>Пользователь</option>
          <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Администратор</option>
        </select>
      </label>

      <label>Логин
        <input type="text" name="login" placeholder="login123"
               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
      </label>

      <label>Пароль
        <input type="password" name="password" required>
      </label>

      <label>E-mail
        <input type="email" name="email" placeholder="email@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </label>

      <div class="button-group">
        <a href="users.php" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>
</body>
</html>
