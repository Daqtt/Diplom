<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Получаем ID мероприятия
$event_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$event_id) {
    header('Location: events_user.php');
    exit;
}

// Получаем данные мероприятия
$stmt = $conn->prepare(
    'SELECT name, description, event_date_time, location, age_category
     FROM events
     WHERE event_id = ?'
);
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}
$stmt->bind_param('i', $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header('Location: events_user.php');
    exit;
}
$event = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Мероприятие: <?= htmlspecialchars($event['name'], ENT_QUOTES) ?></title>
  <style>
    html, body { height:100%; margin:0; }
    body {
      display:flex;
      align-items:center;
      justify-content:center;
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      padding:20px;
    }
    .card {
      width:90%; max-width:800px;
      background:#fff; border-radius:12px;
      padding:40px; box-shadow:0 6px 20px rgba(0,0,0,0.1);
    }
    h1 {
      margin-bottom:32px;
      font-size:36px; text-align:center;
      color:#4B0082;
    }
    p {
      margin:20px 0;
      font-size:18px; color:#333;
    }
    a.btn-back {
      display:inline-block;
      margin-top:32px;
      width:100%; padding:16px 0;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; text-decoration:none;
      border-radius:999px; font-weight:500;
      text-align:center; transition:background .3s;
    }
    a.btn-back:hover {
      background:linear-gradient(135deg,#9575CD,#B39DDB);
    }
  </style>
</head>
<body>
  <div class="card">
    <h1><?= htmlspecialchars($event['name'], ENT_QUOTES) ?></h1>
    <p><?= date('d.m.Y H:i', strtotime($event['event_date_time'])) ?></p>
    <p><?= htmlspecialchars($event['location'], ENT_QUOTES) ?></p>
    <p><?= htmlspecialchars($event['age_category'], ENT_QUOTES) ?></p>
    <p><?= nl2br(htmlspecialchars($event['description'], ENT_QUOTES)) ?></p>
    <a href="events_user.php" class="btn-back">← К списку мероприятий</a>
  </div>
</body>
</html>
