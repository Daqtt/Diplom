<?php
// ========== edit_session.php ==========
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Получаем ID смены
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$session_id) {
    echo 'Смена не найдена';
    exit;
}

$error   = '';
$success = '';

// Обработка сохранения/удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $name         = trim($_POST['name']        ?? '');
        $start_date   = $_POST['start_date']       ?? '';
        $end_date     = $_POST['end_date']         ?? '';
        $age_category = trim($_POST['age_category']?? '');
        $description  = trim($_POST['description'] ?? '');

        if ($name === '' || $start_date === '' || $end_date === '' || $age_category === '') {
            $error = 'Пожалуйста, заполните все обязательные поля.';
        } else {
            $stmt = $conn->prepare("
                UPDATE summer_sessions SET
                  name         = ?,
                  start_date   = ?,
                  end_date     = ?,
                  age_category = ?,
                  description  = ?
                WHERE session_id = ?
            ");
            $stmt->bind_param(
                'sssssi',
                $name,
                $start_date,
                $end_date,
                $age_category,
                $description,
                $session_id
            );
            if ($stmt->execute()) {
                $success = 'Данные смены успешно обновлены.';
            } else {
                $error = 'Ошибка при сохранении: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM summer_sessions WHERE session_id = ?");
        $stmt->bind_param('i', $session_id);
        $stmt->execute();
        $stmt->close();
        header('Location: summer_sessions.php');
        exit;
    }
}

// Получаем данные смены
$stmt = $conn->prepare("
    SELECT name, start_date, end_date, age_category, description
    FROM summer_sessions
    WHERE session_id = ?
");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
if (!$session) {
    echo 'Смена не найдена';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать смену</title>
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
      width: 100%; max-width: 600px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center; color: #4B0082;
      margin-bottom: 30px;
    }
    label {
      display: block; margin-bottom: 16px;
      color: #333; font-weight: 500;
    }
    input, textarea {
      width: 100%; padding: 12px; margin-top: 8px;
      border: 1px solid #ccc; border-radius: 12px;
      font-size: 16px;
    }
    textarea { resize: vertical; min-height: 100px; }
    .form-row {
      display: flex; gap: 20px; margin-bottom: 16px;
    }
    .form-row label { flex: 1; }
    .button-group {
      display: flex; flex-direction: column; gap: 12px; margin-top: 20px;
    }
    .btn-main, .btn-delete {
      display: block; width: 100%; height: 52px;
      border: none; border-radius: 12px;
      font-size: 16px; font-weight: 500;
      cursor: pointer; color: #fff;
      text-align: center; line-height: 52px;
      transition: background 0.3s;
    }
    .btn-main {
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
    }
    .btn-main:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .btn-delete {
      background: #e74c3c;
    }
    .btn-delete:hover {
      background: #c0392b;
    }
    .back-link {
      text-align: center; margin-top: 20px;
    }
    .back-link a {
      color: #4B0082; text-decoration: none; font-weight: 500;
    }
    .message {
      margin-bottom: 16px; padding: 10px;
      border-radius: 8px; font-weight: 500;
    }
    .message.success { background: #dff0d8; color: #3c763d; }
    .message.error   { background: #f2dede; color: #a94442; }
  </style>
  <script>
    function confirmDeletion() {
      return confirm('Вы уверены? Смена будет удалена безвозвратно.');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать смену</h1>

    <?php if ($success): ?>
      <div class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Название смены
        <input type="text" name="name" required
          value="<?= htmlspecialchars($session['name'], ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <div class="form-row">
        <label>Дата начала
          <input type="date" name="start_date" required
            value="<?= htmlspecialchars($session['start_date'], ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>Дата окончания
          <input type="date" name="end_date" required
            value="<?= htmlspecialchars($session['end_date'], ENT_QUOTES, 'UTF-8') ?>">
        </label>
      </div>

      <label>Возрастная категория
        <input type="text" name="age_category" required
          value="<?= htmlspecialchars($session['age_category'], ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <label>Описание смены
        <textarea name="description"><?= htmlspecialchars($session['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
      </label>

      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDeletion();">Удалить смену</button>
      </div>
    </form>

    <div class="back-link">
      <a href="summer_sessions.php">← Назад к списку смен</a>
    </div>
  </div>
</body>
</html>
