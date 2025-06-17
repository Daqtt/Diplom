<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$error = $success = '';

// Проверяем наличие ID конкурса
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: contests.php");
    exit;
}
$contest_id = (int)$_GET['id'];

// Получаем список организаций из gifted_responsibles
$orgs = [];
$resOrg = $conn->query("SELECT DISTINCT organization_name FROM gifted_responsibles ORDER BY organization_name");
if ($resOrg) {
    while ($r = $resOrg->fetch_assoc()) {
        $orgs[] = $r['organization_name'];
    }
    $resOrg->free();
}

// Удаление конкурса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $del = $conn->prepare("DELETE FROM contests WHERE contest_id = ?");
    $del->bind_param('i', $contest_id);
    if ($del->execute()) {
        header("Location: contests.php");
        exit;
    } else {
        $error = 'Ошибка при удалении: ' . $del->error;
    }
    $del->close();
}

// Обработка обновления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name              = trim($_POST['name']);
    $order_number      = trim($_POST['order_number']);
    $level             = trim($_POST['level']);
    $age_category      = trim($_POST['age_category']);
    $description       = trim($_POST['description']);
    $start_date        = $_POST['start_date'];
    $end_date          = $_POST['end_date'] ?: null;
    $organization_name = $_POST['organization_name'];

    $upd = $conn->prepare(
        "UPDATE contests SET name=?, order_number=?, level=?, age_category=?, description=?, start_date=?, end_date=?, organization_name=? WHERE contest_id=?"
    );
    $upd->bind_param(
        'ssssssssi',
        $name,
        $order_number,
        $level,
        $age_category,
        $description,
        $start_date,
        $end_date,
        $organization_name,
        $contest_id
    );
    if ($upd->execute()) {
        $success = 'Конкурс успешно обновлён.';
    } else {
        $error = 'Ошибка при обновлении: ' . $upd->error;
    }
    $upd->close();
}

// Получаем текущие данные конкурса
$stmt = $conn->prepare(
    "SELECT name, order_number, level, age_category, description, start_date, end_date, organization_name
     FROM contests WHERE contest_id = ?"
);
$stmt->bind_param('i', $contest_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header("Location: contests.php");
    exit;
}
$row = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать конкурс</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
      background: rgba(255,255,255,0.95);
      padding: 40px;
      border-radius: 20px;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 { text-align: center; margin-bottom: 30px; color: #4B0082; }
    input, textarea, select, button, a.back-link {
      width: 100%;
      padding: 14px;
      margin-bottom: 16px;
      border-radius: 12px;
      border: 1px solid #ccc;
      font-size: 16px;
      box-sizing: border-box;
      font-family: inherit;
    }
    textarea { resize: vertical; }
    .date-group { display: flex; gap: 10px; }
    .date-group input { flex: 1; }
    button {
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff;
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
    .error   { background: #f2dede; color: #a94442; }
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
    <h1>Редактировать конкурс</h1>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="name" value="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>" placeholder="Название конкурса" required>
      <input type="text" name="order_number" value="<?= htmlspecialchars($row['order_number'], ENT_QUOTES) ?>" placeholder="Номер приказа">
      <input type="text" name="level" value="<?= htmlspecialchars($row['level'], ENT_QUOTES) ?>" placeholder="Уровень мероприятия" required>
      <div class="date-group">
        <input type="date" name="start_date" value="<?= $row['start_date'] ?>" required>
        <input type="date" name="end_date" value="<?= $row['end_date'] ?>">
      </div>
      <input type="text" name="age_category" value="<?= htmlspecialchars($row['age_category'], ENT_QUOTES) ?>" placeholder="Возрастная категория" required>
      <textarea name="description" placeholder="Описание конкурса" rows="4"><?= htmlspecialchars($row['description'], ENT_QUOTES) ?></textarea>
      <!-- select для организации -->
      <select name="organization_name" required>
        <option value="" disabled>Выберите организацию</option>
        <?php foreach ($orgs as $org): ?>
          <option value="<?= htmlspecialchars($org) ?>" <?= $org === $row['organization_name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($org) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" name="save">Сохранить</button>
      <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Удалить конкурс?');">Удалить конкурс</button>
    </form>
    <a href="contests.php" class="back-link">← Назад к списку конкурсов</a>
  </div>
</body>
</html>
