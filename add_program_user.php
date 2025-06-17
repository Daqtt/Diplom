<?php
// ========== add_program_user.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn    = get_db_connection();
$uid     = $_SESSION['user_id'];
$error   = '';
$success = '';

// Получаем организацию пользователя (единожды)
$stmtOrg = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$stmtOrg->bind_param('i', $uid);
$stmtOrg->execute();
$userOrg = $stmtOrg->get_result()->fetch_assoc()['organization_name'];
$stmtOrg->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $implementer_full_name  = trim($_POST['implementer_full_name']   ?? '');
    $program_name           = trim($_POST['program_name']           ?? '');
    $direction              = trim($_POST['direction']              ?? '');
    $student_count          = (int)($_POST['student_count']          ?? 0);
    $qualification_category = trim($_POST['qualification_category'] ?? '');
    $experience_years       = (int)($_POST['experience_years']       ?? 0);
    $qualification_data     = trim($_POST['qualification_data']     ?? '');
    $position               = trim($_POST['position']               ?? '');
    $awards                 = trim($_POST['awards']                 ?? '');
    $annotation_link        = trim($_POST['annotation_link']        ?? '');

    // Валидация обязательных полей
    if (
        $implementer_full_name === '' ||
        $program_name           === '' ||
        $direction              === '' ||
        $student_count <= 0
    ) {
        $error = 'Заполните все обязательные поля.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO gifted_programs
              (implementer_full_name,
               organization_name,
               program_name,
               direction,
               student_count,
               qualification_category,
               experience_years,
               qualification_data,
               position,
               awards,
               annotation_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'ssssisissss',
            $implementer_full_name,  // s
            $userOrg,                // s — организация из сессии
            $program_name,           // s
            $direction,              // s
            $student_count,          // i
            $qualification_category, // s
            $experience_years,       // i
            $qualification_data,     // s
            $position,               // s
            $awards,                 // s
            $annotation_link         // s
        );
        if ($stmt->execute()) {
            $success = 'Программа успешно добавлена.';
            // Очистим поля формы
            $_POST = [];
        } else {
            $error = 'Ошибка при добавлении: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить программу</title>
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
    .button-group {display:flex;gap:10px;margin-top:20px;}
    .btn-cancel, .btn-main {
      flex:1;display:flex;align-items:center;
      justify-content:center;height:52px;
      border:none;border-radius:12px;
      font-size:16px;font-weight:500;
      cursor:pointer;color:#fff;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      text-decoration:none;transition:background .3s;
    }
    .btn-cancel:hover, .btn-main:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
    }
    .success, .error {
      margin-bottom:16px;padding:10px;
      border-radius:8px;font-weight:500;
    }
    .success {background:#dff0d8;color:#3c763d;}
    .error   {background:#f2dede;color:#a94442;}
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить программу</h1>

    <?php if ($success): ?>
      <div class="success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post">
      <label>Ф.И.О. исполнителя
        <input type="text" name="implementer_full_name"
               placeholder="Иванов Иван Иванович"
               value="<?=htmlspecialchars($_POST['implementer_full_name']??'')?>"
               required>
      </label>

      <!-- Поле организации убрано -->

      <label>Название программы
        <input type="text" name="program_name"
               placeholder="Робототехника"
               value="<?=htmlspecialchars($_POST['program_name']??'')?>"
               required>
      </label>

      <label>Направление
        <select name="direction" required>
          <option value="">— Выберите направление —</option>
          <option value="Наука"     <?=($_POST['direction']??'')==='Наука'    ?'selected':''?>>Наука</option>
          <option value="Спорт"     <?=($_POST['direction']??'')==='Спорт'    ?'selected':''?>>Спорт</option>
          <option value="Искусство" <?=($_POST['direction']??'')==='Искусство'?'selected':''?>>Искусство</option>
        </select>
      </label>

      <label>Общее количество обучающихся
        <input type="number" name="student_count" min="1"
               placeholder="25"
               value="<?=htmlspecialchars($_POST['student_count']??'')?>"
               required>
      </label>

      <label>Категория квалификации
        <input type="text" name="qualification_category"
               value="<?=htmlspecialchars($_POST['qualification_category']??'')?>">
      </label>

      <label>Стаж (лет)
        <input type="number" name="experience_years" min="0"
               value="<?=htmlspecialchars($_POST['experience_years']??'')?>">
      </label>

      <label>Данные о квалификации
        <textarea name="qualification_data"><?=htmlspecialchars($_POST['qualification_data']??'')?></textarea>
      </label>

      <label>Должность
        <input type="text" name="position"
               value="<?=htmlspecialchars($_POST['position']??'')?>">
      </label>

      <label>Награды
        <textarea name="awards"><?=htmlspecialchars($_POST['awards']??'')?></textarea>
      </label>

      <label>Аннотация (ссылка)
        <input type="url" name="annotation_link"
               placeholder="https://…"
               value="<?=htmlspecialchars($_POST['annotation_link']??'')?>">
      </label>

      <div class="button-group">
        <a href="gifted_programs_user.php" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>
</body>
</html>
