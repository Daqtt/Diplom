<?php
// ========== add_gifted_responsible.php (пользователь) ==========
session_start();
require 'db_config.php';

// Только авторизованные пользователи
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// Узнаём организацию текущего пользователя
$stmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userOrg = $userRow['organization_name'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Собираем данные из формы
    $teacher_name           = trim($_POST['teacher_name']);
    $position               = trim($_POST['position']);
    $education_level        = trim($_POST['education_level']);
    $qualification_category = trim($_POST['qualification_category']);
    $experience_years       = (int)$_POST['experience_years'];
    $qualification_data     = trim($_POST['qualification_data']);
    $awards                 = trim($_POST['awards']);

    // Вставляем, organization_name берём из $userOrg
    $sql = "INSERT INTO gifted_responsibles
               (teacher_name, organization_name, education_level, qualification_category,
                experience_years, qualification_data, position, awards)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssisss',
        $teacher_name,
        $userOrg,
        $education_level,
        $qualification_category,
        $experience_years,
        $qualification_data,
        $position,
        $awards
    );
    if ($stmt->execute()) {
        $success = "Запись успешно добавлена.";
    } else {
        $error = "Ошибка при добавлении: " . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить ответственного</title>
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
      font-size: 24px; text-align: center;
      margin-bottom: 30px; color: #4B0082;
    }
    label {
      display: block; margin-bottom: 16px;
      color: #333; font-weight: 500;
    }
    input, textarea {
      width: 100%; padding: 14px; margin-top: 8px;
      border: 1px solid #ccc; border-radius: 12px;
      font-size: 16px; resize: vertical;
    }
    .button-group {
      display: flex; gap: 10px; margin-top: 20px;
    }
    .btn-main, .btn-cancel {
      flex: 1; display: flex; align-items: center; justify-content: center;
      height: 52px; font-size: 16px; font-weight: 500;
      border-radius: 12px; cursor: pointer; text-decoration: none; border: none;
      background: linear-gradient(135deg, #8A2BE2, #4B0082); color: #fff;
      transition: background 0.3s;
    }
    .btn-main:hover, .btn-cancel:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .message {
      margin-bottom: 20px; padding: 10px; border-radius: 8px;
      font-weight: 500;
    }
    .message.success { background: #dff0d8; color: #3c763d; }
    .message.error   { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить ответственного</h1>

    <?php if ($success): ?>
      <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>ФИО преподавателя
        <input type="text" name="teacher_name" required>
      </label>
      <label>Должность
        <input type="text" name="position" required>
      </label>
      <label>Уровень образования
        <input type="text" name="education_level">
      </label>
      <label>Квалификационная категория
        <input type="text" name="qualification_category">
      </label>
      <label>Стаж (лет)
        <input type="number" name="experience_years" min="0">
      </label>
      <label>Сведения о квалификации
        <textarea name="qualification_data" rows="4"></textarea>
      </label>
      <label>Награды
        <textarea name="awards" rows="3"></textarea>
      </label>
      <div class="button-group">
        <a href="gifted_responsibles_user.php" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>
</body>
</html>
