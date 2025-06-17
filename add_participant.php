<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Проверяем contest_id
if (!isset($_GET['contest_id']) || !is_numeric($_GET['contest_id'])) {
    header("Location: contests.php");
    exit;
}
$contest_id = (int)$_GET['contest_id'];

$conn = get_db_connection();
$error = $success = '';

// Получаем организацию конкурса
$cStmt = $conn->prepare("SELECT organization_name FROM contests WHERE contest_id = ?");
$cStmt->bind_param('i', $contest_id);
$cStmt->execute();
$cRow = $cStmt->get_result()->fetch_assoc();
$org = $cRow['organization_name'] ?? '';
$cStmt->close();
if (!$org) {
    die('Конкурс не найден или у него нет организации.');
}

// Получаем список детей и руководителей для этой организации
$chStmt = $conn->prepare(
    "SELECT child_id, full_name, class, association FROM children WHERE organization_name = ? ORDER BY full_name"
);
$chStmt->bind_param('s', $org);
$chStmt->execute();
$childrenData = $chStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chStmt->close();

$rStmt = $conn->prepare(
    "SELECT responsible_id, teacher_name FROM gifted_responsibles WHERE organization_name = ? ORDER BY teacher_name"
);
$rStmt->bind_param('s', $org);
$rStmt->execute();
$responsiblesData = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rStmt->close();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $child_id = (int)$_POST['child_id'];
    $class = trim($_POST['child_class']);
    $assoc = trim($_POST['child_association']);
    $resp_id = (int)$_POST['responsible_id'];
    $result = trim($_POST['result']);

    // Проверка дубликата
    $chk = $conn->prepare(
        "SELECT COUNT(*) FROM contest_participants
         WHERE contest_id = ? AND organization_name = ? AND child_id = ? AND responsible_id = ?"
    );
    $chk->bind_param('isis', $contest_id, $org, $child_id, $resp_id);
    $chk->execute();
    $chk->bind_result($count);
    $chk->fetch();
    $chk->close();

    if ($count > 0) {
        $error = "Участник уже добавлен в этот конкурс.";
    } else {
        $ins = $conn->prepare(
            "INSERT INTO contest_participants
             (contest_id, organization_name, child_id, class, association, responsible_id, result)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param('isissis', $contest_id, $org, $child_id, $class, $assoc, $resp_id, $result);
        if ($ins->execute()) {
            $success = 'Участник успешно добавлен.';
        } else {
            $error = 'Ошибка при добавлении: ' . $ins->error;
        }
        $ins->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить участника</title>
  <style>
    body { font-family: 'Inter',sans-serif; background: linear-gradient(135deg,#8A2BE2,#4B0082); margin:0; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; color:#333; padding:40px 20px; }
    .form-container { background: rgba(255,255,255,0.95); padding:40px; border-radius:20px; max-width:600px; width:100%; box-shadow:0 12px 40px rgba(0,0,0,0.2); position:relative; }
    h1 { text-align:center; margin-bottom:30px; color:#4B0082; }
    select, input, button, a.back-link { width:100%; padding:14px; margin-bottom:16px; border-radius:12px; border:1px solid #ccc; font-size:16px; box-sizing:border-box; }
    textarea { resize:vertical; }
    button { background: linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; font-weight:500; cursor:pointer; border:none; transition: background .3s, transform .2s; }
    button:hover { background: linear-gradient(135deg,#B39DDB,#9575CD); transform:scale(1.05); }
    .success, .error { padding:10px 14px; border-radius:8px; margin-bottom:16px; font-weight:500; text-align:center; }
    .success { background:#dff0d8; color:#3c763d; }
    .error { background:#f2dede; color:#a94442; }
    a.back-link { display:inline-block; text-decoration:none; color:#4B0082; background:#fff; text-align:center; font-weight:500; transition: background .3s, transform .2s; }
    a.back-link:hover { background:#f0f0f0; transform:scale(1.02); }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить участника</h1>
    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success, ENT_QUOTES) ?></div>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="POST">
      <!-- Организация берётся из конкурса -->
      <select name="responsible_id" id="resp_select" required>
        <option value="" disabled selected hidden>Выберите руководителя</option>
        <?php foreach ($responsiblesData as $r): ?>
          <option value="<?= $r['responsible_id'] ?>"><?= htmlspecialchars($r['teacher_name'], ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="child_id" id="child_select" required>
        <option value="" disabled selected hidden>Ф.И.О. ребёнка</option>
        <?php foreach ($childrenData as $k): ?>
          <option value="<?= $k['child_id'] ?>"><?= htmlspecialchars($k['full_name'], ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="child_class" id="child_class" placeholder="Класс" readonly>
      <input type="text" name="child_association" id="child_association" placeholder="Объединение" readonly>
      <input type="text" name="result" placeholder="Результат">
      <button type="submit" name="save">Сохранить</button>
    </form>
    <a href="participants.php?contest_id=<?= $contest_id ?>" class="back-link">← Назад к списку участников</a>
  </div>
  <script>
    const kids = <?= json_encode($childrenData) ?>;
    const cSel = document.getElementById('child_select');
    const cCls = document.getElementById('child_class');
    const cAs  = document.getElementById('child_association');
    // автозаполнение при выборе ребёнка
    cSel.addEventListener('change', () => {
      const k = kids.find(x => x.child_id == cSel.value);
      cCls.value = k.class || '';
      cAs.value  = k.association || '';
    });
  </script>
</body>
</html>

