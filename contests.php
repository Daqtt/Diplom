<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

// Параметры пагинации
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;
$offset     = ($page - 1) * $limit;

// Считаем общее число конкурсов
$countRes   = $conn->query("SELECT COUNT(*) AS cnt FROM contests");
$total      = $countRes->fetch_assoc()['cnt'];
$totalPages = (int)ceil($total / $limit);

// Получаем нужную страницу, включая организацию
$stmt = $conn->prepare("    
    SELECT contest_id, name, order_number, level, start_date, end_date, age_category, description, organization_name
      FROM contests
     ORDER BY start_date DESC
     LIMIT ?, ?");
$stmt->bind_param('ii', $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Список конкурсов</title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0; padding:20px;
      display:flex;justify-content:center;
    }
    .content-container {
      background:rgba(255,255,255,0.95);
      padding:30px; border-radius:20px;
      width:100%; max-width:1200px; min-height: 1000px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
      display:flex; flex-direction:column;
    }
    .page-header {
      display:flex; justify-content:space-between; align-items:center;
      margin-bottom:20px;
    }
    .btn {
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; padding:12px 24px;
      border-radius:999px; text-decoration:none;
      font-weight:500; font-size:16px;
      display:inline-block; transition:background .3s,transform .2s;
    }
    .btn:hover {
      background: linear-gradient(135deg,#9575CD,#B39DDB);
      transform:scale(1.05);
    }

    .search-bar {
      display:flex; gap:10px;
      margin-bottom:16px; flex-wrap:wrap;
    }
    .search-bar select {
      flex:0 0 auto;
      padding:10px; border:1px solid #ccc; border-radius:6px;
      font-size:16px;
    }
    .search-wrapper {
      position:relative; flex:1;
    }
    .search-wrapper input {
      width:100%; padding:10px;
      border:1px solid #ccc; border-radius:6px;
      font-size:16px; box-sizing:border-box;
    }
    .search-suggestions {
      position:absolute; top:100%; left:0;
      background:#fff; border:1px solid #ccc;
      width:100%; max-height:200px; overflow-y:auto;
      display:none;
    }
    .search-suggestions div {
      padding:10px; cursor:pointer;
    }
    .search-suggestions div:hover {
      background:#eee;
    }

    table {
      width:100%; border-collapse:collapse;
      background:#fff; margin-bottom:20px;
    }
    th, td {
      padding:12px; border:1px solid #eee;
      text-align:left;
    }
    th.col-level, td.col-level { width:10%; }
    th.col-age,   td.col-age   { width:10%; }
    th.col-desc,  td.col-desc  { width:20%; }
    th.col-org,   td.col-org   { width:15%; }
    tbody tr:hover { background:#f9f9ff; cursor:pointer; }

    .actions a.btn {
      margin:0; padding:8px 16px; font-size:14px;
    }
    th.actions { width:1%; }

    .pagination {
      display:flex; justify-content:center; gap:20px;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard.php" class="btn">Главная</a>
      <h1>Список конкурсов</h1>
      <a href="add_contest.php" class="btn">➕ Добавить</a>
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="name">Название</option>
        <option value="order_number">Номер приказа</option>
        <option value="level">Уровень</option>
        <option value="duration">Сроки</option>
        <option value="age_category">Возраст</option>
        <option value="description">Описание</option>
        <option value="organization_name">Организация</option>
      </select>
      <div class="search-wrapper">
        <input type="text" id="searchInput" placeholder="Поиск...">
        <div class="search-suggestions" id="suggestions"></div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Название конкурса</th>
          <th>Номер приказа</th>
          <th class="col-level">Уровень</th>
          <th>Сроки</th>
          <th class="col-age">Возраст</th>
          <th class="col-desc">Описание</th>
          <th class="col-org">Организация</th>
          <th class="actions"></th>
        </tr>
      </thead>
      <tbody id="contestsTable">
        <?php while ($row = $result->fetch_assoc()):
            $start    = date('d.m.Y', strtotime($row['start_date']));
            $end      = $row['end_date'] ? date('d.m.Y', strtotime($row['end_date'])) : '';
            $duration = $start . ($end ? " – {$end}" : '');
        ?>
        <tr data-id="<?= $row['contest_id'] ?>">
          <td class="cell-name"><?= htmlspecialchars($row['name'], ENT_QUOTES) ?></td>
          <td class="cell-order_number"><?= htmlspecialchars($row['order_number'], ENT_QUOTES) ?></td>
          <td class="cell-level"><?= htmlspecialchars($row['level'], ENT_QUOTES) ?></td>
          <td class="cell-duration"><?= $duration ?></td>
          <td class="cell-age_category"><?= htmlspecialchars($row['age_category'], ENT_QUOTES) ?></td>
          <td class="cell-description"><?= nl2br(htmlspecialchars($row['description'], ENT_QUOTES)) ?></td>
          <td class="cell-organization_name"><?= htmlspecialchars($row['organization_name'], ENT_QUOTES) ?></td>
          <td class="actions">
            <a href="participants.php?contest_id=<?= $row['contest_id'] ?>"
               class="btn" onclick="event.stopPropagation()">Участники</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn">← Назад</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn">Вперёд →</a>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const columnSelect = document.getElementById('columnSelect');
    const searchInput  = document.getElementById('searchInput');
    const rows         = Array.from(document.querySelectorAll('#contestsTable tr'));

    function filterTable() {
      const filter = searchInput.value.toLowerCase();
      const col    = columnSelect.value;

      rows.forEach(row => {
        let text;
        if (col === 'all') {
          text = row.textContent.toLowerCase();
        } else {
          text = row.querySelector(`.cell-${col}`)?.textContent.toLowerCase() || '';
        }
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    }

    searchInput.addEventListener('input', filterTable);
    columnSelect.addEventListener('change', () => {
      searchInput.value = '';
      filterTable();
    });

    rows.forEach(row => {
      row.addEventListener('click', () => {
        const id = row.getAttribute('data-id');
        window.location.href = `edit_contest.php?id=${id}`;
      });
    });
  </script>
</body>
</html>