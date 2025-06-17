<?php
// ========== details_session.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Получаем ID смены
$session_id = isset($_GET['session_id']) && is_numeric($_GET['session_id'])
    ? (int)$_GET['session_id']
    : null;
if (!$session_id) {
    header('Location: summer_sessions_user.php');
    exit;
}

// Получаем данные смены
$stmt = $conn->prepare(
    'SELECT name, start_date, end_date, age_category, description
     FROM summer_sessions
     WHERE session_id = ?'
);
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}
$stmt->bind_param('i', $session_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header('Location: summer_sessions_user.php');
    exit;
}
$session = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Смена: <?= htmlspecialchars($session['name'], ENT_QUOTES) ?></title>
  <style>
    html, body { height: 100%; margin: 0; }
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      padding: 20px;
    }
    .card {
      width: 90%;
      max-width: 800px;
      background: #fff;
      border-radius: 12px;
      padding: 40px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    h1 {
      margin-bottom: 32px;
      font-size: 36px;
      text-align: center;
      color: #4B0082;
    }
    p {
      margin: 20px 0;
      font-size: 18px;
      color: #333;
    }
    a.btn-back {
      display: inline-block;
      margin-top: 32px;
      width: 100%;
      padding: 16px 0;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff;
      text-decoration: none;
      border-radius: 999px;
      font-weight: 500;
      text-align: center;
      transition: background .3s;
    }
    a.btn-back:hover {
      background: linear-gradient(135deg, #9575CD, #B39DDB);
    }
  </style>
</head>
<body>
  <div class="card">
    <h1><?= htmlspecialchars($session['name'], ENT_QUOTES) ?></h1>

    <p>
      <?= date('d.m.Y', strtotime($session['start_date'])) ?>
      –
      <?= date('d.m.Y', strtotime($session['end_date'])) ?>
    </p>
    <p><?= htmlspecialchars($session['age_category'], ENT_QUOTES) ?></p>
    <p><?= nl2br(htmlspecialchars($session['description'], ENT_QUOTES)) ?></p>

    <a href="summer_sessions_user.php" class="btn-back">← К списку смен</a>
  </div>
</body>
</html>

