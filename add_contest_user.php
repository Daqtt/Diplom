<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$error = $success = "";

// Определяем организацию пользователя
if (!isset($_SESSION['organization_name']) || empty($_SESSION['organization_name'])) {
    // Если в сессии нет, берём из таблицы users
    $uid = $_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
    $uStmt->bind_param('i', $uid);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $org = $uRow['organization_name'] ?? '';
    $uStmt->close();
    // Сохраняем в сессии для последующих запросов
    $_SESSION['organization_name'] = $org;
} else {
    $org = $_SESSION['organization_name'];
}

if (empty($org)) {
    die('Не удалось определить организацию пользователя.');
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name         = trim($_POST['name']);
    $order_number = trim($_POST['order_number']);
    $level        = trim($_POST['level']);
    $age_category = trim($_POST['age_category']);
    $description  = trim($_POST['description']);
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'] ?: null;

    $stmt = $conn->prepare(
        "INSERT INTO contests
         (name, order_number, level, age_category, description, start_date, end_date, organization_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        $error = 'Ошибка подготовки запроса: ' . $conn->error;
    } else {
        $stmt->bind_param(
            'ssssssss',
            $name,
            $order_number,
            $level,
            $age_category,
            $description,
            $start_date,
            $end_date,
            $org
        );
        if ($stmt->execute()) {
            $success = 'Конкурс успешно добавлен.';
        } else {
            $error = 'Ошибка при добавлении конкурса: ' . $stmt->error;
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
  <title>Добавить конкурс</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      color: #333;
    }
    .form-container {
      background: rgba(255,255,255,0.95);
      padding: 40px;
      border-radius: 20px;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center;
      margin-bottom: 30px;
      color: #4B0082;
    }
    input, textarea, button {
      width: 100%;
      padding: 14px;
      margin-bottom: 16px;
      border-radius: 12px;
      border: 1px solid #ccc;
      font-size: 16px;
      font-family: inherit;
    }
    textarea { resize: vertical; }
    .date-group {
      display: flex;
      gap: 10px;
    }
    .date-group input { flex: 1; }
    .button-group {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .btn-main, .btn-cancel {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 52px;
      font-size: 16px;
      font-weight: 500;
      text-decoration: none;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff;
      transition: background 0.3s;
    }
    .btn-main:hover, .btn-cancel:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .success, .error {
      padding: 10px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-weight: 500;
      text-align: center;
    }
    .success { background: #dff0d8; color: #3c763d; }
    .error { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить конкурс</h1>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success, ENT_QUOTES) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="name" placeholder="Название конкурса" required>
      <input type="text" name="order_number" placeholder="Номер приказа">
      <input type="text" name="level" placeholder="Уровень мероприятия" required>
      <div class="date-group">
        <input type="date" name="start_date" required>
        <input type="date" name="end_date">
      </div>
      <input type="text" name="age_category" placeholder="Возрастная категория" required>
      <textarea name="description" rows="4" placeholder="Описание конкурса"></textarea>
      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить</button>
        <a href="contests_user.php" class="btn-cancel">Отмена</a>
      </div>
    </form>
  </div>
</body>
</html>