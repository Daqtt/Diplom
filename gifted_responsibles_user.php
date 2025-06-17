<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$uid  = $_SESSION['user_id'];

// 1) Получаем и нормализуем имя организации пользователя
$stmtUser = $conn->prepare("
    SELECT organization_name
      FROM users
     WHERE user_id = ?
");
$stmtUser->bind_param('i', $uid);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($resUser->num_rows !== 1) {
    die("Пользователь не найден");
}
$userOrg = trim(mb_strtolower($resUser->fetch_assoc()['organization_name']));
$stmtUser->close();

// 2) AJAX-запрос
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // параметры пагинации и поиска
    $search = trim($_GET['search'] ?? '');
    $column = $_GET['column'] ?? 'all';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    // строим WHERE-часть
    $whereParts = ['TRIM(LOWER(organization_name)) = ?'];
    $params     = [$userOrg];
    $types      = 's';

    if ($search !== '') {
        $like = "%{$search}%";
        if ($column === 'teacher') {
            $whereParts[] = 'teacher_name LIKE ?';
            $params[]     = $like;
            $types       .= 's';
        } elseif ($column === 'position') {
            $whereParts[] = 'position LIKE ?';
            $params[]     = $like;
            $types       .= 's';
        } else {
            $whereParts[] = '(teacher_name LIKE ? OR position LIKE ?)';
            $params[]     = $like;
            $params[]     = $like;
            $types       .= 'ss';
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $whereParts);

    // 2.1) подсчёт общего числа
    $countSQL  = "SELECT COUNT(*) AS cnt FROM gifted_responsibles $whereSQL";
    $stmtCount = $conn->prepare($countSQL);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total     = (int)$stmtCount->get_result()->fetch_assoc()['cnt'];
    $stmtCount->close();
    $totalPages = (int)ceil($total / $limit);

    // 2.2) получение данных
    $dataSQL = "
        SELECT responsible_id, teacher_name, position
          FROM gifted_responsibles
          $whereSQL
      ORDER BY responsible_id DESC
          LIMIT ?, ?
    ";
    $stmt = $conn->prepare($dataSQL);
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$offset, $limit]));
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'data'    => $rows,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Ответственные за одарённых</title>
  <style>
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082);
           margin:0; padding:20px; display:flex; justify-content:center; }
    .content-container { background:rgba(255,255,255,0.95); padding:30px;
                         border-radius:20px; max-width:1200px; width:100%; min-height:1000px;
                         display:flex; flex-direction:column; }
    .page-header { display:flex; justify-content:space-between; align-items:center;
                   margin-bottom:20px; }
    .btn { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff;
           padding:12px 24px; border-radius:999px; text-decoration:none; font-weight:500; }
    .search-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
    .search-bar select, .search-bar input { padding:10px; border:1px solid #ccc;
                                             border-radius:6px; }
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
      <a href="dashboard_user.php" class="btn">Главная</a>
      <h1>Ответственные за одарённых</h1>
      <a href="add_gifted_responsible_user.php" class="btn">➕ Добавить</a>
    </div>
    <div class="search-bar">
      <select id="columnSelect">
        <option value="all">Все</option>
        <option value="teacher">Преподаватель</option>
        <option value="position">Должность</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>
    <table>
      <thead><tr><th>Преподаватель</th><th>Должность</th></tr></thead>
      <tbody id="responsiblesTable"></tbody>
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
      fetch(`gifted_responsibles_user.php?ajax=1&search=${s}&column=${c}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          const tb = document.getElementById('responsiblesTable');
          tb.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.teacher_name}</td><td>${r.position}</td>`;
            tr.onclick = () => location.href = `edit_gifted_responsible_user.php?id=${r.responsible_id}`;
            tb.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        });
    };
    document.getElementById('searchInput').addEventListener('input', () => { page = 1; load(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; load(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if (page>1) { page--; load(); } };
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; load(); };
    window.addEventListener('DOMContentLoaded', load);
  </script>
</body>
</html>

