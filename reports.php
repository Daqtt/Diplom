<?php
session_start();
require 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$conn = get_db_connection();

// 1) Папка для отчётов
$dir = __DIR__ . '/reports';
if (!is_dir($dir)) mkdir($dir, 0755, true);

// 2) Определяем период полугодия
$year = date('Y');
$half = ((int)date('n') <= 6) ? 1 : 2;
if ($half === 1) {
    $start      = "{$year}-01-01";
    $end        = "{$year}-06-30";
    $periodText = 'январь–июнь';
    $periodSlug = 'январь-июнь';
} else {
    $start      = "{$year}-07-01";
    $end        = "{$year}-12-31";
    $periodText = 'июль–декабрь';
    $periodSlug = 'июль-декабрь';
}

$labelText = "Отчёт за {$periodText} {$year}";
$filename  = "Отчёт_{$periodSlug}_{$year}.xls";
$fullPath  = "{$dir}/{$filename}";

// 3) Удаляем старые файлы этого же периода
foreach (glob("{$dir}/Отчёт_{$periodSlug}_{$year}*.xls") as $old) {
    if ($old !== $fullPath) {
        @unlink($old);
    }
}

// 4) Генерируем HTML-таблицу для Excel
ob_start();
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/><style>
  table { font-family:"Times New Roman",serif; font-size:14pt; border-collapse:collapse; }
  th, td { border:1px solid #000; padding:15px; }
  .contest { background:#D9EDF7; font-weight:bold; }
</style></head><body><table>';

// 4.1) Заголовок
echo "<tr><th colspan='6'>{$labelText}</th></tr>";

// 4.2) Шапка
echo '<tr>
        <th>Конкурс</th>
        <th>ФИО учащегося</th>
        <th>Организация</th>
        <th>Преподаватель</th>
        <th>Класс/Объединение</th>
        <th>Результат</th>
      </tr>';

// 4.3) Данные конкурсов и участников
$stmt = $conn->prepare("
  SELECT contest_id, name
    FROM contests
   WHERE start_date BETWEEN ? AND ?
   ORDER BY name
");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$res = $stmt->get_result();

while ($c = $res->fetch_assoc()) {
    $cName = htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8');
    echo "<tr><td class='contest' colspan='6'>{$cName}</td></tr>";

    // Считаем участников
    $cntStmt = $conn->prepare("
      SELECT COUNT(*) AS cnt
        FROM contest_participants
       WHERE contest_id = ?
    ");
    $cntStmt->bind_param('i', $c['contest_id']);
    $cntStmt->execute();
    $cnt = $cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();

    // Получаем участников
    $pStmt = $conn->prepare("
      SELECT 
        ch.full_name,
        ch.organization_name,
        IF(ch.class='', '-', ch.class) AS class_or_assoc,
        gr.teacher_name,
        cp.result
      FROM contest_participants cp
      JOIN children ch ON cp.child_id = ch.child_id
      LEFT JOIN gifted_responsibles gr ON cp.responsible_id = gr.responsible_id
     WHERE cp.contest_id = ?
     ORDER BY ch.full_name
    ");
    $pStmt->bind_param('i', $c['contest_id']);
    $pStmt->execute();
    $res2 = $pStmt->get_result();

    $firstRow = true;
    while ($p = $res2->fetch_assoc()) {
        $fn    = htmlspecialchars($p['full_name'],        ENT_QUOTES, 'UTF-8');
        $org   = htmlspecialchars($p['organization_name'], ENT_QUOTES, 'UTF-8');
        $teach = $p['teacher_name']
                 ? htmlspecialchars($p['teacher_name'], ENT_QUOTES, 'UTF-8')
                 : '-';
        $cls   = htmlspecialchars($p['class_or_assoc'],   ENT_QUOTES, 'UTF-8');
        $resu  = htmlspecialchars($p['result'],           ENT_QUOTES, 'UTF-8');

        echo '<tr>';
        if ($firstRow) {
            echo "<td rowspan='{$cnt}'></td>";
            $firstRow = false;
        }
        echo "<td>{$fn}</td>"
           . "<td>{$org}</td>"
           . "<td>{$teach}</td>"
           . "<td>{$cls}</td>"
           . "<td>{$resu}</td>"
           . '</tr>';
    }
    $pStmt->close();
}
$stmt->close();
$conn->close();

echo '</table></body></html>';
file_put_contents($fullPath, ob_get_clean());

// 5) Собираем и сортируем отчёты
$reports = glob("{$dir}/Отчёт_*.xls");
usort($reports, function($a, $b){
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Отчёты</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      display:flex; justify-content:center; padding:20px;
    }
    .content-container {
      background:rgba(255,255,255,0.95);
      padding:30px; border-radius:20px;
      box-shadow:0 12px 40px rgba(0,0,0,0.2);
      width:100%; max-width:1200px; min-height: 1000px;
      display:flex; flex-direction:column;
    }
    .page-header {
      display:flex; justify-content:space-between;
      align-items:center; margin-bottom:20px;
    }
    .btn {
      background:linear-gradient(135deg,#8A2BE2,#4B0082);
      color:#fff; padding:12px 24px;
      border-radius:999px; text-decoration:none;
      font-weight:500; transition:background .3s,transform .2s;
    }
    .btn:hover {
      background:linear-gradient(135deg,#B39DDB,#9575CD);
      transform:scale(1.05);
    }
    /* Вертикальный список карточек */
    .reports-list {
      display:flex;
      flex-direction:column;
      gap:20px;
      margin-top:20px;
    }
    .reports-list a {
      background:#fff; padding:20px;
      border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
      text-decoration:none; color:inherit;
      transition:transform .3s,box-shadow .3s;
    }
    .reports-list a:hover {
      transform:translateY(-5px);
      box-shadow:0 8px 20px rgba(0,0,0,0.15);
    }
    .reports-list a h3 {
      font-size:14px; margin:0; font-weight:500;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="page-header">
      <a href="dashboard.php" class="btn">Главная</a>
      <h1>Отчёты</h1>
      <a href="reports.php" class="btn">Обновить</a>
    </div>
    <div class="reports-list">
      <?php foreach ($reports as $path):
          $file = basename($path, '.xls');
          list(, $slugPeriod, $slugYear) = explode('_', $file);
          // человекочитаемый заголовок
          $period = $slugPeriod === 'январь-июнь' ? 'январь–июнь' : 'июль–декабрь';
          $title  = "Отчёт за {$period} {$slugYear}";
      ?>
        <a href="reports/<?=urlencode(basename($path))?>" download>
          <h3><?=htmlspecialchars($title)?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>


















