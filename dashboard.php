<?php
session_start();
require 'db_config.php';

// Подключаем файл с ключами и IV
$config = include __DIR__ . '/conf/key.php';

// Настройки AES-256-CBC
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY',    hex2bin($config['encryption_key_hex']));
define('ENCRYPTION_IV',     hex2bin($config['encryption_iv_hex']));

/**
 * Пытается расшифровать, если не получилось — возвращает оригинал
 */
function try_decrypt(string $ciphertext, string $original): string
{
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false || $decrypted === null ? $original : $decrypted;
}

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['user_id'])) {
        $_SESSION['user_id'] = intval($_COOKIE['user_id']);
    } else {
        header('Location: index.php');
        exit;
    }
}

$conn = get_db_connection();

// Получаем данные текущего пользователя
$stmtUser = $conn->prepare(
    'SELECT full_name, email, role, organization_name
     FROM users
     WHERE user_id = ?'
);
$stmtUser->bind_param('i', $_SESSION['user_id']);
$stmtUser->execute();
$userRes = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// Расшифровываем или оставляем как есть
$full_name = htmlspecialchars(
    try_decrypt($userRes['full_name'], $userRes['full_name']),
    ENT_QUOTES, 'UTF-8'
);
$email     = htmlspecialchars(
    try_decrypt($userRes['email'], $userRes['email']),
    ENT_QUOTES, 'UTF-8'
);
$role      = htmlspecialchars(
    try_decrypt($userRes['role'], $userRes['role']),
    ENT_QUOTES, 'UTF-8'
);
$org_name  = htmlspecialchars(
    try_decrypt($userRes['organization_name'], $userRes['organization_name']),
    ENT_QUOTES, 'UTF-8'
);

// Получаем события
$events = [];
$res = $conn->query("
    SELECT event_id, name, event_date_time
    FROM events
    WHERE DATE(event_date_time) >= CURDATE()
    ORDER BY event_date_time ASC
");
while ($row = $res->fetch_assoc()) {
    $d = substr($row['event_date_time'], 0, 10);
    $t = substr($row['event_date_time'], 11, 5);
    $title_encrypted = $row['name'];
    $title = try_decrypt($title_encrypted, $title_encrypted);
    $events[$d][] = [
        'id'    => (int)$row['event_id'],
        'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
        'time'  => $t
    ];
}
$res->free();

// Получаем ближайшие 5 конкурсов
$contests = [];
$res2 = $conn->query("
    SELECT contest_id, name, description, start_date, end_date
    FROM contests
    WHERE end_date >= CURDATE()
    ORDER BY start_date ASC
    LIMIT 5
");
while ($row = $res2->fetch_assoc()) {
    $name_enc = $row['name'];
    $desc_enc = $row['description'];
    $name = try_decrypt($name_enc, $name_enc);
    $desc = try_decrypt($desc_enc, $desc_enc);
    $contests[] = [
        'id'          => (int)$row['contest_id'],
        'name'        => htmlspecialchars($name,        ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars($desc,        ENT_QUOTES, 'UTF-8')
    ];
}
$res2->free();
$conn->close();

$events_json   = json_encode($events,   JSON_UNESCAPED_UNICODE);
$contests_json = json_encode($contests, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Главная</title>
  <style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#333; }

    /* Увеличенная полоса сверху */
    .top-bar {
      background: #8A2BE2;
      height: 80px;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      padding: 0 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }
    /* Блок пользователя */
.user-block {
  display: inline-block;
  position: relative;
  background: #5F0DA0;
  border: 2px solid #4B0082;
  border-radius: 8px;
  padding: 8px 12px;
  margin-right: 30px;
  cursor: pointer;
  transition: transform .2s, box-shadow .2s;
  color: #fff;
  min-width: 120px;
  max-width: 300px;
  overflow: visible; /* важно: выпадашка не обрезается */
}

.user-block:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.user-summary {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-summary .user-email {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: rgba(255,255,255,0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}


    /* Выпадающий блок с деталями */
    .user-block .user-details {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      background: #fff;
      color: #333;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      padding: 10px;
      min-width: 200px;
      z-index: 10;
    }
    .user-block .user-details ul {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .user-block .user-details li {
      padding: 6px 10px;
      font-size: 14px;
    }
    .user-block .user-details li + li {
      border-top: 1px solid #eee;
    }
    .user-block:hover .user-details {
      display: block;
    }

    /* Кнопка выхода */
    .top-bar .btn {
      display: inline-block;
      padding: 8px 16px;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      color: #fff;
      text-decoration: none;
      border-radius: 999px;
      font-weight: 500;
      transition: background .3s, transform .2s;
      font-size: 14px;
    }
    .top-bar .btn:hover {
      background: linear-gradient(135deg,#B39DDB,#9575CD);
      transform: scale(1.05);
    }

    .main-container {
      display: flex;
      flex-direction: column;
      max-width: 1400px;
      min-height: calc(100vh - 80px);
      margin: 10px auto;
      background: rgba(255,255,255,0.95);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    @media(min-width:768px){ .main-container { flex-direction: row; } }

    .sidebar {
      background: #F2E8FC;
      padding: 20px;
      flex-shrink: 0;
      width: 260px;
    }
    .sidebar nav ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .sidebar nav a.btn {
      display: block;
      padding: 12px 24px;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      color: #fff;
      text-align: center;
      border-radius: 999px;
      text-decoration: none;
      font-weight: 500;
      font-size: 16px;
      transition: background .3s, transform .2s;
    }
    .sidebar nav a.btn:hover {
      background: linear-gradient(135deg,#B39DDB,#9575CD);
      transform: scale(1.05);
    }

    .content { padding: 30px; flex: 1; }
    @media(max-width:767px){
      .sidebar { width: 100%; padding: 10px; }
      .sidebar nav ul { flex-direction: row; overflow-x: auto; }
      .content { padding: 20px; }
    }

    .calendar {
      max-width: 700px;
      margin: 0 auto 30px;
      background: #fff;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .calendar h3 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 10px;
      text-transform: capitalize;
    }
    .calendar table {
      width: 100%;
      border-collapse: collapse;
    }
    .calendar th, .calendar td {
      width: 14.28%;
      padding: 10px;
      text-align: center;
      border: 1px solid #eee;
      transition: background .2s;
    }
    .calendar td:hover {
      background: rgba(138,43,226,0.1);
      cursor: pointer;
      border-radius: 4px;
    }
    .calendar td.today { outline: 2px solid #8A2BE2; }
    .calendar td.has-event.today {
      background: #8A2BE2;
      color: #fff;
      border: 4px solid #fff;
      border-radius: 4px;
    }
    .calendar td.has-event:not(.today) {
      background: #8A2BE2;
      color: #fff;
      border-radius: 4px;
    }

    .tooltip {
      position: absolute;
      background: #fff;
      padding: 10px;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: none;
      z-index: 1000;
    }

    a.contest-card, a.event-card {
      display: block;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      text-decoration: none;
      color: inherit;
      transition: transform .3s, box-shadow .3s;
    }
    a.contest-card:hover, a.event-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .show-all-button {
      display: block;
      padding: 15px;
      margin: 10px auto;
      max-width: 300px;
      background: linear-gradient(135deg,#8A2BE2,#4B0082);
      color: #fff;
      text-decoration: none;
      text-align: center;
      border-radius: 999px;
      font-weight: 500;
      transition: background .3s, transform .2s;
    }
    .show-all-button:hover {
      background: linear-gradient(135deg,#B39DDB,#9575CD);
      transform: scale(1.05);
    }

    .contests h2, .events h2 { margin-bottom: 20px; }
  </style>
</head>
<body>
<div class="top-bar">
  <div class="user-block">
    <div class="user-summary">
      <?= $full_name ?>
      <span class="user-email"><?= $email ?></span>
    </div>
    <div class="user-details">
      <ul>
        <li><strong>ФИО:</strong> <?= $full_name ?></li>
        <li><strong>Email:</strong> <?= $email ?></li>
        <li><strong>Роль:</strong> <?= $role ?></li>
        <li><strong>Организация:</strong> <?= $org_name ?></li>
      </ul>
    </div>
  </div>
  <a href="logout.php" class="btn">Выйти</a>
</div>
  <div class="main-container">
    <div class="sidebar">
      <nav>
        <ul>
          <li><a href="events.php" class="btn">Список мероприятий</a></li>
          <li><a href="contests.php" class="btn">Список конкурсов</a></li>
          <li><a href="children.php" class="btn">Дети</a></li>
          <li><a href="gifted_responsibles.php" class="btn">Ответственные за одарённых</a></li>
          <li><a href="gifted_programs.php" class="btn">Программы для одарённых</a></li>
          <li><a href="summer_sessions.php" class="btn">Летние смены</a></li>
          <li><a href="users.php" class="btn">Пользователи</a></li>
          <li><a href="reports.php" class="btn">Отчёты</a></li>
        </ul>
      </nav>
    </div>

    <div class="content">
      <section class="calendar" id="calendar"></section>
      <div class="tooltip" id="tooltip"></div>

      <section class="contests">
        <h2>Конкурсы</h2>
        <?php foreach (json_decode($contests_json, true) as $c): ?>
          <a href="details_contest.php?id=<?= $c['id'] ?>" class="contest-card">
            <h3><?= $c['name'] ?></h3>
            <p><?= $c['description'] ?></p>
          </a>
        <?php endforeach; ?>
        <a href="contests.php" class="show-all-button">Показать все конкурсы</a>
      </section>

      <section class="events">
        <h2>Мероприятия</h2>
        <div id="events-list"></div>
        <a href="events.php" class="show-all-button">Показать все мероприятия</a>
      </section>
    </div>
  </div>

  <script>
    const events = <?= $events_json ?>;
    const tooltip = document.getElementById('tooltip');

    function renderCalendar() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const monthNames = ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
      const firstDayRaw = new Date(year, month, 1).getDay();
      const firstDay = (firstDayRaw + 6) % 7;
      const days = new Date(year, month + 1, 0).getDate();
      let html = `<h3>${monthNames[month]} ${year}</h3><table><tr>`;
      ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].forEach(d => html += `<th>${d}</th>`);
      html += '</tr><tr>';
      for (let i = 0; i < firstDay; i++) html += '<td></td>';
      for (let d = 1; d <= days; d++) {
        const key = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = key === `${year}-${String(month+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
        const has = events[key];
        let cls = '';
        if (has && isToday) cls = 'has-event today';
        else if (has)     cls = 'has-event';
        else if (isToday) cls = 'today';
        html += `<td class="${cls}" data-date="${key}">${d}</td>`;
        if ((firstDay + d) % 7 === 0 && d !== days) html += '</tr><tr>';
      }
      html += '</tr></table>';
      document.getElementById('calendar').innerHTML = html;

      document.querySelectorAll('td.has-event').forEach(cell => {
        cell.addEventListener('mouseenter', e => {
          const dt = e.target.dataset.date;
          let tt = `<strong>${dt.split('-').reverse().join('.')}</strong><ul>`;
          events[dt].forEach(ev => tt += `<li>${ev.time} ${ev.title}</li>`);
          tooltip.innerHTML = tt + '</ul>';
          const r = e.target.getBoundingClientRect();
          tooltip.style.top = (r.bottom + window.scrollY) + 'px';
          tooltip.style.left = (r.left + window.scrollX) + 'px';
          tooltip.style.display = 'block';
        });
        cell.addEventListener('mouseleave', () => tooltip.style.display = 'none');
      });
    }
    renderCalendar();

    // Список ближайших 5 событий
    const evArray = [];
    Object.entries(events).forEach(([d, evs]) => evs.forEach(ev => evArray.push({ date: d, time: ev.time, title: ev.title, id: ev.id })));
    evArray.sort((a,b) => new Date(`${a.date}T${a.time}`) - new Date(`${b.date}T${b.time}`));
    evArray.slice(0,5).forEach(ev => {
      const a = document.createElement('a');
      a.href = `details_event.php?id=${ev.id}`;
      a.className = 'event-card';
      a.innerHTML = `<h3>${ev.title}</h3><p>${ev.date.split('-').reverse().join('.')} ${ev.time}</p>`;
      document.getElementById('events-list').appendChild(a);
    });
  </script>
</body>
</html>
