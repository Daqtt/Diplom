<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// Проверка параметров
if (!isset($_GET['id'], $_GET['event_id']) || !is_numeric($_GET['id']) || !is_numeric($_GET['event_id'])) {
    header("Location: event_participants_user.php");
    exit;
}
$participant_id = (int)$_GET['id'];
$event_id       = (int)$_GET['event_id'];

$error = $success = '';

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delStmt = $conn->prepare("DELETE FROM event_participants WHERE participant_id = ?");
    $delStmt->bind_param('i', $participant_id);
    if ($delStmt->execute()) {
        header("Location: event_participants_user.php?event_id={$event_id}");
        exit;
    } else {
        $error = "Ошибка при удалении: " . $delStmt->error;
    }
    $delStmt->close();
}

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $class         = trim($_POST['class']);
    $association   = trim($_POST['association']);
    $responsible_id= (int)$_POST['responsible_id'];

    $upd = $conn->prepare("UPDATE event_participants SET class = ?, association = ?, responsible_id = ? WHERE participant_id = ?");
    $upd->bind_param('ssii', $class, $association, $responsible_id, $participant_id);
    if ($upd->execute()) {
        $success = "Данные обновлены.";
    } else {
        $error = "Ошибка при обновлении: " . $upd->error;
    }
    $upd->close();
}

// Получение данных участника
$stmt = $conn->prepare("
    SELECT ep.class, ep.association, ep.responsible_id,
           c.full_name AS child_name
    FROM event_participants ep
    LEFT JOIN children c ON ep.child_id = c.child_id
    WHERE ep.participant_id = ?
");
$stmt->bind_param('i', $participant_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Список руководителей
$user_id = $_SESSION['user_id'];
$orgStmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$orgStmt->bind_param('i', $user_id);
$orgStmt->execute();
$organization = $orgStmt->get_result()->fetch_assoc()['organization_name'] ?? '';
$orgStmt->close();

$respsStmt = $conn->prepare("SELECT responsible_id, teacher_name FROM gifted_responsibles WHERE organization_name = ?");
$respsStmt->bind_param('s', $organization);
$respsStmt->execute();
$responsibles = $respsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$respsStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать участника</title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      margin: 0;
      padding: 40px 20px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      color: #333;
    }
    .form-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 40px;
      border-radius: 20px;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
    }
    h1 {
      text-align: center;
      margin-bottom: 30px;
      color: #4B0082;
    }
    input, select, button, a.back-link {
      width: 100%;
      padding: 14px;
      margin-bottom: 16px;
      border-radius: 12px;
      border: 1px solid #ccc;
      font-size: 16px;
      box-sizing: border-box;
    }
    button {
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: white;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: background 0.3s, transform 0.2s;
    }
    button:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
      transform: scale(1.05);
    }
    .delete-btn {
      background: #e53935;
    }
    .delete-btn:hover {
      background: #c62828;
      transform: scale(1.05);
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
    a.back-link {
      display: inline-block;
      text-decoration: none;
      color: #4B0082;
      background: #fff;
      text-align: center;
      font-weight: 500;
      transition: background 0.3s, transform 0.2s;
    }
    a.back-link:hover {
      background: #f0f0f0;
      transform: scale(1.02);
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать участника</h1>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
  <select name="responsible_id" required>
    <?php foreach ($responsibles as $r): ?>
      <option value="<?= $r['responsible_id'] ?>" <?= $r['responsible_id'] == $row['responsible_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($r['teacher_name'], ENT_QUOTES) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <input type="text" value="<?= htmlspecialchars($row['child_name'], ENT_QUOTES) ?>" readonly placeholder="Ф.И.О. ребёнка">
  <input type="text" name="class" value="<?= htmlspecialchars($row['class'], ENT_QUOTES) ?>" placeholder="Класс">
  <input type="text" name="association" value="<?= htmlspecialchars($row['association'], ENT_QUOTES) ?>" placeholder="Объединение">

  <button type="submit" name="save">Сохранить</button>
  <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Удалить участника?');">Удалить</button>
</form>


    <a href="event_participants_user.php?event_id=<?= $event_id ?>" class="back-link">← Назад</a>
  </div>
</body>
</html>
