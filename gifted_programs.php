<?php
// ========== gifted_programs.php ==========
session_start();
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$conn = get_db_connection();

// AJAX: поиск + пагинация
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search   = $_GET['search']    ?? '';
    $column   = $_GET['column']    ?? 'all';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = 20;
    $offset   = ($page - 1) * $limit;
    $where    = '';
    $params   = [];
    $types    = '';
    $param    = "%{$search}%";

    if ($search !== '') {
        switch ($column) {
            case 'teacher':
                $where   = "WHERE gp.implementer_full_name LIKE ?";
                $types   = 's';
                $params  = [$param];
                break;
            case 'organization':
                $where   = "WHERE gp.organization_name LIKE ?";
                $types   = 's';
                $params  = [$param];
                break;
            case 'program':
                $where   = "WHERE gp.program_name LIKE ?";
                $types   = 's';
                $params  = [$param];
                break;
            case 'direction':
                $where   = "WHERE gp.direction LIKE ?";
                $types   = 's';
                $params  = [$param];
                break;
            default:
                $where   = "WHERE gp.implementer_full_name LIKE ?
                            OR gp.organization_name LIKE ?
                            OR gp.program_name LIKE ?
                            OR gp.direction LIKE ?";
                $types   = 'ssss';
                $params  = [$param, $param, $param, $param];
        }
    }

    // общее число записей
    $countSql = "SELECT COUNT(*) AS cnt FROM gifted_programs gp $where";
    $cntStmt  = $conn->prepare($countSql);
    if ($types) {
        $cntStmt->bind_param($types, ...$params);
    }
    $cntStmt->execute();
    $total     = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
    $totalPages= ceil($total / $limit);

    // данные текущей страницы
    $sql = "
      SELECT
        gp.program_id,
        gp.implementer_full_name AS teacher,
        gp.organization_name,
        gp.program_name,
        gp.direction,
        gp.student_count,
        gp.annotation_link
      FROM gifted_programs gp
      $where
      ORDER BY gp.program_id DESC
      LIMIT ?, ?
    ";
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$offset, $limit]));
    } else {
        $stmt->bind_param('ii', $offset, $limit);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
      'data'    => $rows,
      'hasPrev' => $page > 1,
      'hasNext' => $page < $totalPages
    ]);
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
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0;padding:20px;
      display:flex;justify-content:center;
    }
    .content-container {
      background:rgba(255,255,255,0.95);
      padding:30px;border-radius:20px;
      max-width:1200px;min-height:1000px;
      width:100%;display:flex;flex-direction:column;
    }
    .page-header {
      display:flex;justify-content:space-between;
      align-items:center;margin-bottom:20px;
    }
    .btn {
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff;padding:12px 24px;
      border-radius:999px;text-decoration:none;
      font-weight:500;
    }
    .search-bar {
      display:flex;gap:10px;
      margin-bottom:16px;width:100%;
    }
    .search-bar select {
      padding:10px;border:1px solid #ccc;
      border-radius:6px;flex:0 0 auto;
    }
    .search-bar input {
      flex:1;padding:10px;
      border:1px solid #ccc;border-radius:6px;
    }
    table {
      width:100%;border-collapse:collapse;
      background:#fff;
    }
    colgroup col:nth-child(4),
    colgroup col:nth-child(5) {
      width:15%;
    }
    th,td {
      padding:12px;border:1px solid #eee;
      text-align:left;
    }
    tr:hover {
      background:#f9f9ff;cursor:pointer;
    }
    .pagination {
      display:flex;justify-content:center;
      gap:20px;margin-top:20px;
    }
    .link {
      color:#4B0082;text-decoration:underline;
      font-weight:500;cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard.php" class="btn">Главная</a>
      <h1>Реализующие программы</h1>
      <a href="add_program.php" class="btn">➕ Добавить</a>
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="teacher">ФИО педагога</option>
        <option value="organization">Название организации</option>
        <option value="program">Название программы</option>
        <option value="direction">Направление</option>
        <option value="all">Все</option>
      </select>
      <input type="text" id="searchInput" placeholder="Поиск...">
    </div>

    <table>
      <colgroup>
        <col><col><col><col><col><col>
      </colgroup>
      <thead>
        <tr>
          <th>Ф.И.О. педагога</th>
          <th>Название организации</th>
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
      fetch(`gifted_programs.php?ajax=1&search=${s}&column=${c}&page=${page}`)
        .then(r => r.json())
        .then(j => {
          console.log(j);
          const tbody = document.getElementById('tableBody');
          tbody.innerHTML = '';
          j.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${r.teacher}</td>
              <td>${r.organization_name}</td>
              <td>${r.program_name}</td>
              <td>${r.direction || '—'}</td>
              <td>${r.student_count}</td>
              <td>${
                r.annotation_link
                  ? `<a href="${r.annotation_link}" target="_blank" class="link" onclick="event.stopPropagation()">${r.annotation_link}</a>`
                  : '—'
              }</td>
            `;
            tr.onclick = () => location.href = `edit_program.php?id=${r.program_id}`;
            tbody.appendChild(tr);
          });
          document.getElementById('prevPage').style.display = j.hasPrev ? 'inline-block' : 'none';
          document.getElementById('nextPage').style.display = j.hasNext ? 'inline-block' : 'none';
        })
        .catch(console.error);
    }

    document.getElementById('searchInput')
      .addEventListener('input', () => { page = 1; load(); });
    document.getElementById('columnSelect')
      .addEventListener('change', () => { page = 1; load(); });
    document.getElementById('prevPage').onclick = e => { e.preventDefault(); if (page > 1) { page--; load(); } };
    document.getElementById('nextPage').onclick = e => { e.preventDefault(); page++; load(); };
    window.addEventListener('DOMContentLoaded', load);
  </script>
</body>
</html>



