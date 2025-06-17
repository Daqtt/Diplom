<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['search'] ?? '';
    $column = $_GET['column'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $where = '';
    $params = [];
    $types = '';
    $param = "%$search%";

    if ($search !== '') {
        if ($column === '0') {
            $where = "WHERE name LIKE ?";
            $params[] = $param;
            $types .= 's';
        } elseif ($column === '1') {
            $where = "WHERE event_date_time LIKE ?";
            $params[] = $param;
            $types .= 's';
        } elseif ($column === '2') {
            $where = "WHERE description LIKE ?";
            $params[] = $param;
            $types .= 's';
        } elseif ($column === '3') {
            $where = "WHERE organizer LIKE ?";
            $params[] = $param;
            $types .= 's';
        } else {
            $where = "WHERE name LIKE ? OR event_date_time LIKE ? OR description LIKE ? OR organizer LIKE ?";
            $params = [$param, $param, $param, $param];
            $types = 'ssss';
        }
    }

    $count_sql = "SELECT COUNT(*) AS total FROM events $where";
    $count_stmt = $conn->prepare($count_sql);
    if ($params) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT event_id, name, event_date_time, description, organizer
            FROM events $where
            ORDER BY event_date_time DESC
            LIMIT ? OFFSET ?";
    if ($params) {
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
    } else {
        $params = [$limit, $offset];
        $types = 'ii';
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => $row['event_id'],
            'name' => $row['name'],
            'date' => date('d.m.Y H:i', strtotime($row['event_date_time'])),
            'description' => $row['description'],
            'organizer' => $row['organizer']
        ];
    }

    echo json_encode([
        'events' => $events,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $total_pages,
        'page' => $page
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список мероприятий</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #8A2BE2, #4B0082);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .content-container {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 1200px;
            min-height: 600px;
            display: flex;
            flex-direction: column;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .page-header h1 {
            flex: 1;
            text-align: center;
            margin: 0;
        }
        .btn {
      background: linear-gradient(135deg, #8A2BE2, #4B0082);
      color: white;
      padding: 8px 16px;
      border-radius: 999px;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      display: inline-block;
      transition: background 0.3s, transform 0.2s;
      white-space: nowrap;
    }

    .btn:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
      transform: scale(1.05);
    }

.btn-large {
  background: linear-gradient(135deg, #8A2BE2, #4B0082);
  color: white;
  height: 44px;            /* было 52px — уменьшено */
  padding: 0 20px;         /* чуть меньше отступы */
  border-radius: 999px;
  font-size: 14px;         /* чуть меньше шрифт */
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  transition: background 0.3s;
  white-space: nowrap;
  min-width: 120px;        /* тоже можно уменьшить */
}



    .btn-large:hover {
      background: linear-gradient(135deg, #B39DDB, #9575CD);
    }
        .search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            width: 100%;
        }
        .search-bar select {
            flex: 0 0 150px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .search-bar input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 12px;
            border: 1px solid #eee;
            text-align: left;
        }
        tr:hover {
            background: #f9f9ff;
        }
        .pagination {
            margin-top: auto;
            padding-top: 20px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
    </style>
</head>
<body>
<div class="content-container">
    <div class="page-header">
        <a href="dashboard_user.php" class="btn">Главная</a>
        <h1>Список мероприятий</h1>
        <div style="width: 100px;"></div>
    </div>

    <div class="search-bar">
        <select id="columnSelect">
            <option value="0" selected>Название</option>
            <option value="1">Дата</option>
            <option value="2">Описание</option>
            <option value="3">Организатор</option>
            <option value="all">Все поля</option>
        </select>
        <input type="text" id="searchInput" placeholder="Поиск...">
    </div>

    <table>
        <thead>
        <tr>
            <th>Название</th>
            <th>Дата</th>
            <th>Описание</th>
            <th>Организатор</th>
            <th style="width:1%;white-space:nowrap;">Действия</th>
        </tr>
        </thead>
        <tbody id="eventsTable"></tbody>
    </table>

    <div class="pagination">
        <a href="#" id="prevPage" class="btn" style="display:none">← Назад</a>
        <a href="#" id="nextPage" class="btn" style="display:none">Вперёд →</a>
    </div>
</div>

<script>
    let page = 1;

    function loadEvents() {
        const search = document.getElementById('searchInput').value;
        const column = document.getElementById('columnSelect').value;
        fetch(`events.php?ajax=1&search=${encodeURIComponent(search)}&column=${column}&page=${page}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('eventsTable');
                tbody.innerHTML = '';
                data.events.forEach(ev => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${ev.name}</td>
                        <td>${ev.date}</td>
                        <td>${ev.description}</td>
                        <td>${ev.organizer}</td>
                        <td><a href="event_participants_user.php?event_id=${ev.id}" class="btn">Участники</a></td>
                    `;
                    row.style.cursor = 'pointer';
                    row.onclick = e => {
                        if (!e.target.closest('a')) {
                            window.location.href = `event_details.php?id=${ev.id}`;
                        }
                    };
                    tbody.appendChild(row);
                });
                document.getElementById('prevPage').style.display = data.hasPrev ? 'inline-block' : 'none';
                document.getElementById('nextPage').style.display = data.hasNext ? 'inline-block' : 'none';
            });
    }

    document.getElementById('searchInput').addEventListener('input', () => { page = 1; loadEvents(); });
    document.getElementById('columnSelect').addEventListener('change', () => { page = 1; loadEvents(); });
    document.getElementById('prevPage').addEventListener('click', e => { e.preventDefault(); if (page > 1) { page--; loadEvents(); } });
    document.getElementById('nextPage').addEventListener('click', e => { e.preventDefault(); page++; loadEvents(); });

    window.addEventListener('DOMContentLoaded', loadEvents);
</script>
</body>
</html>


