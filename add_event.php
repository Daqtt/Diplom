<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$error = $success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $date_time = $_POST['event_date_time'];
    $location = $_POST['location'];
    $age_category = $_POST['age_category'];
    $organizer = $_POST['organizer'];

    $stmt = $conn->prepare("
        INSERT INTO events 
            (name, description, event_date_time, location, age_category, organizer) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $name, $description, $date_time, $location, $age_category, $organizer);

    if ($stmt->execute()) {
        $success = "Мероприятие успешно добавлено.";
    } else {
        $error = "Ошибка при добавлении.";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить мероприятие</title>
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
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

    input, textarea {
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

    .button-group {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .btn-main,
    .btn-cancel {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 52px;
      padding: 0 14px;
      font-size: 16px;
      font-weight: 500;
      text-decoration: none;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: background 0.3s;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff;
    }

    .btn-main:hover,
    .btn-cancel:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
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
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить мероприятие</h1>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="name" placeholder="Название" required>
      <input type="datetime-local" name="event_date_time" required>
      <input type="text" name="location" placeholder="Место проведения" required>
      <input type="text" name="age_category" placeholder="Возрастная категория" required>
      <input type="text" name="organizer" placeholder="Организатор" required>
      <textarea name="description" placeholder="Описание" rows="4" required></textarea>

      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить</button>
        <a href="events.php" class="btn-cancel">Отмена</a>
      </div>
    </form>
  </div>
</body>
</html>




