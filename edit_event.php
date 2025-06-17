<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

$event_id = $_GET['id'] ?? null;
if (!$event_id) {
    echo "Мероприятие не найдено.";
    exit;
}

// Получаем данные мероприятия
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    echo "Мероприятие не найдено.";
    exit;
}

$success = $error = "";

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name         = $_POST['name'];
    $description  = $_POST['description'];
    $date_time    = $_POST['event_date_time'];
    $location     = $_POST['location'];
    $age_category = $_POST['age_category'];
    $organizer    = $_POST['organizer'];

    $stmt = $conn->prepare("UPDATE events SET name = ?, description = ?, event_date_time = ?, location = ?, age_category = ?, organizer = ? WHERE event_id = ?");
    $stmt->bind_param("ssssssi", $name, $description, $date_time, $location, $age_category, $organizer, $event_id);

    if ($stmt->execute()) {
        header("Location: events.php");
        exit;
    } else {
        $error = "Ошибка при обновлении.";
    }
    $stmt->close();
}

// Удаление мероприятия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    if ($stmt->execute()) {
        header("Location: events.php");
        exit;
    } else {
        $error = "Ошибка при удалении.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать мероприятие</title>
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
    input, textarea, button {
      width: 100%;
      padding: 14px;
      margin-bottom: 16px;
      border-radius: 12px;
      border: 1px solid #ccc;
      font-size: 16px;
    }
    textarea {
      resize: vertical;
    }
    button {
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: white;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: background 0.3s;
    }
    button:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .delete-btn {
      background: #e53935;
      margin-top: 10px;
    }
    .success, .error {
      padding: 10px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-weight: 500;
    }
    .success {
      background: #dff0d8;
      color: #3c763d;
    }
    .error {
      background: #f2dede;
      color: #a94442;
    }
    a {
      display: inline-block;
      margin-top: 16px;
      text-align: center;
      width: 100%;
      text-decoration: none;
      color: #4B0082;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Редактирование мероприятия</h1>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="name" placeholder="Название" required value="<?= htmlspecialchars($event['name']) ?>">
      <input type="datetime-local" name="event_date_time" required value="<?= date('Y-m-d\TH:i', strtotime($event['event_date_time'])) ?>">
      <input type="text" name="location" placeholder="Место проведения" required value="<?= htmlspecialchars($event['location']) ?>">
      <input type="text" name="age_category" placeholder="Возрастная категория" required value="<?= htmlspecialchars($event['age_category']) ?>">
      <input type="text" name="organizer" placeholder="Организатор" required value="<?= htmlspecialchars($event['organizer']) ?>">
      <textarea name="description" placeholder="Описание" rows="4" required><?= htmlspecialchars($event['description']) ?></textarea>

      <button type="submit" name="save">Сохранить изменения</button>
      <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Удалить мероприятие?')">Удалить</button>
    </form>

    <a href="events.php">← Назад к списку мероприятий</a>
  </div>
</body>
</html>


