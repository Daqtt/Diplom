<?php
// ========== edit_session_participant.php ==========
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = get_db_connection();

// Проверяем GET-параметры
if (
    !isset($_GET['id'], $_GET['session_id']) ||
    !is_numeric($_GET['id']) ||
    !is_numeric($_GET['session_id'])
) {
    header('Location: summer_sessions.php');
    exit;
}
$participant_id = (int)$_GET['id'];
$session_id     = (int)$_GET['session_id'];

// Получаем данные участника, включая organization_name из своей таблицы
$stmt = $conn->prepare("
    SELECT
      p.organization_name,
      p.child_id,
      p.class,
      p.association,
      p.responsible_id,
      p.result
    FROM summer_session_participants p
    WHERE p.participant_id = ? 
      AND p.session_id     = ?
");
$stmt->bind_param('ii', $participant_id, $session_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    header("Location: session_participants.php?session_id={$session_id}");
    exit;
}
$part = $res->fetch_assoc();
$stmt->close();

// Название смены
$stmt = $conn->prepare("SELECT name FROM summer_sessions WHERE session_id = ?");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Список организаций (для select)
$orgs = [];
$r = $conn->query("
    SELECT DISTINCT organization_name
    FROM gifted_responsibles
    ORDER BY organization_name
");
while ($row = $r->fetch_assoc()) {
    $orgs[] = $row['organization_name'];
}

// Дети по организациям
$childrenByOrg = [];
$r = $conn->query("
    SELECT child_id, full_name, class, association, organization_name
    FROM children
    ORDER BY full_name
");
while ($row = $r->fetch_assoc()) {
    $childrenByOrg[$row['organization_name']][] = $row;
}

// Преподаватели по организациям
$respByOrg = [];
$r = $conn->query("
    SELECT responsible_id, teacher_name, organization_name
    FROM gifted_responsibles
    ORDER BY teacher_name
");
while ($row = $r->fetch_assoc()) {
    $respByOrg[$row['organization_name']][] = $row;
}

// Обработка POST
$error   = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Удаление
    if (isset($_POST['delete'])) {
        $d = $conn->prepare("
            DELETE FROM summer_session_participants
            WHERE participant_id = ?
        ");
        $d->bind_param('i', $participant_id);
        $d->execute();
        header("Location: session_participants.php?session_id={$session_id}");
        exit;
    }

    // Сохранение изменений
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

        $u = $conn->prepare("
          UPDATE summer_session_participants SET
            organization_name = ?,
            child_id         = ?,
            class            = ?,
            association      = ?,
            responsible_id   = ?,
            result           = ?
          WHERE participant_id = ?
        ");
        // s = organization_name, i = child_id, s = class, s = association, i = responsible_id, s = result, i = participant_id
        $u->bind_param(
            'sissisi',
            $org,
            $child_id,
            $class,
            $association,
            $responsible_id,
            $result,
            $participant_id
        );
        if ($u->execute()) {
            $success = 'Данные участника успешно обновлены.';
            // обновляем локальный массив для повторного вывода
            $part['organization_name'] = $org;
            $part['child_id']          = $child_id;
            $part['class']             = $class;
            $part['association']       = $association;
            $part['responsible_id']    = $responsible_id;
            $part['result']            = $result;
        } else {
            $error = 'Ошибка при сохранении: ' . $u->error;
        }
        $u->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать участника «<?= htmlspecialchars($session['name'], ENT_QUOTES) ?>»</title>
  <style>
    *,*::before,*::after { box-sizing:border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0; padding:20px;
      display:flex; justify-content:center; align-items:center;
      min-height:100vh;
    }
    .form-container {
      background: rgba(255,255,255,0.95);
      padding: 40px; border-radius:20px;
      width:100%; max-width:600px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
    }
    h1 {
      text-align:center; margin-bottom:30px;
      color:#4B0082;
    }
    label {
      display:block; margin-bottom:16px;
      color:#333; font-weight:500;
    }
    select,input {
      width:100%; padding:14px; margin-top:8px;
      border:1px solid #ccc; border-radius:12px;
      font-size:16px;
    }
    .button-group {
      display:flex; flex-direction:column; gap:12px; margin-top:20px;
    }
    .btn-main, .btn-delete {
      display:block; width:100%; height:52px;
      border:none; border-radius:12px;
      font-size:16px; font-weight:500;
      color:#fff; cursor:pointer;
      text-align:center; line-height:52px;
      transition:background 0.3s;
    }
    .btn-main {
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
    }
    .btn-main:hover {
      background: linear-gradient(135deg,#B39DDB,#9575CD);
    }
    .btn-delete {
      background: #e74c3c;
    }
    .btn-delete:hover {
      background: #c0392b;
    }
    .back-link {
      text-align:center; margin-top:30px;
    }
    .back-link a {
      color:#4B0082; text-decoration:none; font-weight:500;
    }
    .message {
      margin-bottom:20px; padding:10px;
      border-radius:8px; font-weight:500;
    }
    .message.success { background:#dff0d8; color:#3c763d; }
    .message.error   { background:#f2dede; color:#a94442; }
  </style>
  <script>
    function confirmDelete() {
      return confirm('Удалить участника?');
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h1>Редактировать участника смены «<?= htmlspecialchars($session['name'], ENT_QUOTES) ?>»</h1>

    <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <label>
        Организация
        <select id="orgSelect" name="organization_name" required>
          <option value="">— Выберите организацию —</option>
          <?php foreach ($orgs as $o): ?>
          <option value="<?= htmlspecialchars($o, ENT_QUOTES) ?>"
            <?= ($o === ($part['organization_name'] ?? '')) ? 'selected' : '' ?>>
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
               value="<?= htmlspecialchars($part['class'], ENT_QUOTES) ?>">
      </label>

      <label>
        Объединение
        <input type="text" id="assocInput" name="association" readonly
               value="<?= htmlspecialchars($part['association'], ENT_QUOTES) ?>">
      </label>

      <label>
        Руководитель
        <select id="respSelect" name="responsible_id" required>
          <option value="">— Сначала организация —</option>
        </select>
      </label>

      <label>
        Результат
        <input type="text" name="result"
               value="<?= htmlspecialchars($part['result'], ENT_QUOTES) ?>">
      </label>

      <div class="button-group">
        <button type="submit" name="save"   class="btn-main">Сохранить изменения</button>
        <button type="submit" name="delete" class="btn-delete" onclick="return confirmDelete();">Удалить участника</button>
      </div>

      <div class="back-link">
        <a href="session_participants.php?session_id=<?= $session_id ?>">← Назад к участникам</a>
      </div>
    </form>
  </div>

  <script>
    const childrenByOrg     = <?= json_encode($childrenByOrg,   JSON_UNESCAPED_UNICODE) ?>;
    const responsiblesByOrg = <?= json_encode($respByOrg,       JSON_UNESCAPED_UNICODE) ?>;

    const orgSelect   = document.getElementById('orgSelect');
    const childSelect = document.getElementById('childSelect');
    const respSelect  = document.getElementById('respSelect');
    const classInput  = document.getElementById('classInput');
    const assocInput  = document.getElementById('assocInput');

    orgSelect.addEventListener('change', () => {
      const org = orgSelect.value;
      // Обновляем список детей
      childSelect.innerHTML = '<option value="">— Сначала организация —</option>';
      (childrenByOrg[org] || []).forEach(c => {
        const o = document.createElement('option');
        o.value = c.child_id;
        o.textContent = c.full_name;
        o.dataset.class       = c.class;
        o.dataset.association = c.association;
        childSelect.appendChild(o);
      });
      // Обновляем список руководителей
      respSelect.innerHTML = '<option value="">— Сначала организация —</option>';
      (responsiblesByOrg[org] || []).forEach(r => {
        const o = document.createElement('option');
        o.value = r.responsible_id;
        o.textContent = r.teacher_name;
        respSelect.appendChild(o);
      });
      // Сброс данных полей
      classInput.value = '';
      assocInput.value = '';
    });

    childSelect.addEventListener('change', () => {
      const sel = childSelect.selectedOptions[0];
      classInput.value = sel?.dataset.class || '';
      assocInput.value = sel?.dataset.association || '';
    });

    // Инициализация при загрузке (для редактирования)
    <?php if (!empty($part['organization_name'])): ?>
    orgSelect.value = <?= json_encode($part['organization_name'], JSON_UNESCAPED_UNICODE) ?>;
    orgSelect.dispatchEvent(new Event('change'));
    <?php endif; ?>
    <?php if (!empty($part['child_id'])): ?>
    setTimeout(() => {
      childSelect.value = <?= json_encode($part['child_id'], JSON_UNESCAPED_UNICODE) ?>;
      childSelect.dispatchEvent(new Event('change'));
      respSelect.value = <?= json_encode($part['responsible_id'], JSON_UNESCAPED_UNICODE) ?>;
    }, 0);
    <?php endif; ?>
  </script>
</body>
</html>


