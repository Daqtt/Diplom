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
 * Пытается расшифровать; если не удалось — возвращает оригинал
 */
function try_decrypt(string $ciphertext, string $original): string
{
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false || $decrypted === null ? $original : $decrypted;
}

// Проверка сессии/куки
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['user_id'])) {
        $_SESSION['user_id'] = intval($_COOKIE['user_id']);
    } else {
        header('Location: index.php');
        exit;
    }
}

$conn = get_db_connection();

// Инфо о пользователе
$stmt = $conn->prepare("
  SELECT full_name, email, role, organization_name 
  FROM users 
  WHERE user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Дешифруем поля пользователя
$fullName = htmlspecialchars(
    try_decrypt($userRow['full_name'], $userRow['full_name']),
    ENT_QUOTES, 'UTF-8'
);
$email    = htmlspecialchars(
    try_decrypt($userRow['email'],      $userRow['email']),
    ENT_QUOTES, 'UTF-8'
);
$role     = htmlspecialchars(
    try_decrypt($userRow['role'],       $userRow['role']),
    ENT_QUOTES, 'UTF-8'
);
$orgName  = htmlspecialchars(
    try_decrypt($userRow['organization_name'], $userRow['organization_name']),
    ENT_QUOTES, 'UTF-8'
);

// Загружаем будущие мероприятия
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
    $rawTitle = $row['name'];
    $title    = try_decrypt($rawTitle, $rawTitle);
    $events[$d][] = [
        'id'    => (int)$row['event_id'],
        'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
        'time'  => $t
    ];
}
$res->free();

// Загружаем ближайшие 5 конкурсов по организации
$contests = [];
$stmt = $conn->prepare("
    SELECT contest_id, name, description
    FROM contests
    WHERE organization_name = ?
      AND end_date >= CURDATE()
    ORDER BY start_date ASC
    LIMIT 5
");
$stmt->bind_param("s", $userRow['organization_name']); // bind raw encrypted OR plaintext if unencrypted
$stmt->execute();
$rs2 = $stmt->get_result();
while ($row = $rs2->fetch_assoc()) {
    $rawName = $row['name'];
    $rawDesc = $row['description'];
    $name    = try_decrypt($rawName, $rawName);
    $desc    = try_decrypt($rawDesc, $rawDesc);
    $contests[] = [
        'id'          => (int)$row['contest_id'],
        'name'        => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')
    ];
}
$stmt->close();
$conn->close();

$events_json   = json_encode($events,   JSON_UNESCAPED_UNICODE);
$contests_json = json_encode($contests, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Главная (пользователь)</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#333 }

    /* Верхняя панель */
/* Верхняя панель */
.top-bar {
      background: #8A2BE2;
      height: 80px;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      padding: 0 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }

/* Поддержка/Выход */
.top-bar .btn {
  margin-left: 10px;
  padding: 12px 24px;                   /* тот же padding, что на dashboard */
  background: linear-gradient(135deg,#8A2BE2,#4B0082);
  color: #fff;
  text-decoration: none;
  border-radius: 999px;
  font-weight: 500;
  font-size: 14px;
  transition: background .3s, transform .2s;
}
.top-bar .btn:hover {
  background: linear-gradient(135deg,#B39DDB,#9575CD);
  transform: scale(1.05);
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



    .top-bar .btn {
      margin-left:10px; padding:8px 16px;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; text-decoration:none; border-radius:999px;
      font-weight:500; font-size:14px;
      transition:background .3s,transform .2s;
    }
    .top-bar .btn:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
      transform:scale(1.05);
    }

    .main-container {
      display:flex; flex-direction:column;
      max-width:1400px; min-height:calc(100vh - 60px);
      margin:10px auto; background:rgba(255,255,255,0.95);
      border-radius:20px; overflow:hidden;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
    }
    @media(min-width:768px){ .main-container { flex-direction:row; } }

    .sidebar {
      background:#F2E8FC; padding:20px; width:260px; flex-shrink:0;
    }
    .sidebar nav ul {
      list-style:none; display:flex; flex-direction:column; gap:12px;
    }
    .sidebar nav a.btn {
      display:block; padding:12px 24px;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; text-align:center; border-radius:999px;
      text-decoration:none; font-weight:500; font-size:16px;
      transition:background .3s,transform .2s;
    }
    .sidebar nav a.btn:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
      transform:scale(1.05);
    }

    .content { padding:30px; flex:1; }
    @media(max-width:767px){
      .sidebar{width:100%;padding:10px;}
      .sidebar nav ul{flex-direction:row;overflow-x:auto;}
      .content{padding:20px;}
    }

    /* Календарь */
    .calendar {
      max-width:700px; margin:0 auto 30px;
      background:#fff; border-radius:10px; padding:15px;
      box-shadow:0 4px 12px rgba(0,0,0,0.1);
    }
    .calendar h3 { text-align:center; font-size:20px; margin-bottom:10px; text-transform:capitalize; }
    .calendar table { width:100%; border-collapse:collapse; }
    .calendar th, .calendar td {
      width:14.28%; padding:10px; text-align:center;
      border:1px solid #eee; transition:background .2s;
    }
    .calendar td:hover {
      background:rgba(138,43,226,0.1); cursor:pointer; border-radius:4px;
    }
    .calendar td.today { outline:2px solid #8A2BE2; }
    .calendar td.has-event.today {
      background:#8A2BE2; color:#fff; border:4px solid #fff; border-radius:4px;
    }
    .calendar td.has-event:not(.today) {
      background:#8A2BE2; color:#fff; border-radius:4px;
    }

    /* Подсказка */
    .tooltip {
      position:absolute; background:#fff; padding:10px;
      border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
      display:none; z-index:1000;
    }

    /* Карточки конкурсов и событий */
    a.contest-card, a.event-card {
      display:block; background:#fff; padding:20px;
      border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
      margin-bottom:20px; text-decoration:none; color:inherit;
      transition:transform .3s, box-shadow .3s;
    }
    a.contest-card:hover, a.event-card:hover {
      transform:translateY(-5px); box-shadow:0 8px 20px rgba(0,0,0,0.15);
    }

    .show-all-button {
      display:block; padding:15px; margin:10px auto; max-width:300px;
      background:linear-gradient(135deg,#8A2BE2,#4B0082); color:#fff;
      text-align:center; text-decoration:none; border-radius:999px;
      font-weight:500; transition:background .3s, transform .2s;
    }
    .show-all-button:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
      transform:scale(1.05);
    }

    section.contests h2, section.events h2 { margin-bottom:20px; }

    /* Чат-виджет */
    .support-chat {
      position:fixed; bottom:20px; right:20px;
      width:300px; max-height:400px;
      background:#fff; border:1px solid #ccc; border-radius:8px;
      box-shadow:0 4px 12px rgba(0,0,0,0.1);
      display:none; flex-direction:column; overflow:hidden;
      font-size:14px;
    }
    .support-chat .chat-header {
      background:#8A2BE2; color:#fff; padding:10px;
      display:flex; justify-content:space-between; align-items:center;
    }
    .support-chat .chat-header span { cursor:pointer; }
    .support-chat .chat-messages {
      display:flex; flex-direction:column; gap:8px;
      padding:10px; height:200px; overflow-y:auto;
    }
    .support-chat .chat-messages .user-message {
      align-self:flex-end;
      background:#ddd;
      color:#000; padding:8px 12px; border-radius:12px;
      max-width:75%; word-wrap:break-word;
    }
    .support-chat .chat-messages .support-message {
      align-self:flex-start; background:#ccc; color:#000;
      padding:8px 12px; border-radius:12px; max-width:75%;
      word-wrap:break-word;
    }
    .support-chat .chat-input {
      display:flex; border-top:1px solid #eee;
    }
    .support-chat .chat-input input {
      flex:1; border:none; padding:10px; outline:none;
    }
    .support-chat .chat-input button {
      border:none; background:#8A2BE2; color:#fff; padding:0 15px;
      cursor:pointer;
    }
  </style>
</head>
<body>

  <!-- Верхняя панель -->
  <div class="top-bar">
  <div class="user-block">
    <div class="user-summary">
      <div class="user-name"><?= $fullName ?></div>
      <div class="user-email"><?= $email ?></div>
    </div>
    <div class="user-details">
      <ul>
        <li><span class="field">ФИО:</span><span class="value"><?= $fullName ?></span></li>
        <li><span class="field">Email:</span><span class="value"><?= $email ?></span></li>
        <li><span class="field">Роль:</span><span class="value"><?= $role ?></span></li>
        <li><span class="field">Организация:</span><span class="value"><?= $orgName ?></span></li>
      </ul>
    </div>
  </div>
  <a href="#" id="supportBtn" class="btn">Поддержка</a>
  <a href="logout.php"       class="btn">Выйти</a>
</div>


  <div class="main-container">
    <!-- Сайдбар -->
    <div class="sidebar">
      <nav>
        <ul>
          <li><a href="events_user.php" class="btn">Список мероприятий</a></li>
          <li><a href="contests_user.php" class="btn">Список конкурсов</a></li>
          <li><a href="children_user.php" class="btn">Дети</a></li>
          <li><a href="gifted_responsibles_user.php" class="btn">Ответственные за одарённых</a></li>
          <li><a href="gifted_programs_user.php" class="btn">Программы для одарённых</a></li>
          <li><a href="summer_sessions_user.php" class="btn">Летние смены</a></li>
        </ul>
      </nav>
    </div>

    <!-- Основной контент -->
    <div class="content">
      <section class="calendar" id="calendar"></section>
      <div class="tooltip" id="tooltip"></div>

      <section class="contests">
        <h2>Конкурсы</h2>
        <?php foreach(json_decode($contests_json, true) as $c): ?>
          <a href="contest_details_user.php?id=<?= $c['id'] ?>" class="contest-card">
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

  <!-- Виджет поддержки -->
  <div class="support-chat" id="supportChat">
    <div class="chat-header">
      <span>Поддержка</span>
      <span id="closeChat">✖</span>
    </div>
    <div class="chat-messages"></div>
    <div class="chat-input">
      <input type="text" id="supportMessage" placeholder="Ваше сообщение…">
      <button id="sendSupport">Отправить</button>
    </div>
  </div>

  <script>
    // Доступные в JS PHP-переменные
    const userName = <?= json_encode($fullName, JSON_HEX_TAG) ?>;
    const userOrg  = <?= json_encode($orgName, JSON_HEX_TAG) ?>;

    // Рендер календаря
    const events = <?= $events_json ?>;
    const tooltip = document.getElementById('tooltip');
    function renderCalendar() {
      const now = new Date(), year = now.getFullYear(), month = now.getMonth();
      const monthNames = ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
      const firstDay = (new Date(year, month, 1).getDay() + 6) % 7;
      const days = new Date(year, month+1, 0).getDate();
      let html = `<h3>${monthNames[month]} ${year}</h3><table><tr>`
        + ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(d=>`<th>${d}</th>`).join('')
        + `</tr><tr>`;
      for(let i=0;i<firstDay;i++) html+='<td></td>';
      for(let d=1;d<=days;d++){
        const key = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = key===`${year}-${String(month+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
        const has = events[key];
        let cls = has ? (isToday?'has-event today':'has-event') : (isToday?'today':'');
        html+=`<td class="${cls}" data-date="${key}">${d}</td>`;
        if((firstDay+d)%7===0 && d!==days) html+='</tr><tr>';
      }
      html+='</tr></table>';
      document.getElementById('calendar').innerHTML = html;
      document.querySelectorAll('td.has-event').forEach(cell=>{
        cell.addEventListener('mouseenter',e=>{
          const dt=e.target.dataset.date;
          let tt=`<strong>${dt.split('-').reverse().join('.')}</strong><ul>`;
          events[dt].forEach(ev=>tt+=`<li>${ev.time} ${ev.title}</li>`);
          tooltip.innerHTML=tt+'</ul>';
          const r=e.target.getBoundingClientRect();
          tooltip.style.top=(r.bottom+window.scrollY)+'px';
          tooltip.style.left=(r.left+window.scrollX)+'px';
          tooltip.style.display='block';
        });
        cell.addEventListener('mouseleave',()=>tooltip.style.display='none');
      });
    }
    renderCalendar();

    // Вывод 5 ближайших событий
    const evs = [];
    Object.entries(events).forEach(([d,arr])=>arr.forEach(ev=>evs.push({date:d,time:ev.time,title:ev.title,id:ev.id})));
    evs.sort((a,b)=>new Date(`${a.date}T${a.time}`)-new Date(`${b.date}T${b.time}`));
    evs.slice(0,5).forEach(ev=>{
      const a=document.createElement('a');
      a.href=`event_details_user.php?id=${ev.id}`; a.className='event-card';
      a.innerHTML=`<h3>${ev.title}</h3><p>${ev.date.split('-').reverse().join('.')} ${ev.time}</p>`;
      document.getElementById('events-list').appendChild(a);
    });

    // Чат-виджет
    const supportBtn  = document.getElementById('supportBtn');
    const supportChat = document.getElementById('supportChat');
    const closeChat   = document.getElementById('closeChat');
    const sendBtn     = document.getElementById('sendSupport');
    const input       = document.getElementById('supportMessage');
    const messages    = document.querySelector('.chat-messages');

    supportBtn.addEventListener('click', e => {
      e.preventDefault();
      supportChat.style.display = 'flex';
    });
    closeChat.addEventListener('click', () => {
      supportChat.style.display = 'none';
    });
   sendBtn.addEventListener('click', () => {
  const text = input.value.trim();
  if (!text) return;
  // добавляем свое в окно
  const div = document.createElement('div');
  div.textContent = text;
  div.className = 'user-message';
  messages.appendChild(div);
  messages.scrollTop = messages.scrollHeight;

  // отправляем все поля support.php
  fetch('support.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      message:      text,
      name:         userName,
      organization: userOrg
    })
  });

  input.value = '';
});

// получаем ответы
setInterval(() => {
  fetch('support_poll.php')
    .then(r => r.json())
    .then(data => {
      data.forEach(m => {
        const d = document.createElement('div');
        d.textContent = m.text;
        d.className = 'support-message';
        messages.appendChild(d);
      });
      if (data.length) messages.scrollTop = messages.scrollHeight;
    });
}, 3000);
  </script>
</body>
</html>




