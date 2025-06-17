<?php
// ========== edit_child_user.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}
$conn = get_db_connection();

// Определяем организацию пользователя
$uid = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$uStmt->bind_param('i', $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$userOrg = $uRow['organization_name'] ?? '';
$uStmt->close();
if (!$userOrg) die('Не удалось определить организацию пользователя.');

// Получаем ID ребёнка из GET
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$child_id) die('Ребёнок не найден');

$error = $success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $full_name   = trim($_POST['full_name']);
        $birth_date  = $_POST['birth_date'];
        $class       = trim($_POST['class']) ?: '-';
        $association = trim($_POST['association']) ?: '-';

        // Обновление, без изменения организации
        $stmt = $conn->prepare(
            "UPDATE children
             SET full_name = ?, birth_date = ?, class = ?, association = ?
             WHERE child_id = ? AND organization_name = ?"
        );
        $stmt->bind_param(
            'sssis',
            $full_name,
            $birth_date,
            $class,
            $association,
            $child_id,
            $userOrg
        );
        if ($stmt->execute()) {
            $success = 'Данные ребёнка успешно обновлены.';
        } else {
            $error = 'Ошибка при сохранении: ' . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['delete'])) {
        // Удаляем только если организация совпадает
        $stmt = $conn->prepare("DELETE FROM children WHERE child_id = ? AND organization_name = ?");
        $stmt->bind_param('is', $child_id, $userOrg);
        $stmt->execute();
        $stmt->close();
        header("Location: children_user.php");
        exit;
    }
}

// Получаем текущие данные ребёнка, только если из той же организации
$stmt = $conn->prepare(
    "SELECT full_name, birth_date, class, association
     FROM children WHERE child_id = ? AND organization_name = ?"
);
$stmt->bind_param('is', $child_id, $userOrg);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$child) die('Ребёнок не найден или организация не совпадает.');
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать ребёнка</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0; padding:20px;
      display:flex; justify-content:center; align-items:center;
      min-height:100vh;
    }
    .form-container {
      background:rgba(255,255,255,0.95);
      padding:40px; border-radius:20px;
      max-width:600px; width:100%;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
    }
    h1 { text-align:center; margin-bottom:30px; color:#4B0082; }
    label { display:block; margin-bottom:16px; color:#333; font-weight:500; }
    input { width:100%; padding:14px; margin-top:8px; border:1px solid #ccc; border-radius:12px; font-size:16px; }
    .button-group { display:flex; flex-direction:column; gap:12px; margin-top:20px; }
    .btn-main, .btn-delete { width:100%; height:52px; border:none; border-radius:12px; font-size:16px; font-weight:500; cursor:pointer; text-align:center; line-height:52px; transition:background 0.3s, transform 0.2s; }
    .btn-main { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; }
    .btn-main:hover { background:linear-gradient(135deg,#B39DDB,#9575CD); transform:scale(1.02); }
    .btn-delete { background:#e74c3c; color:#fff; }
    .btn-delete:hover { background:#c0392b; transform:scale(1.02); }
    .message { margin-bottom:20px; padding:10px; border-radius:8px; font-weight:500; text-align:center; }
    .message.success { background:#dff0d8; color:#3c763d; }
    .message.error { background:#f2dede; color:#a94442; }
    .back-link { text-align:center; margin-top:30px; }
    .back-link a { color:#4B0082; text-decoration:none; font-weight:500; }
  </style>
  <script>function confirmDeletion(){return confirm('Удалить ребенка?');}</script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать ребёнка</h1>
    <?php if ($success): ?><div class="message success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post">
      <label>ФИО ребёнка
        <input type="text" name="full_name" value="<?=htmlspecialchars($child['full_name'],ENT_QUOTES)?>" required>
      </label>
      <label>Дата рождения
        <input type="date" name="birth_date" value="<?=htmlspecialchars($child['birth_date'],ENT_QUOTES)?>" required>
      </label>
      <label>Класс
        <input type="text" name="class" value="<?=htmlspecialchars($child['class'],ENT_QUOTES)?>">
      </label>
      <label>Объединение
        <input type="text" name="association" value="<?=htmlspecialchars($child['association'],ENT_QUOTES)?>">
      </label>
      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDeletion();">Удалить</button>
      </div>
    </form>
    <div class="back-link"><a href="children_user.php">← Назад к списку детей</a></div>
  </div>
</body>
</html>
