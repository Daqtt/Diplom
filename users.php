<?php
session_start();

// Подключение к БД как было
require 'db_config.php';

// Подключаем файл с ключами и IV
$config = include __DIR__ . '/conf/key.php';

// Настройки шифрования
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY',    hex2bin($config['encryption_key_hex']));
define('ENCRYPTION_IV',     hex2bin($config['encryption_iv_hex']));

/**
 * Пытается расшифровать, если не получилось — возвращает оригинал
 */
function try_decrypt(string $ciphertext, string $original): string
{
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false || $decrypted === null
         ? $original
         : $decrypted;
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Получаем соединение и всех пользователей
$conn   = get_db_connection();
$result = $conn->query("
    SELECT user_id, full_name, organization_name, role, login, email
    FROM users
    ORDER BY full_name
");
if (!$result) {
    die('Ошибка выборки пользователей: ' . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Список пользователей</title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      margin: 0; padding: 20px; color: #333;
      display: flex; justify-content: center;
    }
    .container {
      background: rgba(255,255,255,0.95);
      padding: 30px; border-radius: 20px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
      width: 100%; max-width: 1200px; min-height: 1000px;
    }
    .header {
      display: flex; align-items: center;
      justify-content: space-between; margin-bottom: 20px;
    }
    h1 {
      margin: 0; flex: 1; text-align: center;
      color: #4B0082; font-size: 24px;
    }
    .btn-add, .btn-back {
      display: inline-block; padding: 12px 24px;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff; text-decoration: none; border-radius: 999px;
      font-weight: 500; transition: background .3s, transform .2s;
    }
    .btn-add:hover, .btn-back:hover {
      background: linear-gradient(135deg, #9575CD, #B39DDB);
      transform: scale(1.05);
    }
    .search-bar { margin-bottom: 20px; }
    .search-bar input {
      width: 100%; padding: 10px; border: 1px solid #ccc;
      border-radius: 6px; font-size: 16px;
    }
    table {
      width: 100%; border-collapse: collapse; margin-top: 10px;
    }
    th, td {
      padding: 12px; border: 1px solid #eee; text-align: left;
    }
    thead th, tbody tr { background-color: #fff; }
    tbody tr:hover {
      background: #f9f9ff; cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="dashboard.php" class="btn-back">← Главная</a>
      <h1>Список пользователей</h1>
      <a href="add_user.php" class="btn-add">➕ Добавить пользователя</a>
    </div>

    <div class="search-bar">
      <input
        type="text"
        id="searchInput"
        placeholder="Поиск по Ф.И.О."
        oninput="filterUsers()">
    </div>

    <table>
      <thead>
        <tr>
          <th>Ф.И.О.</th>
          <th>Организация</th>
          <th>Роль</th>
          <th>Логин</th>
          <th>Email</th>
        </tr>
      </thead>
      <tbody id="usersTable">
        <?php while ($user = $result->fetch_assoc()): ?>
          <?php
            // Декодируем, либо оставляем оригинал
            $fullName = try_decrypt($user['full_name'],         $user['full_name']);
            $orgName  = try_decrypt($user['organization_name'], $user['organization_name']);
            $role     = try_decrypt($user['role'],              $user['role']);
            $login    = try_decrypt($user['login'],             $user['login']);
            $email    = try_decrypt($user['email'],             $user['email']);
          ?>
          <tr data-id="<?= $user['user_id'] ?>">
            <td><?= htmlspecialchars($fullName, ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($orgName,  ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($role,     ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($login,    ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($email,    ENT_QUOTES) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <script>
    const searchInput = document.getElementById('searchInput');
    const rows        = Array.from(document.querySelectorAll('#usersTable tr'));

    function filterUsers() {
      const filter = searchInput.value.toLowerCase();
      rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        row.style.display = name.includes(filter) ? '' : 'none';
      });
    }

    rows.forEach(row => {
      row.addEventListener('click', () => {
        window.location.href = `edit_user.php?id=${row.dataset.id}`;
      });
    });
  </script>
</body>
</html>

