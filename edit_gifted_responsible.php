<?php
// ========== edit_gifted_responsible.php ==========
session_start();
require 'db_config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}
$conn = get_db_connection();

// Получаем ID записи
$id = $_GET['id'] ?? null;
if (!$id) { echo "Запись не найдена"; exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $teacher_name = trim($_POST['teacher_name']);
        $organization_name = trim($_POST['organization_name']);
        $position = trim($_POST['position']);
        $education_level = trim($_POST['education_level']);
        $qualification_category = trim($_POST['qualification_category']);
        $experience_years = (int)$_POST['experience_years'];
        $qualification_data = trim($_POST['qualification_data']);
        $awards = trim($_POST['awards']);
        
        $sql = "UPDATE gifted_responsibles SET
                  teacher_name = ?,
                  organization_name = ?,
                  position = ?,
                  education_level = ?,
                  qualification_category = ?,
                  experience_years = ?,
                  qualification_data = ?,
                  awards = ?
                WHERE responsible_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssisssi',
            $teacher_name,
            $organization_name,
            $position,
            $education_level,
            $qualification_category,
            $experience_years,
            $qualification_data,
            $awards,
            $id
        );
        if ($stmt->execute()) {
            $success = 'Изменения успешно сохранены.';
        } else {
            $error = 'Ошибка при сохранении: ' . $stmt->error;
        }
    } elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM gifted_responsibles WHERE responsible_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header("Location: gifted_responsibles.php"); exit;
    }
}

// Получаем текущие данные
$stmt = $conn->prepare("SELECT * FROM gifted_responsibles WHERE responsible_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$gr = $stmt->get_result()->fetch_assoc();
if (!$gr) { echo "Запись не найдена"; exit; }
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактирование ответственного</title>
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
    h1 { font-size:24px; text-align:center; margin-bottom:30px; color:#4B0082; }
    label { display:block; margin-bottom:16px; color:#333; font-weight:500; }
    input, textarea {
      width:100%; padding:14px; margin-top:8px;
      border:1px solid #ccc; border-radius:12px;
      font-size:16px; resize:vertical;
    }
    .button-group {
      display:flex; flex-direction:column; gap:12px; margin-top:20px;
    }
    .btn-main {
      display:block; width:100%; height:52px;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; border:none; border-radius:12px;
      font-size:16px; font-weight:500; cursor:pointer;
      text-align:center; line-height:52px;
    }
    .btn-main:hover { background:linear-gradient(135deg,#B39DDB,#9575CD); }
    .btn-delete {
      display:block; width:100%; height:52px;
      background:#e74c3c; color:#fff;
      border:none; border-radius:12px;
      font-size:16px; font-weight:500;
      cursor:pointer; text-align:center; line-height:52px;
      /* добавляем подтверждение */
    }
    .btn-delete:hover { background:#c0392b; }
    .back-link {
      text-align:center; margin-top:30px;
    }
    .back-link a {
      color:#4B0082; text-decoration:none; font-weight:500;
    }
    .message { margin-bottom:20px; padding:10px; border-radius:8px; font-weight:500; }
    .message.success { background:#dff0d8; color:#3c763d; }
    .message.error   { background:#f2dede; color:#a94442; }
  </style>
  <script>
    function confirmDeletion() {
      return confirm('Вы уверены, что хотите удалить ответственного?');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактирование ответственного</h1>
    <?php if ($success): ?><div class="message success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post">
      <label>ФИО преподавателя
        <input type="text" name="teacher_name" value="<?=htmlspecialchars($gr['teacher_name'])?>" required>
      </label>
      <label>Организация
        <input type="text" name="organization_name" value="<?=htmlspecialchars($gr['organization_name'])?>" required>
      </label>
      <label>Должность
        <input type="text" name="position" value="<?=htmlspecialchars($gr['position'])?>" required>
      </label>
      <label>Уровень образования
        <input type="text" name="education_level" value="<?=htmlspecialchars($gr['education_level'])?>">
      </label>
      <label>Квалификационная категория
        <input type="text" name="qualification_category" value="<?=htmlspecialchars($gr['qualification_category'])?>">
      </label>
      <label>Стаж (лет)
        <input type="number" name="experience_years" min="0" value="<?=htmlspecialchars($gr['experience_years'])?>">
      </label>
      <label>Сведения о квалификации
        <textarea name="qualification_data" rows="4"><?=htmlspecialchars($gr['qualification_data'])?></textarea>
      </label>
      <label>Награды
        <textarea name="awards" rows="3"><?=htmlspecialchars($gr['awards'])?></textarea>
      </label>
      <div class="button-group">
        <button type="submit" name="save" class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDeletion();">Удалить</button>
      </div>
    </form>
    <div class="back-link">
      <a href="gifted_responsibles.php">← Назад к списку ответственных</a>
    </div>
  </div>
</body>
</html>

