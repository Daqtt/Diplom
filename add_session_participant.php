<?php
// ========== add_session_participant.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Проверяем session_id
if (!isset($_GET['session_id']) || !is_numeric($_GET['session_id'])) {
    header('Location: summer_sessions.php');
    exit;
}
$session_id = (int)$_GET['session_id'];

// Получаем название смены
$stmt = $conn->prepare("SELECT name FROM summer_sessions WHERE session_id = ?");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    header('Location: summer_sessions.php');
    exit;
}
$session = $res->fetch_assoc();
$stmt->close();

// Список организаций
$orgs = [];
$orgsRes = $conn->query("
    SELECT DISTINCT organization_name
    FROM gifted_responsibles
    ORDER BY organization_name
");
while ($r = $orgsRes->fetch_assoc()) {
    $orgs[] = $r['organization_name'];
}

// Дети по организациям
$childrenByOrg = [];
$chRes = $conn->query("
    SELECT child_id, full_name, class, association, organization_name
    FROM children
    ORDER BY full_name
");
while ($r = $chRes->fetch_assoc()) {
    $childrenByOrg[$r['organization_name']][] = $r;
}

// Преподаватели по организациям
$respByOrg = [];
$respRes = $conn->query("
    SELECT responsible_id, teacher_name, organization_name
    FROM gifted_responsibles
    ORDER BY teacher_name
");
while ($r = $respRes->fetch_assoc()) {
    $respByOrg[$r['organization_name']][] = $r;
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org            = trim($_POST['organization_name'] ?? '');
    $child_id       = (int)($_POST['child_id'] ?? 0);
    $responsible_id = (int)($_POST['responsible_id'] ?? 0);
    $class          = trim($_POST['class'] ?? '');
    $association    = trim($_POST['association'] ?? '');
    $result         = trim($_POST['result'] ?? '');

    if ($org === '' || $child_id <= 0 || $responsible_id <= 0) {
        $error = 'Выберите организацию, ребёнка и руководителя.';
    } else {
        $class       = $class       ?: '-';
        $association = $association ?: '-';
        $result      = $result      ?: '-';

        $stmt = $conn->prepare("
            INSERT INTO summer_session_participants
              (session_id, organization_name, child_id, class, association, responsible_id, result)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        // i = session_id, s = organization_name, i = child_id, s = class, s = association, i = responsible_id, s = result
        $stmt->bind_param(
            'isisiss',
            $session_id,
            $org,
            $child_id,
            $class,
            $association,
            $responsible_id,
            $result
        );
        if ($stmt->execute()) {
            $success = 'Участник успешно добавлен.';
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
  <title>Добавить участника «<?= htmlspecialchars($session['name'], ENT_QUOTES) ?>»</title>
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
      width: 100%; max-width: 600px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center; color: #4B0082;
      margin-bottom: 30px;
    }
    label {
      display: block;
      margin-bottom: 20px;
      color: #333; font-weight: 500;
    }
    select, input {
      width: 100%; padding: 12px;
      margin-top: 8px;
      border: 1px solid #ccc; border-radius: 12px;
      font-size: 16px;
    }
    .button-group {
      display: flex; gap: 10px; margin-top: 20px;
    }
    .btn-cancel, .btn-main {
      flex: 1;
      display: flex; align-items: center; justify-content: center;
      height: 52px;
      border: none; border-radius: 12px;
      font-size: 16px; font-weight: 500;
      color: #fff; text-decoration: none;
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      cursor: pointer;
      transition: background 0.3s;
    }
    .btn-cancel:hover, .btn-main:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
    .success, .error {
      margin-bottom: 20px; padding: 12px;
      border-radius: 8px; font-weight: 500;
    }
    .success { background: #dff0d8; color: #3c763d; }
    .error   { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Добавить участника «<?= htmlspecialchars($session['name'], ENT_QUOTES) ?>»</h1>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>
        Организация
        <select id="orgSelect" name="organization_name" required>
          <option value="">— Выберите организацию —</option>
          <?php foreach ($orgs as $o): ?>
            <option value="<?= htmlspecialchars($o, ENT_QUOTES) ?>"
              <?= (($_POST['organization_name'] ?? '') === $o) ? 'selected' : '' ?>>
              <?= htmlspecialchars($o) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Ребёнок
        <select id="childSelect" name="child_id" required>
          <option value="">— Сначала организация —</option>
        </select>
      </label>

      <label>
        Класс
        <input type="text" id="classInput" name="class" readonly
               value="<?= htmlspecialchars($_POST['class'] ?? '', ENT_QUOTES) ?>">
      </label>

      <label>
        Объединение
        <input type="text" id="assocInput" name="association" readonly
               value="<?= htmlspecialchars($_POST['association'] ?? '', ENT_QUOTES) ?>">
      </label>

      <label>
        Руководитель
        <select id="respSelect" name="responsible_id" required>
          <option value="">— Сначала организация —</option>
        </select>
      </label>

      <label>
        Результат
        <input type="text" name="result" value="<?= htmlspecialchars($_POST['result'] ?? '', ENT_QUOTES) ?>">
      </label>

      <div class="button-group">
        <a href="session_participants.php?session_id=<?= $session_id ?>" class="btn-cancel">Отмена</a>
        <button type="submit" class="btn-main">Сохранить</button>
      </div>
    </form>
  </div>

  <script>
    const childrenByOrg     = <?= json_encode($childrenByOrg, JSON_UNESCAPED_UNICODE) ?>;
    const responsiblesByOrg = <?= json_encode($respByOrg,     JSON_UNESCAPED_UNICODE) ?>;

    const orgSelect   = document.getElementById('orgSelect');
    const childSelect = document.getElementById('childSelect');
    const respSelect  = document.getElementById('respSelect');
    const classInput  = document.getElementById('classInput');
    const assocInput  = document.getElementById('assocInput');

    orgSelect.addEventListener('change', () => {
      const org = orgSelect.value;
      // Обновляем список детей
      childSelect.innerHTML = '<option value="">— Выберите ребёнка —</option>';
      (childrenByOrg[org] || []).forEach(c => {
        const o = document.createElement('option');
        o.value = c.child_id;
        o.textContent = c.full_name;
        o.dataset.class       = c.class;
        o.dataset.association = c.association;
        childSelect.appendChild(o);
      });
      // Обновляем список руководителей
      respSelect.innerHTML = '<option value="">— Выберите руководителя —</option>';
      (responsiblesByOrg[org] || []).forEach(r => {
        const o = document.createElement('option');
        o.value = r.responsible_id;
        o.textContent = r.teacher_name;
        respSelect.appendChild(o);
      });
      classInput.value = '';
      assocInput.value = '';
    });

    childSelect.addEventListener('change', () => {
      const sel = childSelect.selectedOptions[0];
      classInput.value = sel?.dataset.class || '';
      assocInput.value = sel?.dataset.association || '';
    });

    // Восстановление после ошибки
    <?php if (!empty($_POST['organization_name'])): ?>
      orgSelect.value = <?= json_encode($_POST['organization_name'], JSON_UNESCAPED_UNICODE) ?>;
      orgSelect.dispatchEvent(new Event('change'));
    <?php endif; ?>
    <?php if (!empty($_POST['child_id'])): ?>
      setTimeout(() => {
        childSelect.value = <?= (int)$_POST['child_id'] ?>;
        childSelect.dispatchEvent(new Event('change'));
        respSelect.value = <?= (int)($_POST['responsible_id'] ?? 0) ?>;
      }, 0);
    <?php endif; ?>
  </script>
</body>
</html>



