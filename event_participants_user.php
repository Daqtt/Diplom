<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// Получаем организацию пользователя
$uid = $_SESSION['user_id'];
$stmtU = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$stmtU->bind_param('i', $uid);
$stmtU->execute();
$org = $stmtU->get_result()->fetch_assoc()['organization_name'] ?? '';
$stmtU->close();

if (!$org) {
    die("Организация не определена.");
}

// Проверка ID мероприятия
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header("Location: events_user.php");
    exit;
}
$event_id = (int)$_GET['event_id'];

// AJAX-запрос
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['search'] ?? '';
    $column = $_GET['column'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where = 'WHERE ep.event_id = ? AND ep.organization_name = ?';
    $params = [$event_id, $org];
    $types = 'is';

    if ($search !== '') {
        $param = "%{$search}%";
        switch ($column) {
            case 'child':
                $where .= ' AND c.full_name LIKE ?';
                $params[] = $param; $types .= 's'; break;
            case 'class':
                $where .= ' AND ep.class LIKE ?';
                $params[] = $param; $types .= 's'; break;
            case 'association':
                $where .= ' AND ep.association LIKE ?';
                $params[] = $param; $types .= 's'; break;
            case 'responsible':
                $where .= ' AND gr.teacher_name LIKE ?';
                $params[] = $param; $types .= 's'; break;
            default:
                $where .= ' AND (c.full_name LIKE ? OR ep.class LIKE ? OR ep.association LIKE ? OR gr.teacher_name LIKE ?)';
                $params = array_merge($params, array_fill(0, 4, $param));
                $types .= 'ssss';
        }
    }

    // Подсчёт
    $cntSql = "
        SELECT COUNT(*) AS cnt
        FROM event_participants ep
        LEFT JOIN children c ON ep.child_id = c.child_id
        LEFT JOIN gifted_responsibles gr ON ep.responsible_id = gr.responsible_id
        $where
    ";
    $cntStmt = $conn->prepare($cntSql);
    $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();
    $totalPages = ceil($total / $limit);

    // Получение данных
    $sql = "
        SELECT ep.participant_id, c.full_name, ep.class, ep.association, gr.teacher_name AS responsible_name
        FROM event_participants ep
        LEFT JOIN children c ON ep.child_id = c.child_id
        LEFT JOIN gifted_responsibles gr ON ep.responsible_id = gr.responsible_id
        $where
        ORDER BY c.full_name ASC
        LIMIT ? OFFSET ?
    ";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'data' => $rows,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages,
        'page' => $page
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Название мероприятия
$stmtE = $conn->prepare("SELECT name FROM events WHERE event_id = ?");
$stmtE->bind_param('i', $event_id);
$stmtE->execute();
$event = $stmtE->get_result()->fetch_assoc();
$stmtE->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Участники мероприятия «<?= htmlspecialchars($event['name'], ENT_QUOTES) ?>»</title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      margin: 0; padding: 20px;
      display: flex; justify-content: center;
    }
    .container {
      background: rgba(255,255,255,0.95);
      padding: 30px; border-radius: 20px;
      width: 100%; max-width: 1200px; min-height:1000px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
      display: flex; flex-direction: column;
    }
    .header {
      display: flex; justify-content: space-between;
      align-items: center; margin-bottom: 20px;
    }
    .btn {
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      color: #fff; padding: 12px 24px;
      border-radius: 999px; text-decoration: none;
      font-weight: 500; font-size: 16px;
      transition: background .3s, transform .2s;
    }
    .btn:hover {
      background: linear-gradient(135deg,#9575CD,#B39DDB);
      transform: scale(1.05);
    }
    .search-bar {
      display: flex; gap: 10px; margin-bottom: 16px;
    }
    .search-bar select, .search-bar input {
      padding: 10px; border: 1px solid #ccc;
      border-radius: 6px; font-size: 16px;
    }
    .search-bar input { flex: 1; }
    table {
      width: 100%; border-collapse: collapse;
      background: #fff;
    }
    th, td {
      padding: 12px; border: 1px solid #eee;
      text-align: left;
    }
    tbody tr:hover { background: #f9f9ff; cursor: pointer; }
    .pagination {
      display: flex; justify-content: center;
      gap: 20px; margin-top: 20px;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <a href="events_user.php" class="btn">← Назад</a>
    <h1>Участники: <?= htmlspecialchars($event['name'], ENT_QUOTES) ?></h1>
    <a href="add_event_participant_user.php?event_id=<?= $event_id ?>" class="btn">➕ Добавить</a>
  </div>

  <div class="search-bar">
    <select id="columnSelect">
      <option value="child">Ф.И.О. ребёнка</option>
      <option value="class">Класс</option>
      <option value="association">Объединение</option>
      <option value="responsible">Руководитель</option>
    </select>
    <input type="text" id="searchInput" placeholder="Поиск участников...">
  </div>

  <table>
    <thead>
      <tr>
        <th>Ф.И.О. ребёнка</th>
        <th>Класс</th>
        <th>Объединение</th>
        <th>Руководитель</th>
      </tr>
    </thead>
    <tbody id="tableBody"></tbody>
  </table>

  <div class="pagination">
    <button id="prevPage" class="btn" style="display:none">← Назад</button>
    <button id="nextPage" class="btn" style="display:none">Вперёд →</button>
  </div>
</div>

<script>
const eventId    = <?= $event_id ?>;
let page         = 1;
const colSelect  = document.getElementById('columnSelect');
const searchInput= document.getElementById('searchInput');
const tableBody  = document.getElementById('tableBody');
const prevBtn    = document.getElementById('prevPage');
const nextBtn    = document.getElementById('nextPage');

function loadParticipants() {
  const search = encodeURIComponent(searchInput.value);
  const col    = colSelect.value;
  fetch(`event_participants_user.php?ajax=1&event_id=${eventId}&search=${search}&column=${col}&page=${page}`)
    .then(r => r.json())
    .then(j => {
      tableBody.innerHTML = '';
      j.data.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.full_name}</td>
          <td>${r.class}</td>
          <td>${r.association}</td>
          <td>${r.responsible_name}</td>
        `;
        tr.onclick = () => location.href = `edit_event_participant_user.php?id=${r.participant_id}&event_id=${eventId}`;
        tableBody.appendChild(tr);
      });
      prevBtn.style.display = j.hasPrev ? 'inline-block' : 'none';
      nextBtn.style.display = j.hasNext ? 'inline-block' : 'none';
    });
}

searchInput.addEventListener('input', () => { page = 1; loadParticipants(); });
colSelect.addEventListener('change', () => { page = 1; loadParticipants(); });
prevBtn.addEventListener('click', e => { e.preventDefault(); if (page > 1) { page--; loadParticipants(); } });
nextBtn.addEventListener('click', e => { e.preventDefault(); page++; loadParticipants(); });

window.addEventListener('DOMContentLoaded', loadParticipants);
</script>
</body>
</html>
