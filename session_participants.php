<?php
// ========== session_participants.php ==========
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$conn = get_db_connection();

// ==== AJAX: поиск и пагинация ====
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
    $search     = $_GET['search']   ?? '';
    $column     = $_GET['column']   ?? 'all';
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = 20;
    $offset     = ($page - 1) * $limit;

    // Базовый WHERE: только для этой смены
    $where  = 'WHERE p.session_id = ?';
    $types  = 'i';
    $params = [$session_id];

    // Добавляем поисковый фильтр, если нужно
    if ($search !== '') {
        $param = "%{$search}%";
        switch ($column) {
            case 'org':
                $where   .= ' AND c.organization_name LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            // ... остальные case ...
            default:
                $where   .= ' AND (c.organization_name LIKE ? OR c.full_name LIKE ?'
                           . ' OR p.class LIKE ? OR p.association LIKE ?'
                           . ' OR gr.teacher_name LIKE ? OR p.result LIKE ?)';
                $types   .= str_repeat('s', 6);
                $params  = array_merge($params, array_fill(0, 6, $param));
        }
    }

    // Считаем общее число
    $countSql = "
        SELECT COUNT(*) AS cnt
          FROM summer_session_participants p
          JOIN children c ON p.child_id = c.child_id
     LEFT JOIN gifted_responsibles gr ON p.responsible_id = gr.responsible_id
         {$where}
    ";
    $cntStmt = $conn->prepare($countSql);
    $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();
    $totalPages = ceil($total / $limit);

    // Выборка данных
    $sql = "
        SELECT
          p.participant_id,
          p.organization_name,
          c.full_name   AS child_name,
          p.class,
          p.association,
          gr.teacher_name AS leader_name,
          p.result
        FROM summer_session_participants p
        JOIN children c ON p.child_id = c.child_id
        LEFT JOIN gifted_responsibles gr ON p.responsible_id = gr.responsible_id
        {$where}
        ORDER BY c.full_name
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    // добавляем лимит и офсет к строке типов и к параметрам
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'data'    => $rows,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages,
        'page'    => $page
    ]);
    exit;
}

// Обычный запрос страницы
// Проверяем ID смены
if (!isset($_GET['session_id']) || !is_numeric($_GET['session_id'])) {
    header('Location: summer_sessions.php');
    exit;
}
$session_id = (int)$_GET['session_id'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Участники смены</title>
  <style>
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0;padding:20px;
      display:flex;justify-content:center;
    }
    .container {
      background:rgba(255,255,255,0.95);
      padding:30px;border-radius:20px;
      width:100%;max-width:1200px; min-height: 1000px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
      display:flex;flex-direction:column;
    }
    .header {
      display:flex;justify-content:space-between;align-items:center;
      margin-bottom:20px;
    }
    .btn {
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff;padding:12px 24px;
      border-radius:999px;text-decoration:none;
      font-weight:500;font-size:16px;
      transition:background .3s,transform .2s;
    }
    .btn:hover {
      background:linear-gradient(135deg,#9575CD,#B39DDB);
      transform:scale(1.05);
    }
    .search-bar {
      display:flex;gap:10px;margin-bottom:16px;
      flex-wrap:wrap;
    }
    .search-bar select {
      padding:10px;border:1px solid #ccc;border-radius:6px;
    }
    .search-wrapper {
      position:relative;flex:1;
    }
    .search-wrapper input {
      width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;
    }
    .search-suggestions {
      position:absolute;top:100%;left:0;
      background:#fff;border:1px solid #ccc;
      width:100%;max-height:200px;overflow-y:auto;
      display:none;
    }
    .search-suggestions div {
      padding:10px;cursor:pointer;
    }
    .search-suggestions div:hover {
      background:#eee;
    }
    table {
      width:100%;border-collapse:collapse;background:#fff;
    }
    th,td {
      padding:12px;border:1px solid #eee;text-align:left;
    }
    th.col-org,    td.col-org    { width:20%; }
    th.col-child,  td.col-child  { width:20%; }
    th.col-class,  td.col-class  { width:10%; }
    th.col-assoc,  td.col-assoc  { width:15%; }
    th.col-leader, td.col-leader { width:20%; }
    th.col-result, td.col-result { width:15%; }
    tbody tr:hover {
      background:#f9f9ff;cursor:pointer;
    }
    .pagination {
      margin-top:auto;padding-top:20px;
      display:flex;justify-content:center;gap:20px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="summer_sessions.php" class="btn">← Смены</a>
      <h1>Участники смены</h1>
      <a href="add_session_participant.php?session_id=<?= $session_id ?>" class="btn">➕ Добавить</a>
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="org">Организация</option>
        <option value="child">Ф.И.О. ребёнка</option>
        <option value="class">Класс</option>
        <option value="assoc">Объединение</option>
        <option value="leader">Руководитель</option>
        
      </select>
      <div class="search-wrapper">
        <input type="text" id="searchInput" placeholder="Поиск...">
        <div class="search-suggestions" id="suggestions"></div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th class="col-org">Организация</th>
          <th class="col-child">Ф.И.О. ребёнка</th>
          <th class="col-class">Класс</th>
          <th class="col-assoc">Объединение</th>
          <th class="col-leader">Руководитель</th>
          <th class="col-result">Результат</th>
        </tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>

    <div class="pagination">
      <a href="#" id="prevPage" class="btn" style="display:none">← Назад</a>
      <a href="#" id="nextPage" class="btn" style="display:none">Вперёд →</a>
    </div>
  </div>

  <script>
    const sessionId = <?= $session_id ?>;
    let page = 1;

    function loadParticipants() {
      const search = encodeURIComponent(document.getElementById('searchInput').value);
      const column = document.getElementById('columnSelect').value;
      fetch(`session_participants.php?ajax=1&session_id=${sessionId}&search=${search}&column=${column}&page=${page}`)
        .then(res => res.json())
        .then(j => {
          const tbody = document.getElementById('tableBody');
          tbody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${r.organization_name}</td>
              <td>${r.child_name}</td>
              <td>${r.class}</td>
              <td>${r.association}</td>
              <td>${r.leader_name}</td>
              <td>${r.result}</td>
            `;
            tr.onclick = () => location.href = `edit_session_participant.php?id=${r.participant_id}&session_id=${sessionId}`;
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        });
    }

    document.getElementById('searchInput').addEventListener('input', () => { page = 1; loadParticipants(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; loadParticipants(); });
    document.getElementById('prevPage').addEventListener('click', e => { e.preventDefault(); if (page>1) { page--; loadParticipants(); } });
    document.getElementById('nextPage').addEventListener('click', e => { e.preventDefault(); page++; loadParticipants(); });

    window.addEventListener('DOMContentLoaded', loadParticipants);
  </script>
</body>
</html>

