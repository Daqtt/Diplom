<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$error = $success = '';

// Получаем организацию пользователя
$user_id = $_SESSION['user_id'];
$orgStmt = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$orgStmt->bind_param('i', $user_id);
$orgStmt->execute();
$orgResult = $orgStmt->get_result()->fetch_assoc();
$organization = $orgResult['organization_name'] ?? '';
$orgStmt->close();

if (!$organization) {
    die("Организация не определена.");
}

// Получаем детей и руководителей из этой организации
$childrenStmt = $conn->prepare("SELECT child_id, full_name, class, association FROM children WHERE organization_name = ?");
$childrenStmt->bind_param('s', $organization);
$childrenStmt->execute();
$childrenData = $childrenStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$childrenStmt->close();

$responsiblesStmt = $conn->prepare("SELECT responsible_id, teacher_name FROM gifted_responsibles WHERE organization_name = ?");
$responsiblesStmt->bind_param('s', $organization);
$responsiblesStmt->execute();
$responsiblesData = $responsiblesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$responsiblesStmt->close();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $child_id = (int)$_POST['child_id'];
    $class    = trim($_POST['child_class']);
    $assoc    = trim($_POST['child_association']);
    $resp_id  = (int)$_POST['responsible_id'];
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

    $chk = $conn->prepare(
        "SELECT COUNT(*) FROM event_participants
         WHERE event_id = ? AND organization_name = ? AND child_id = ? AND responsible_id = ?"
    );
    $chk->bind_param('isii', $event_id, $organization, $child_id, $resp_id);
    $chk->execute();
    $chk->bind_result($count);
    $chk->fetch();
    $chk->close();

    if ($count > 0) {
        $error = "Участник уже добавлен в это мероприятие.";
    } else {
        $ins = $conn->prepare(
            "INSERT INTO event_participants
             (event_id, organization_name, child_id, class, association, responsible_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param('isissi', $event_id, $organization, $child_id, $class, $assoc, $resp_id);
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
  <title>Добавить участника мероприятия</title>
  <style>
    body { font-family: 'Inter',sans-serif; background: linear-gradient(135deg,#8A2BE2,#4B0082); margin:0; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; color:#333; padding:40px 20px; }
    .form-container { background: rgba(255,255,255,0.95); padding:40px; border-radius:20px; max-width:600px; width:100%; box-shadow:0 12px 40px rgba(0,0,0,0.2); position:relative; }
    h1 { text-align:center; margin-bottom:30px; color:#4B0082; }
    select, input, button, a.back-link { width:100%; padding:14px; margin-bottom:16px; border-radius:12px; border:1px solid #ccc; font-size:16px; box-sizing:border-box; }
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
  <h1>Добавить участника мероприятия</h1>

  <?php if ($success): ?>
    <div class="success"><?= htmlspecialchars($success, ENT_QUOTES) ?></div>
  <?php elseif ($error): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <form method="POST">
    <select name="responsible_id" id="resp_select" required>
      <option value="" disabled selected hidden>Выберите руководителя</option>
      <?php foreach ($responsiblesData as $r): ?>
        <option value="<?= $r['responsible_id'] ?>"><?= htmlspecialchars($r['teacher_name'], ENT_QUOTES) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="child_id" id="child_select" required>
      <option value="" disabled selected hidden>Ф.И.О. ребёнка</option>
      <?php foreach ($childrenData as $c): ?>
        <option value="<?= $c['child_id'] ?>"
                data-class="<?= htmlspecialchars($c['class'], ENT_QUOTES) ?>"
                data-assoc="<?= htmlspecialchars($c['association'], ENT_QUOTES) ?>">
          <?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="text" name="child_class" id="child_class" placeholder="Класс" readonly>
    <input type="text" name="child_association" id="child_association" placeholder="Объединение" readonly>

    <button type="submit" name="save">Сохранить</button>
  </form>

  <a href="event_participants_user.php?event_id=<?= (int)($_GET['event_id'] ?? 0) ?>" class="back-link">← Назад</a>
</div>

<script>
  const childSelect = document.getElementById('child_select');
  const childClass  = document.getElementById('child_class');
  const childAssoc  = document.getElementById('child_association');

  childSelect.addEventListener('change', () => {
    const opt = childSelect.options[childSelect.selectedIndex];
    childClass.value = opt.dataset.class || '';
    childAssoc.value = opt.dataset.assoc || '';
  });
</script>
</body>
</html>
