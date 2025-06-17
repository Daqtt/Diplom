<?php
// ========== details_contest.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Получаем ID конкурса
$contest_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$contest_id) {
    header('Location: dashboard_user.php');
    exit;
}

// Получаем данные конкурса
$stmt = $conn->prepare(
    'SELECT name, order_number, level, start_date, end_date, description
     FROM contests
     WHERE contest_id = ?'
);
$stmt->bind_param('i', $contest_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header('Location: dashboard_user.php');
    exit;
}
$contest = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Конкурс: <?= htmlspecialchars($contest['name'], ENT_QUOTES) ?></title>
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
    <h1><?= htmlspecialchars($contest['name'], ENT_QUOTES) ?></h1>
    <p><?= htmlspecialchars($contest['order_number'], ENT_QUOTES) ?></p>
    <p><?= htmlspecialchars($contest['level'], ENT_QUOTES) ?></p>
    <p>
      <?= date('d.m.Y', strtotime($contest['start_date'])) ?>
      <?php if ($contest['end_date']): ?>– <?= date('d.m.Y', strtotime($contest['end_date'])) ?><?php endif; ?>
    </p>
    <p><?= nl2br(htmlspecialchars($contest['description'], ENT_QUOTES)) ?></p>
    <a href="dashboard.php" class="btn-back">← На главную</a>
  </div>
</body>
</html>
