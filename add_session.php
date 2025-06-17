<?php
// ========== add_session.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn    = get_db_connection();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name']        ?? '');
    $start_date   = $_POST['start_date']       ?? '';
    $end_date     = $_POST['end_date']         ?? '';
    $age_category = trim($_POST['age_category']?? '');
    $description  = trim($_POST['description'] ?? '');

    // Валидация обязательных полей
    if ($name === '' || $start_date === '' || $end_date === '' || $age_category === '') {
        $error = 'Пожалуйста, заполните все обязательные поля.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO summer_sessions
              (name, start_date, end_date, age_category, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'sssss',
            $name,
            $start_date,
            $end_date,
            $age_category,
            $description
        );

        if ($stmt->execute()) {
            $success = 'Летняя смена успешно добавлена.';
            $_POST = [];
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
  <title>Добавить летнюю смену</title>
  <style>
    /* Общий сброс и фон */
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      margin: 0;
      padding: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    /* Контейнер формы */
    .form-container {
      background: rgba(255,255,255,0.95);
      padding: 40px;
      border-radius: 20px;
      width: 100%;
      max-width: 600px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }

    h1 {
      text-align: center;
      color: #4B0082;
      margin-bottom: 30px;
    }

    /* Лейблы и поля */
    label {
      display: block;
      margin-bottom: 16px;
      color: #333;
      font-weight: 500;
    }
    input, textarea {
      width: 100%;
      padding: 12px;
      margin-top: 8px;
      border: 1px solid #ccc;
      border-radius: 12px;
      font-size: 16px;
    }
    textarea { resize: vertical; min-height: 100px; }

    /* flex-контейнер для дат */
    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 16px;
    }
    .form-row label {
      flex: 1;
    }

    /* Кнопки */
    .button-group {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
    .btn-cancel, .btn-main {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 52px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 500;
      color: #fff;
      text-decoration: none;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      transition: background 0.3s;
      cursor: pointer;
    }
    .btn-cancel:hover, .btn-main:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }

    /* Сообщения */
    .success, .error {
      margin-bottom: 16px;
      padding: 10px;
      border-radius: 8px;
      font-weight: 500;
    }
    .success { background: #dff0d8; color: #3c763d; }
    .error   { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить летнюю смену</h1>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Название смены
        <input
          type="text"
          name="name"
          placeholder="Летний лагерь «Солнечный»"
          value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required>
      </label>

      <div class="form-row">
        <label>Дата начала
          <input
            type="date"
            name="start_date"
            value="<?= htmlspecialchars($_POST['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required>
        </label>
        <label>Дата окончания
          <input
            type="date"
            name="end_date"
            value="<?= htmlspecialchars($_POST['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required>
        </label>
      </div>

      <label>Возрастная категория
        <input
          type="text"
          name="age_category"
          placeholder="10–14 лет"
          value="<?= htmlspecialchars($_POST['age_category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required>
      </label>

      <label>Описание смены
        <textarea
          name="description"
          placeholder="Краткое описание программы смены…"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </label>

      <div class="button-group">
        <a href="summer_sessions.php" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>
</body>
</html>


