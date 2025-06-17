<?php
// ========== add_child_user.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

$conn = get_db_connection();
$error = $success = '';

// Определяем организацию текущего пользователя
$uid = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$uStmt->bind_param('i', $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$userOrg = $uRow['organization_name'] ?? '';
$uStmt->close();
if (!$userOrg) {
    die('Не удалось определить организацию пользователя.');
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name']);
    $birth_date  = $_POST['birth_date'];
    $class       = trim($_POST['class']) ?: '-';
    $association = trim($_POST['association']) ?: '-';

    $stmt = $conn->prepare(
        "INSERT INTO children
         (full_name, birth_date, class, association, organization_name)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssss',
        $full_name,
        $birth_date,
        $class,
        $association,
        $userOrg
    );
    if ($stmt->execute()) {
        $success = 'Ребёнок успешно добавлен.';
    } else {
        $error = 'Ошибка при добавлении: ' . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить ребёнка</title>
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
    input {
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
    <h1>Добавить ребёнка</h1>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <label>ФИО ребёнка
        <input type="text" name="full_name" placeholder="Иванов Иван Иванович" required>
      </label>
      <label>Дата рождения
        <input type="date" name="birth_date" required>
      </label>
      <label>Класс
        <input type="text" name="class" placeholder="Например: 5А">
      </label>
      <label>Объединение
        <input type="text" name="association" placeholder="Название объединения">
      </label>
      <div class="button-group">
        <a href="children_user.php" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>
</body>
</html>
