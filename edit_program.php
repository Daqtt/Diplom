<?php
// ========== edit_program.php ==========
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Получаем ID программы из GET
$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$program_id) {
    echo 'Программа не найдена';
    exit;
}

$error   = '';
$success = '';

// Обработка POST: сохранить или удалить
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        // Считываем поля
        $implementer_full_name  = trim($_POST['implementer_full_name']   ?? '');
        $organization_name      = trim($_POST['organization_name']      ?? '');
        $program_name           = trim($_POST['program_name']           ?? '');
        $direction              = trim($_POST['direction']              ?? '');
        $student_count          = (int)($_POST['student_count']          ?? 0);
        $qualification_category = trim($_POST['qualification_category'] ?? '');
        $experience_years       = (int)($_POST['experience_years']       ?? 0);
        $qualification_data     = trim($_POST['qualification_data']     ?? '');
        $position               = trim($_POST['position']               ?? '');
        $awards                 = trim($_POST['awards']                 ?? '');
        $annotation_link        = trim($_POST['annotation_link']        ?? '');

        // Валидация
        if (
            $implementer_full_name === '' ||
            $organization_name       === '' ||
            $program_name            === '' ||
            $direction               === '' ||
            $student_count          <= 0
        ) {
            $error = 'Заполните все обязательные поля.';
        } else {
            $stmt = $conn->prepare("
                UPDATE gifted_programs SET
                  implementer_full_name  = ?,
                  organization_name      = ?,
                  program_name           = ?,
                  direction              = ?,
                  student_count          = ?,
                  qualification_category = ?,
                  experience_years       = ?,
                  qualification_data     = ?,
                  position               = ?,
                  awards                 = ?,
                  annotation_link        = ?
                WHERE program_id = ?
            ");
            // типы: s,s,s,s,i,s,i,s,s,s,s,i
            $stmt->bind_param(
                'ssssisissssi',
                $implementer_full_name,  // s
                $organization_name,      // s
                $program_name,           // s
                $direction,              // s
                $student_count,          // i
                $qualification_category, // s
                $experience_years,       // i
                $qualification_data,     // s
                $position,               // s
                $awards,                 // s
                $annotation_link,        // s
                $program_id              // i
            );
            if ($stmt->execute()) {
                $success = 'Данные программы успешно обновлены.';
            } else {
                $error = 'Ошибка при сохранении: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM gifted_programs WHERE program_id = ?");
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        header('Location: gifted_programs.php');
        exit;
    }
}

// Получаем текущие данные программы (после возможного обновления)
$stmt = $conn->prepare("
    SELECT
      implementer_full_name,
      organization_name,
      program_name,
      direction,
      student_count,
      qualification_category,
      experience_years,
      qualification_data,
      position,
      awards,
      annotation_link
    FROM gifted_programs
    WHERE program_id = ?
");
$stmt->bind_param('i', $program_id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$program) {
    echo 'Программа не найдена';
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать программу</title>
  <style>
    *,*::before,*::after {box-sizing:border-box}
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0;padding:20px;
      display:flex;justify-content:center;align-items:center;
      min-height:100vh;
    }
    .form-container {
      background:rgba(255,255,255,0.95);
      padding:40px;border-radius:20px;
      width:100%;max-width:600px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {text-align:center;color:#4B0082;margin-bottom:30px;}
    label {display:block;margin-bottom:16px;color:#333;font-weight:500;}
    input, select, textarea {
      width:100%;padding:12px;margin-top:8px;
      border:1px solid #ccc;border-radius:12px;
      font-size:16px;
    }
    textarea {resize:vertical;min-height:80px;}
    .button-group {display:flex;flex-direction:column;gap:12px;margin-top:20px;}
    .btn-main, .btn-delete {
      display:block;width:100%;height:52px;
      border:none;border-radius:12px;
      font-size:16px;font-weight:500;
      cursor:pointer;color:#fff;text-align:center;line-height:52px;
      transition:background .3s;
    }
    .btn-main {
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
    }
    .btn-main:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
    }
    .btn-delete {
      background:#e74c3c;
    }
    .btn-delete:hover {
      background:#c0392b;
    }
    .back-link {text-align:center;margin-top:30px;}
    .back-link a {
      color:#4B0082;text-decoration:none;font-weight:500;
    }
    .message {margin-bottom:20px;padding:10px;border-radius:8px;font-weight:500;}
    .message.success {background:#dff0d8;color:#3c763d;}
    .message.error   {background:#f2dede;color:#a94442;}
  </style>
  <script>
    function confirmDeletion() {
      return confirm('Вы уверены? Программа будет удалена безвозвратно.');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать программу</h1>

    <?php if ($success): ?>
      <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Ф.И.О. исполнителя
        <input type="text" name="implementer_full_name"
               value="<?= htmlspecialchars($program['implementer_full_name']) ?>"
               required>
      </label>

      <label>Название организации
        <input type="text" name="organization_name"
               value="<?= htmlspecialchars($program['organization_name']) ?>"
               required>
      </label>

      <label>Название программы
        <input type="text" name="program_name"
               value="<?= htmlspecialchars($program['program_name']) ?>"
               required>
      </label>

      <label>Направление
        <select name="direction" required>
          <option value="">— Выберите направление —</option>
          <option value="Наука"     <?= $program['direction']==='Наука'    ? 'selected':'' ?>>Наука</option>
          <option value="Спорт"     <?= $program['direction']==='Спорт'    ? 'selected':'' ?>>Спорт</option>
          <option value="Искусство" <?= $program['direction']==='Искусство'? 'selected':'' ?>>Искусство</option>
        </select>
      </label>

      <label>Общее количество обучающихся
        <input type="number" name="student_count" min="1"
               value="<?= htmlspecialchars($program['student_count']) ?>"
               required>
      </label>

      <label>Категория квалификации
        <input type="text" name="qualification_category"
               value="<?= htmlspecialchars($program['qualification_category']) ?>">
      </label>

      <label>Стаж (лет)
        <input type="number" name="experience_years" min="0"
               value="<?= htmlspecialchars($program['experience_years']) ?>">
      </label>

      <label>Данные о квалификации
        <textarea name="qualification_data"><?= htmlspecialchars($program['qualification_data']) ?></textarea>
      </label>

      <label>Должность
        <input type="text" name="position"
               value="<?= htmlspecialchars($program['position']) ?>">
      </label>

      <label>Награды
        <textarea name="awards"><?= htmlspecialchars($program['awards']) ?></textarea>
      </label>

      <label>Аннотация (ссылка)
        <input type="url" name="annotation_link"
               value="<?= htmlspecialchars($program['annotation_link']) ?>">
      </label>

      <div class="button-group">
        <button type="submit" name="save"   class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete"
                onclick="return confirmDeletion();">Удалить программу</button>
      </div>
    </form>

    <div class="back-link">
      <a href="gifted_programs.php">← Назад к списку программ</a>
    </div>
  </div>
</body>
</html>

