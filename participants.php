<?php
// ========== participants.php ==========
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// Определяем роль пользователя и организацию
$uid = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT role, organization_name FROM users WHERE user_id = ?");
$uStmt->bind_param('i', $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$role = $uRow['role'] ?? '';
$org  = $uRow['organization_name'] ?? '';
$uStmt->close();

// Проверяем переданный ID конкурса
if (!isset($_GET['contest_id']) || !is_numeric($_GET['contest_id'])) {
    header("Location: " . ($role === 'admin' ? 'contests.php' : 'contests_user.php'));
    exit;
}
$contest_id = (int)$_GET['contest_id'];

// AJAX: поиск и пагинация
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['search'] ?? '';
    $column = $_GET['column'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    // Строим WHERE
    $where  = 'WHERE cp.contest_id = ?';
    $params = [$contest_id];
    $types  = 'i';

    if ($search !== '') {
        $param = "%{$search}%";
        switch ($column) {
            case 'child':
                $where   .= ' AND c.full_name LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            case 'class':
                $where   .= ' AND cp.class LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            case 'association':
                $where   .= ' AND cp.association LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            case 'responsible':
                $where   .= ' AND gr.teacher_name LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            case 'result':
                $where   .= ' AND cp.result LIKE ?';
                $types   .= 's';
                $params[] = $param;
                break;
            default:
                $where   .= ' AND ('
                          . 'c.full_name LIKE ?'
                          . ' OR cp.class LIKE ?'
                          . ' OR cp.association LIKE ?'
                          . ' OR gr.teacher_name LIKE ?'
                          . ' OR cp.result LIKE ?'
                          . ')';
                $types   .= str_repeat('s', 5);
                $params   = array_merge($params, array_fill(0, 5, $param));
        }
    }

    // Считаем общее число
    $cntSql = "
      SELECT COUNT(*) AS cnt
      FROM contest_participants cp
      LEFT JOIN children c ON cp.child_id = c.child_id
      LEFT JOIN gifted_responsibles gr ON cp.responsible_id = gr.responsible_id
      {$where}
    ";
    $cntStmt = $conn->prepare($cntSql);
    $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();
    $totalPages = ceil($total / $limit);

    // Получаем данные
    $sql = "
      SELECT
        cp.participant_id,
        c.full_name AS child_name,
        cp.class,
        cp.association,
        gr.teacher_name AS responsible_name,
        cp.result
      FROM contest_participants cp
      LEFT JOIN children c ON cp.child_id = c.child_id
      LEFT JOIN gifted_responsibles gr ON cp.responsible_id = gr.responsible_id
      {$where}
      ORDER BY c.full_name ASC
      LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    $types    .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
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
      'data'    => $rows,
      'hasPrev' => $page > 1,
      'hasNext' => $page < $totalPages,
      'page'    => $page
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получаем название конкурса
$stmtC = $conn->prepare("SELECT name FROM contests WHERE contest_id = ? AND organization_name = ?");
$stmtC->bind_param('is', $contest_id, $org);
$stmtC->execute();
$contest = $stmtC->get_result()->fetch_assoc();
$stmtC->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Участники конкурса «<?= htmlspecialchars($contest['name'], ENT_QUOTES) ?>»</title>
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
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: nowrap;
  }
  .search-bar select {
    flex: 0 0 auto;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 16px;
  }
  .search-bar input {
    flex: 1 1 auto;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 16px;
    box-sizing: border-box;
  }
    table {
      width: 100%; border-collapse: collapse;
      background: #fff;
    }
    th, td {
      padding: 12px; border: 1px solid #eee;
      text-align: left;
    }
    th.col-level, td.col-level { width: 10%; }
    th.col-age, td.col-age   { width: 10%; }
    th.col-desc, td.col-desc { width: 20%; }
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
      <a href="<?= $role === 'admin' ? 'contests.php' : 'contests_user.php' ?>" class="btn">← Назад к конкурсам</a>
      <h1>Участники: <?= htmlspecialchars($contest['name'], ENT_QUOTES) ?></h1>
      <a href="add_participant.php?contest_id=<?= $contest_id ?>" class="btn">➕ Добавить участника</a>
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="child">Ф.И.О. ребёнка</option>
        <option value="class">Класс</option>
        <option value="association">Объединение</option>
        <option value="responsible">Руководитель</option>
        <option value="result">Результат</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск участников…">
    </div>

    <table>
      <thead>
        <tr>
          <th>Ф.И.О. ребёнка</th>
          <th>Класс</th>
          <th>Объединение</th>
          <th>Руководитель</th>
          <th>Результат</th>
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
    const contestId   = <?= $contest_id ?>;
    let page          = 1;
    const colSelect   = document.getElementById('columnSelect');
    const searchInput = document.getElementById('searchInput');
    const tableBody   = document.getElementById('tableBody');
    const prevBtn     = document.getElementById('prevPage');
    const nextBtn     = document.getElementById('nextPage');

    function loadParticipants() {
      const search = encodeURIComponent(searchInput.value);
      const col    = colSelect.value;
      fetch(`participants.php?ajax=1&contest_id=${contestId}&search=${search}&column=${col}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          tableBody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${r.child_name}</td>
              <td>${r.class}</td>
              <td>${r.association}</td>
              <td>${r.responsible_name}</td>
              <td>${r.result}</td>
            `;
            tr.onclick = () => { location.href = `edit_participant.php?id=${r.participant_id}&contest_id=${contestId}`; };
            tableBody.appendChild(tr);
          });
          prevBtn.style.display = j.hasPrev ? 'inline-block' : 'none';
          nextBtn.style.display = j.hasNext ? 'inline-block' : 'none';
        });
    }

    searchInput.addEventListener('input', () => { page = 1; loadParticipants(); });
    colSelect.addEventListener('change', () => { page = 1; loadParticipants(); });
    prevBtn.addEventListener('click', e => { e.preventDefault(); if(page>1){ page--; loadParticipants(); } });
    nextBtn.addEventListener('click', e => { e.preventDefault(); page++; loadParticipants(); });

    window.addEventListener('DOMContentLoaded', loadParticipants);
  </script>
</body>
</html>
