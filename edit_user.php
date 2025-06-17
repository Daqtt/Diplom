<?php
// ========== edit_user.php ==========
session_start();
require_once 'db_config.php';

// Подключаем файл с ключами и IV
$config = include __DIR__ . '/conf/key.php';

// Настройки AES-256-CBC
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY',    hex2bin($config['encryption_key_hex']));
define('ENCRYPTION_IV',     hex2bin($config['encryption_iv_hex']));

function encrypt_data(string $data): string
{
    return openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}

function try_decrypt(string $ciphertext, string $original): string
{
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false ? $original : $decrypted;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn    = get_db_connection();
$error   = '';
$success = '';

// Получаем ID пользователя
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    echo 'Пользователь не найден';
    exit;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $raw_full_name         = trim($_POST['full_name'] ?? '');
        $raw_organization_name = trim($_POST['organization_name'] ?? '');
        $raw_role              = $_POST['role'] ?? '';
        $raw_login             = trim($_POST['login'] ?? '');
        $raw_password          = $_POST['password'] ?? '';
        $raw_email             = trim($_POST['email'] ?? '');

        if (
            $raw_full_name === '' ||
            $raw_organization_name === '' ||
            !in_array($raw_role, ['user','admin'], true) ||
            $raw_login === '' ||
            $raw_email === ''
        ) {
            $error = 'Пожалуйста, заполните все поля корректно.';
        } else {
            // Определяем, шифруем новый пароль или оставляем старый
            if ($raw_password === '') {
                // Берём старый зашифрованный пароль из БД
                $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($enc_password);
                $stmt->fetch();
                $stmt->close();
            } else {
                $enc_password = encrypt_data($raw_password);
            }

            // Шифруем остальные поля
            $enc_full_name         = encrypt_data($raw_full_name);
            $enc_organization_name = encrypt_data($raw_organization_name);
            $enc_role              = encrypt_data($raw_role);
            $enc_login             = encrypt_data($raw_login);
            $enc_email             = encrypt_data($raw_email);

            $stmt = $conn->prepare(
                "UPDATE users SET
                   full_name         = ?,
                   organization_name = ?,
                   role              = ?,
                   login             = ?,
                   password          = ?,
                   email             = ?
                 WHERE user_id = ?"
            );
            $stmt->bind_param(
                'ssssssi',
                $enc_full_name,
                $enc_organization_name,
                $enc_role,
                $enc_login,
                $enc_password,
                $enc_email,
                $user_id
            );
            if ($stmt->execute()) {
                $success = 'Данные пользователя успешно обновлены.';
            } else {
                $error = 'Ошибка при сохранении: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: users.php");
        exit;
    }
}

// Получаем текущие (зашифрованные) данные
$stmt = $conn->prepare(
    "SELECT full_name, organization_name, role, login, email
     FROM users
     WHERE user_id = ?"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    echo 'Пользователь не найден';
    exit;
}

// Дешифруем для отображения
$dec_full_name         = try_decrypt($user['full_name'],         $user['full_name']);
$dec_organization_name = try_decrypt($user['organization_name'], $user['organization_name']);
$dec_role              = try_decrypt($user['role'],              $user['role']);
$dec_login             = try_decrypt($user['login'],             $user['login']);
$dec_email             = try_decrypt($user['email'],             $user['email']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать пользователя</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #8A2BE2, #4B0082); margin:0; padding:20px; display:flex; justify-content:center; align-items:center; min-height:100vh; }
    .form-container { background: rgba(255,255,255,0.95); padding:40px; border-radius:20px; max-width:600px; width:100%; box-shadow:0 12px 40px rgba(0,0,0,0.2); }
    h1 { text-align:center; margin-bottom:30px; color:#4B0082; }
    label { display:block; margin-bottom:16px; color:#333; font-weight:500; }
    input, select { width:100%; padding:14px; margin-top:8px; border:1px solid #ccc; border-radius:12px; font-size:16px; }
    .button-group { display:flex; flex-direction:column; gap:12px; margin-top:20px; }
    .btn-main { display:block; width:100%; height:52px; background:linear-gradient(135deg, #8A2BE2, #4B0082); color:#fff; border:none; border-radius:12px; font-size:16px; font-weight:500; cursor:pointer; text-align:center; line-height:52px; transition:background 0.3s; }
    .btn-main:hover { background:linear-gradient(135deg, #B39DDB, #9575CD); }
    .btn-delete { display:block; width:100%; height:52px; background:#e74c3c; color:#fff; border:none; border-radius:12px; font-size:16px; font-weight:500; cursor:pointer; text-align:center; line-height:52px; transition:background 0.3s; }
    .btn-delete:hover { background:#c0392b; }
    .back-link { text-align:center; margin-top:30px; }
    .back-link a { color:#4B0082; text-decoration:none; font-weight:500; }
    .message { margin-bottom:20px; padding:10px; border-radius:8px; font-weight:500; }
    .message.success { background:#dff0d8; color:#3c763d; }
    .message.error   { background:#f2dede; color:#a94442; }
  </style>
  <script>
    function confirmDeletion() {
      return confirm('Вы уверены? Пользователь будет удалён безвозвратно.');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать пользователя</h1>
    <?php if ($success): ?><div class="message success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error):   ?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <form method="post">
      <label>Ф.И.О.
        <input type="text" name="full_name" value="<?=htmlspecialchars($dec_full_name, ENT_QUOTES)?>" required>
      </label>

      <label>Организация
        <input type="text" name="organization_name" value="<?=htmlspecialchars($dec_organization_name, ENT_QUOTES)?>" required>
      </label>

      <label>Роль
        <select name="role" required>
          <option value="user"  <?= $dec_role === 'user'  ? 'selected' : '' ?>>Пользователь</option>
          <option value="admin" <?= $dec_role === 'admin' ? 'selected' : '' ?>>Администратор</option>
        </select>
      </label>

      <label>Логин
        <input type="text" name="login" value="<?=htmlspecialchars($dec_login, ENT_QUOTES)?>" required>
      </label>

      <label>Пароль
        <input type="password" name="password" placeholder="Оставьте пустым, чтобы не менять">
      </label>

      <label>E-mail
        <input type="email" name="email" value="<?=htmlspecialchars($dec_email, ENT_QUOTES)?>" required>
      </label>

      <div class="button-group">
        <button type="submit" name="save"   class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDeletion();">Удалить пользователя</button>
      </div>
    </form>

    <div class="back-link">
      <a href="users.php">← Назад к списку пользователей</a>
    </div>
  </div>
</body>
</html>

