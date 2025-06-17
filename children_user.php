<?php
// ========== children_user.php ==========
session_start();
require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

// Определяем организацию текущего пользователя и роль
$conn = get_db_connection();
$uid = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT organization_name, role FROM users WHERE user_id = ?");
$uStmt->bind_param('i', $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$userOrg  = $uRow['organization_name'];
$userRole = $uRow['role'];
$uStmt->close();

// AJAX-запрос: поиск и пагинация
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = trim($_GET['search'] ?? '');
    $column = $_GET['column'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    // Фильтр по организации для всех кроме admin
    $where = '';
    $params = [];
    $types = '';
    if ($userRole !== 'admin') {
        $where = 'WHERE organization_name = ?';
        $types  = 's';
        $params = [$userOrg];
    }
    // Поиск
    if ($search !== '') {
        $param = "%{$search}%";
        $fields = ['full_name','class','association'];
        if (in_array($column, ['name','class','association'])) {
            $colMap = ['name'=>'full_name','class'=>'class','association'=>'association'];
            $where .= $where ? " AND {$colMap[$column]} LIKE ?" : "WHERE {$colMap[$column]} LIKE ?";
            $types .= 's';
            $params[] = $param;
        } else {
            $conds = [];
            foreach ($fields as $f) {
                $conds[] = "$f LIKE ?";
                $params[] = $param;
                $types .= 's';
            }
            $clause = implode(' OR ', $conds);
            $where .= $where ? " AND ($clause)" : "WHERE ($clause)";
        }
    }

    // Подсчёт
    $cntSql = "SELECT COUNT(*) AS cnt FROM children $where";
    $cntStmt = $conn->prepare($cntSql);
    if ($types) $cntStmt->bind_param($types, ...$params);
    $cntStmt->execute();
    $total = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();
    $totalPages = ceil($total / $limit);

    // Данные страницы
    $sql = "SELECT child_id, full_name, birth_date, class, association
            FROM children $where
            ORDER BY full_name ASC
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data'=>$rows,'hasPrev'=>$page>1,'hasNext'=>$page<$totalPages],JSON_UNESCAPED_UNICODE);
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
    .content-container { background:rgba(255,255,255,0.95); padding:30px; border-radius:20px; max-width:1200px; width:100%; min-height:1000px; display:flex; flex-direction:column; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .btn { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; padding:12px 24px; border-radius:999px; text-decoration:none; font-weight:500; }
    .search-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:nowrap; }
    .search-bar select { flex:0 0 auto; padding:10px; border:1px solid #ccc; border-radius:6px; }
    .search-bar input { flex:1 1 auto; padding:10px; border:1px solid #ccc; border-radius:6px; }
    table { width:100%; border-collapse:collapse; background:#fff; }
    th, td { padding:12px; border:1px solid #eee; text-align:left; }
    th.col-name, td.col-name { width:30%; }
    th.col-birth, td.col-birth { width:20%; }
    th.col-class, td.col-class { width:15%; }
    th.col-assoc, td.col-assoc { width:25%; }
    tbody tr:hover { background:#f9f9ff; cursor:pointer; }
    .pagination { display:flex; justify-content:center; gap:20px; margin-top:20px; }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard_user.php" class="btn">Главная</a>
      <h1>Дети</h1>
      <a href="add_child_user.php" class="btn">➕ Добавить</a>
    </div>
    <div class="search-bar">
      <select id="columnSelect">
        <option value="name">ФИО</option>
        <option value="class">Класс</option>
        <option value="association">Объединение</option>
        <option value="all">Все</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>
    <table>
      <colgroup>
        <col class="col-name">
        <col class="col-birth">
        <col class="col-class">
        <col class="col-assoc">
      </colgroup>
      <thead>
        <tr>
          <th>ФИО</th>
          <th>Дата рождения</th>
          <th>Класс</th>
          <th>Объединение</th>
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
    let page = 1;
    const load = () => {
      const s = encodeURIComponent(document.getElementById('searchInput').value);
      const c = document.getElementById('columnSelect').value;
      fetch(`children_user.php?ajax=1&search=${s}&column=${c}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          const tbody = document.getElementById('tableBody'); tbody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${r.full_name}</td>
              <td>${r.birth_date}</td>
              <td>${r.class}</td>
              <td>${r.association}</td>
            `;
            tr.onclick = () => location.href = `edit_child_user.php?id=${r.child_id}`;
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        });
    };
    document.getElementById('searchInput').addEventListener('input', () => { page=1; load(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page=1; load(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if(page>1){page--;load();}};
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; load(); };
    window.addEventListener('DOMContentLoaded', load);
  </script>
</body>
</html>

