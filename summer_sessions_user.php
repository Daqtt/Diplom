<?php
// ========== summer_sessions_user.php ==========
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$conn = get_db_connection();

// Получаем данные авторизованного пользователя
$userStmt = $conn->prepare("
    SELECT organization_name, role
      FROM users
     WHERE user_id = ?
");
$userStmt->bind_param('i', $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$isAdmin = ($user['role'] === 'admin');
$userOrg = $user['organization_name'];

// Параметры пагинации
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

// Считаем общее число смен
if ($isAdmin) {
    $countSql = "SELECT COUNT(*) AS cnt FROM summer_sessions";
    $countRes = $conn->query($countSql);
    $total    = $countRes->fetch_assoc()['cnt'];
} else {
    $countStmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.session_id) AS cnt
          FROM summer_sessions s
          JOIN summer_session_participants p ON s.session_id = p.session_id
         WHERE p.organization_name = ?
    ");
    $countStmt->bind_param('s', $userOrg);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['cnt'];
    $countStmt->close();
}
$totalPages = (int)ceil($total / $limit);

// Получаем страницу смен
if ($isAdmin) {
    $stmt = $conn->prepare("
      SELECT session_id, name, start_date, end_date, age_category, description
        FROM summer_sessions
       ORDER BY start_date DESC
       LIMIT ?, ?
    ");
    $stmt->bind_param('ii', $offset, $limit);
} else {
    $stmt = $conn->prepare("
      SELECT DISTINCT s.session_id, s.name, s.start_date, s.end_date, s.age_category, s.description
        FROM summer_sessions s
        JOIN summer_session_participants p ON s.session_id = p.session_id
       WHERE p.organization_name = ?
       ORDER BY s.start_date DESC
       LIMIT ?, ?
    ");
    $stmt->bind_param('sii', $userOrg, $offset, $limit);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Летние смены</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      margin:0; padding:20px;
      display:flex; justify-content:center;
    }
    .content-container {
      background:rgba(255,255,255,0.95);
      padding:30px; border-radius:20px;
      width:100%; max-width:1200px; min-height: 1000px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
      display:flex; flex-direction:column;
    }
  .page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  padding: 10px 0;     /* чтобы дать контейнеру явную высоту */
  margin-bottom: 20px;
}

.page-header h1 {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  margin: 0;
}




    .btn {
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; padding:12px 24px;
      border-radius:999px; text-decoration:none;
      font-weight:500; font-size:16px;
      transition:background .3s,transform .2s;
      display:inline-block;
    }
    .btn:hover {
      background:linear-gradient(135deg,#9575CD,#B39DDB);
      transform:scale(1.05);
    }
    .search-bar {
      display:flex; gap:10px; margin-bottom:16px;
    }
    .search-bar select,
    .search-bar input {
      padding:10px; border:1px solid #ccc; border-radius:6px;
      font-size:16px;
    }
    .search-bar select { flex:0 0 180px; }
    .search-bar input  { flex:1; }
    table {
      width:100%; border-collapse:collapse; background:#fff;
      margin-bottom:20px;
    }
    th, td {
      padding:12px; border:1px solid #eee; text-align:left;
    }
    /* Теперь все колонки имеют одинаковый класс на TH и TD */
    th.col-name, td.col-name     { width:32%; }
    th.col-period, td.col-period { width:20%; }
    th.col-age,    td.col-age    { width:12%; }
    th.col-desc,   td.col-desc   { width:35%; }
    th.actions { width:1%; }
    tbody tr:hover { background:#f9f9ff; cursor:pointer; }
    .pagination {
      display:flex; justify-content:center; gap:20px;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard_user.php" class="btn">Главная</a>
      <h1>Летние смены</h1>
      
    </div>

    <div class="search-bar">
      <select id="columnSelect">
        <option value="name">Название</option>
        <option value="period">Сроки</option>
        <option value="age_category">Возраст</option>
        <option value="description">Описание</option>
      </select>
      <input
        type="text"
        id="searchInput"
        placeholder="Введите для фильтрации">
    </div>

    <table>
      <thead>
        <tr>
          <th class="col-name">Название смены</th>
          <th class="col-period">Сроки</th>
          <th class="col-age">Возрастная категория</th>
          <th class="col-desc">Описание</th>
          <th class="actions">Участники</th>
        </tr>
      </thead>
      <tbody id="sessionsTable">
        <?php while ($row = $result->fetch_assoc()):
            $start  = date('d.m.Y', strtotime($row['start_date']));
            $end    = date('d.m.Y', strtotime($row['end_date']));
            $period = "{$start} – {$end}";
        ?>
        <tr data-id="<?= $row['session_id'] ?>">
          <td class="col-name"><?= htmlspecialchars($row['name'], ENT_QUOTES) ?></td>
          <td class="col-period"><?= $period ?></td>
          <td class="col-age"><?= htmlspecialchars($row['age_category'], ENT_QUOTES) ?></td>
          <td class="col-desc"><?= nl2br(htmlspecialchars($row['description'], ENT_QUOTES)) ?></td>
          <td class="actions">
            <a href="session_participants_user.php?session_id=<?= $row['session_id'] ?>"
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
    const colSelect   = document.getElementById('columnSelect');
    const searchInput = document.getElementById('searchInput');
    const rows        = Array.from(document.querySelectorAll('#sessionsTable tr'));

    function filterRows() {
      const filter = searchInput.value.toLowerCase();
      const col    = colSelect.value;
      rows.forEach(row => {
        let text;
        if (col === 'all') {
          text = row.textContent.toLowerCase();
        } else if (col === 'period') {
          text = row.querySelector('.col-period').textContent.toLowerCase();
        } else {
          // Для name, age_category и description мы ищем по td.col-XXX
          const selector = col === 'name'
            ? '.col-name'
            : (col === 'age_category' ? '.col-age' : '.col-desc');
          text = (row.querySelector(selector)?.textContent || '').toLowerCase();
        }
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    }

    searchInput.addEventListener('input', filterRows);
    colSelect.addEventListener('change', () => { searchInput.value = ''; filterRows(); });

    rows.forEach(row => {
      row.addEventListener('click', () => {
        const id = row.getAttribute('data-id');
        window.location.href = `details_session.php?session_id=${id}`;
      });
    });
  </script>
</body>
</html>

