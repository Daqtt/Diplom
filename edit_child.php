<?php
// ========== edit_child.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$conn = get_db_connection();

// Получаем ID ребёнка из GET
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$child_id) {
    echo 'Ребёнок не найден';
    exit;
}

$error = $success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $full_name         = trim($_POST['full_name']);
        $birth_date        = $_POST['birth_date'];
        $class             = trim($_POST['class']);
        $association       = trim($_POST['association']);
        $organization_name = $_POST['organization_name'];

        // Подставляем прочерк, если класс или объединение пустые
        if ($class === '') {
            $class = '-';
        }
        if ($association === '') {
            $association = '-';
        }

        $stmt = $conn->prepare(
            "UPDATE children
             SET full_name = ?, birth_date = ?, class = ?, association = ?, organization_name = ?
             WHERE child_id = ?"
        );
        $stmt->bind_param(
            'sssssi',
            $full_name,
            $birth_date,
            $class,
            $association,
            $organization_name,
            $child_id
        );
        if ($stmt->execute()) {
            $success = 'Данные ребёнка успешно обновлены.';
        } else {
            $error = 'Ошибка при сохранении: ' . $stmt->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM children WHERE child_id = ?");
        $stmt->bind_param('i', $child_id);
        $stmt->execute();
        $stmt->close();
        header("Location: children.php");
        exit;
    }
}

// Получаем текущие данные ребёнка
$stmt = $conn->prepare("SELECT full_name, birth_date, class, association, organization_name FROM children WHERE child_id = ?");
$stmt->bind_param('i', $child_id);
$stmt->execute();
$result = $stmt->get_result();
$child = $result->fetch_assoc();
$stmt->close();
if (!$child) {
    echo 'Ребёнок не найден';
    exit;
}

// Получаем список организаций из gifted_responsibles
$orgsResult = $conn->query("SELECT DISTINCT organization_name FROM gifted_responsibles ORDER BY organization_name");
$organizations = [];
while ($row = $orgsResult->fetch_assoc()) {
    $organizations[] = $row['organization_name'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать ребёнка</title>
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
      max-width: 600px; width: 100%;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center; margin-bottom: 30px;
      color: #4B0082;
    }
    label {
      display: block; margin-bottom: 16px;
      color: #333; font-weight: 500;
    }
    input, select {
      width: 100%; padding: 14px; margin-top: 8px;
      border: 1px solid #ccc; border-radius: 12px;
      font-size: 16px;
    }
    .button-group {
      display: flex; flex-direction: column; gap: 12px; margin-top: 20px;
    }
    .btn-main {
      display: block; width: 100%; height: 52px;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: #fff; border: none; border-radius: 12px;
      font-size: 16px; font-weight: 500; cursor: pointer;
      text-align: center; line-height: 52px;
      transition: background 0.3s;
    }
    .btn-main:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .btn-delete {
      display: block; width: 100%; height: 52px;
      background: #e74c3c; color: #fff;
      border: none; border-radius: 12px;
      font-size: 16px; font-weight: 500; cursor: pointer;
      text-align: center; line-height: 52px;
      transition: background 0.3s;
    }
    .btn-delete:hover {
      background: #c0392b;
    }
    .back-link {
      text-align: center; margin-top: 30px;
    }
    .back-link a {
      color: #4B0082; text-decoration: none; font-weight: 500;
    }
    .message { margin-bottom: 20px; padding: 10px; border-radius: 8px; font-weight: 500; }
    .message.success { background: #dff0d8; color: #3c763d; }
    .message.error   { background: #f2dede; color: #a94442; }
  </style>
  <script>
    function confirmDeletion() {
      return confirm('Вы уверены? Ребёнок будет удалён безвозвратно.');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать ребёнка</h1>
    <?php if ($success): ?><div class="message success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post">
      <label>ФИО ребёнка
        <input type="text" name="full_name" value="<?=htmlspecialchars($child['full_name'])?>" required>
      </label>
      <label>Дата рождения
        <input type="date" name="birth_date" value="<?=htmlspecialchars($child['birth_date'])?>" required>
      </label>
      <label>Класс
        <input type="text" name="class" value="<?=htmlspecialchars($child['class'])?>">
      </label>
      <label>Объединение
        <input type="text" name="association" value="<?=htmlspecialchars($child['association'])?>">
      </label>
      <label>Организация
        <select name="organization_name" required>
          <?php foreach ($organizations as $org): ?>
            <option value="<?=htmlspecialchars($org)?>" <?php if($child['organization_name']===$org) echo 'selected'; ?>>
              <?=htmlspecialchars($org)?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDeletion();">Удалить</button>
      </div>
    </form>
    <div class="back-link">
      <a href="children.php">← Назад к списку детей</a>
    </div>
  </div>
</body>
</html>
