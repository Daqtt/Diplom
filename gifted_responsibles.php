<?php
// ========== gifted_responsibles.php ==========
session_start();
require 'db_config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// AJAX-обработка данных с пагинацией
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['search'] ?? '';
    $column = $_GET['column'] ?? 'all';
    $page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    // Условия поиска
    $where = '';
    $params = [];
    $types = '';
    $param = "%$search%";
    if ($search !== '') {
        if ($column === 'teacher') {
            $where = "WHERE teacher_name LIKE ?";
            $params = [$param];
            $types = 's';
        } elseif ($column === 'organization') {
            $where = "WHERE organization_name LIKE ?";
            $params = [$param];
            $types = 's';
        } elseif ($column === 'position') {
            $where = "WHERE position LIKE ?";
            $params = [$param];
            $types = 's';
        } else {
            $where = "WHERE teacher_name LIKE ? OR organization_name LIKE ? OR position LIKE ?";
            $params = [$param, $param, $param];
            $types = 'sss';
        }
    }

    // Подсчет общего числа записей
    $countSql = "SELECT COUNT(*) AS cnt FROM gifted_responsibles $where";
    $cntStmt = $conn->prepare($countSql);
    if ($types) {
        $cntStmt->bind_param($types, ...$params);
    }
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $total_pages = ceil($total / $limit);

    // Выборка данных
    $sql = "SELECT responsible_id, teacher_name, organization_name, position
            FROM gifted_responsibles
            $where
            ORDER BY responsible_id DESC
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($types) {
        $all = array_merge($params, [$offset, $limit]);
        $stmt->bind_param($types . 'ii', ...$all);
    } else {
        $stmt->bind_param('ii', $offset, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'data'    => $rows,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $total_pages
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Ответственные за одарённых</title>
  <style>
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082); margin:0; padding:20px; display:flex; justify-content:center; }
    .content-container { background:rgba(255,255,255,0.95); padding:30px; border-radius:20px; box-shadow:0 12px 40px rgba(0,0,0,0.2); width:100%; max-width:1200px; min-height: 1000px; display:flex; flex-direction:column; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .btn { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; padding:12px 24px; border-radius:999px; text-decoration:none; font-weight:500; }
    .search-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
    .search-bar select, .search-bar input { padding:10px; border-radius:6px; border:1px solid #ccc; }
    .search-bar input { flex:1; }
    table { width:100%; border-collapse:collapse; background:#fff; }
    th, td { padding:12px; border:1px solid #eee; text-align:left; }
    tr:hover { background:#f9f9ff; cursor:pointer; }
    .pagination { display:flex; justify-content:center; gap:20px; margin-top:20px; }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard.php" class="btn">Главная</a>
      <h1>Ответственные за одарённых</h1>
      <a href="add_gifted_responsible.php" class="btn">➕ Добавить</a>
    </div>
    <div class="search-bar">
      <select id="columnSelect">
        <option value="teacher">Преподаватель</option>
        <option value="organization">Организация</option>
        <option value="position">Должность</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>
    <table>
      <thead>
        <tr><th>Преподаватель</th><th>Организация</th><th>Должность</th></tr>
      </thead>
      <tbody id="responsiblesTable"></tbody>
    </table>
    <div class="pagination">
      <a href="#" id="prevPage" class="btn" style="display:none">← Назад</a>
      <a href="#" id="nextPage" class="btn" style="display:none">Вперёд →</a>
    </div>
  </div>
  <script>
    let page = 1;
    function loadData() {
      const search = encodeURIComponent(document.getElementById('searchInput').value);
      const column = document.getElementById('columnSelect').value;
      fetch(`gifted_responsibles.php?ajax=1&search=${search}&column=${column}&page=${page}`)
        .then(res => res.json())
        .then(json => {
          const tbody = document.getElementById('responsiblesTable'); tbody.innerHTML = '';
          json.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.teacher_name}</td><td>${r.organization_name}</td><td>${r.position}</td>`;
            tr.addEventListener('click', () => location.href = `edit_gifted_responsible.php?id=${r.responsible_id}`);
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = json.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = json.hasNext ? 'inline-block' : 'none';
        });
    }
    document.getElementById('searchInput').addEventListener('input', () => { page = 1; loadData(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; loadData(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if (page > 1) { page--; loadData(); } };
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; loadData(); };
    window.addEventListener('DOMContentLoaded', loadData);
  </script>
</body>
</html>


