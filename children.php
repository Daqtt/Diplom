<?php
session_start();
require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}
$conn = get_db_connection();

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['search'] ?? '';
    $column = $_GET['column'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where = '';
    $params = [];
    $types = '';
    $param = "%{$search}%";
    if ($search !== '') {
        if ($column === 'name') {
            $where = "WHERE full_name LIKE ?";
            $params = [$param];
            $types = 's';
        } elseif ($column === 'class') {
            $where = "WHERE class LIKE ?";
            $params = [$param];
            $types = 's';
        } elseif ($column === 'association') {
            $where = "WHERE association LIKE ?";
            $params = [$param];
            $types = 's';
        } elseif ($column === 'organization') {
            $where = "WHERE organization_name LIKE ?";
            $params = [$param];
            $types = 's';
        } else {
            $where = "WHERE full_name LIKE ? OR class LIKE ? OR association LIKE ? OR organization_name LIKE ?";
            $params = [$param, $param, $param, $param];
            $types = 'ssss';
        }
    }

    // count total
    $countSql = "SELECT COUNT(*) AS cnt FROM children $where";
    $cntStmt = $conn->prepare($countSql);
    if ($types) {
        $cntStmt->bind_param($types, ...$params);
    }
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $totalPages = ceil($total / $limit);

    // select page data
    $sql = "SELECT child_id, full_name, birth_date, class, association, organization_name
            FROM children
            $where
            ORDER BY child_id DESC
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$offset, $limit]));
    } else {
        $stmt->bind_param('ii', $offset, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'data'    => $rows,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Дети</title>
  <style>
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082); margin:0; padding:20px; display:flex; justify-content:center; }
    .content-container { background:rgba(255,255,255,0.95); padding:30px; border-radius:20px; max-width:1200px; min-height:1000px; width:100%; display:flex; flex-direction:column; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .btn { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; padding:12px 24px; border-radius:999px; text-decoration:none; font-weight:500; }
    .search-bar { display:flex; gap:10px; margin-bottom:16px; }
    .search-bar select { padding:10px; border:1px solid #ccc; border-radius:6px; }
    .search-bar input { flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; }
    table { width:100%; border-collapse:collapse; background:#fff; }
    colgroup col:nth-child(1) { width: 25%; }
    colgroup col:nth-child(2) { width: 20%; }
    th, td { padding:12px; border:1px solid #eee; text-align:left; }
    tr:hover { background:#f9f9ff; cursor:pointer; }
    .pagination { display:flex; justify-content:center; gap:20px; margin-top:20px; }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard.php" class="btn">Главная</a>
      <h1>Дети</h1>
      <a href="add_child.php" class="btn">➕ Добавить</a>
    </div>
    <div class="search-bar">
      <select id="columnSelect">
        <option value="name">ФИО</option>
        <option value="class">Класс</option>
        <option value="association">Объединение</option>
        <option value="organization">Организация</option>
        <option value="all">Все</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>
    <table>
      <colgroup>
        <col>
        <col>
        <col>
        <col>
        <col>
      </colgroup>
      <thead>
        <tr><th>ФИО</th><th>Дата рождения</th><th>Класс</th><th>Объединение</th><th>Организация</th></tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>
    <div class="pagination">
      <button id="prevPage" class="btn" style="display:none">← Назад</button>
      <button id="nextPage" class="btn" style="display:none">Вперёд →</button>
    </div>
  </div>
  <script>
    let page = 1;
    function load() {
      const s = encodeURIComponent(document.getElementById('searchInput').value);
      const c = document.getElementById('columnSelect').value;
      fetch(`children.php?ajax=1&search=${s}&column=${c}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          const tbody = document.getElementById('tableBody');
          tbody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.full_name}</td><td>${r.birth_date}</td><td>${r.class}</td><td>${r.association}</td><td>${r.organization_name}</td>`;
            tr.onclick = () => location.href = `edit_child.php?id=${r.child_id}`;
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        });
    }
    document.getElementById('searchInput').addEventListener('input', () => { page = 1; load(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; load(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if (page > 1) { page--; load(); } };
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; load(); };
    window.addEventListener('DOMContentLoaded', load);
  </script>
</body>
</html>