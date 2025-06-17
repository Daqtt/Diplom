<?php
// ========== gifted_programs_user.php ==========
session_start();
require_once 'db_config.php';

// Авторизация
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$conn = get_db_connection();

// 1) Получаем и нормализуем организацию пользователя
$stmtOrg = $conn->prepare("SELECT organization_name FROM users WHERE user_id = ?");
$stmtOrg->bind_param('i', $_SESSION['user_id']);
$stmtOrg->execute();
$userOrg = trim(mb_strtolower($stmtOrg->get_result()->fetch_assoc()['organization_name']));
$stmtOrg->close();

// 2) AJAX: поиск + пагинация с фильтрацией по org
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search   = trim($_GET['search'] ?? '');
    $column   = $_GET['column'] ?? 'all';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = 20;
    $offset   = ($page - 1) * $limit;

    // всегда фильтруем по организации
    $whereParts = ['TRIM(LOWER(gp.organization_name)) = ?'];
    $types      = 's';
    $params     = [$userOrg];

    // добавляем поиск по полям
    if ($search !== '') {
        $like = "%{$search}%";
        if ($column === 'teacher') {
            $whereParts[] = 'gp.implementer_full_name LIKE ?';
            $types       .= 's';
            $params[]     = $like;
        } elseif ($column === 'program') {
            $whereParts[] = 'gp.program_name LIKE ?';
            $types       .= 's';
            $params[]     = $like;
        } elseif ($column === 'direction') {
            $whereParts[] = 'gp.direction LIKE ?';
            $types       .= 's';
            $params[]     = $like;
        } else {
            $whereParts[] = '(
                gp.implementer_full_name LIKE ?
                OR gp.program_name LIKE ?
                OR gp.direction LIKE ?
            )';
            $types       .= 'sss';
            $params[]     = $like;
            $params[]     = $like;
            $params[]     = $like;
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $whereParts);

    // 2.1) подсчёт
    $countSQL  = "SELECT COUNT(*) AS cnt FROM gifted_programs gp $whereSQL";
    $stmtCount = $conn->prepare($countSQL);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total     = (int)$stmtCount->get_result()->fetch_assoc()['cnt'];
    $stmtCount->close();
    $totalPages = ceil($total / $limit);

    // 2.2) выбор данных
    $dataSQL = "
      SELECT
        gp.program_id,
        gp.implementer_full_name AS teacher,
        gp.program_name,
        gp.direction,
        gp.student_count,
        gp.annotation_link
      FROM gifted_programs gp
      $whereSQL
      ORDER BY gp.program_id DESC
      LIMIT ?, ?
    ";
    $stmt = $conn->prepare($dataSQL);
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$offset, $limit]));
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'data'    => $rows,
      'hasPrev' => $page > 1,
      'hasNext' => $page < $totalPages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Реализующие программы</title>
  <style>
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082);
           margin:0; padding:20px; display:flex; justify-content:center; }
    .content-container { background:rgba(255,255,255,0.95); padding:30px; border-radius:20px;
                         max-width:1200px; min-height:1000px; width:100%; display:flex; flex-direction:column; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .btn { background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff; padding:12px 24px;
           border-radius:999px; text-decoration:none; font-weight:500; }
    .search-bar { display:flex; gap:10px; margin-bottom:16px; }
    .search-bar select, .search-bar input { padding:10px; border:1px solid #ccc; border-radius:6px; }
    .search-bar input { flex:1; }
    table { width:100%; border-collapse:collapse; background:#fff; }
    th, td { padding:12px; border:1px solid #eee; text-align:left; }
    tr:hover { background:#f9f9ff; cursor:pointer; }
    .pagination { display:flex; justify-content:center; gap:20px; margin-top:20px; }
    .link { color:#4B0082; text-decoration:underline; cursor:pointer; }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard_user.php" class="btn">Главная</a>
      <h1>Реализующие программы</h1>
      <a href="add_program_user.php" class="btn">➕ Добавить</a>
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="teacher">ФИО педагога</option>
        <option value="program">Название программы</option>
        <option value="direction">Направление</option>
        <option value="all">Все</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>

    <table>
      <thead>
        <tr>
          <th>Ф.И.О. педагога</th>
          <th>Название программы</th>
          <th>Направление</th>
          <th>Кол-во обучающихся</th>
          <th>Программа (ссылка)</th>
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
    function load() {
      const s = encodeURIComponent(document.getElementById('searchInput').value);
      const c = document.getElementById('columnSelect').value;
      fetch(`gifted_programs_user.php?ajax=1&search=${s}&column=${c}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          const tbody = document.getElementById('tableBody');
          tbody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${r.teacher}</td>
              <td>${r.program_name}</td>
              <td>${r.direction || '—'}</td>
              <td>${r.student_count}</td>
              <td>${
                r.annotation_link
                  ? `<a href="${r.annotation_link}" target="_blank" class="link">${r.annotation_link}</a>`
                  : '—'
              }</td>
            `;
            tr.onclick = () => window.location = `edit_program_user.php?id=${r.program_id}`;
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        })
        .catch(console.error);
    }
    document.getElementById('searchInput').addEventListener('input', () => { page = 1; load(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; load(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if (page>1){page--;load();} };
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; load(); };
    window.addEventListener('DOMContentLoaded', load);
  </script>
</body>
</html>
