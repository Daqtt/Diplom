<?php
// support_poll.php — вызывается JS-интервалами
session_start();
require 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}
$user_id = $_SESSION['user_id'];

// 1) Берём непрочитанные ответы
$conn = get_db_connection();
$stmt = $conn->prepare("
    SELECT id, text, created_at
    FROM support_messages
    WHERE user_id = ? AND from_admin = 1 AND is_read = 0
    ORDER BY created_at ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$ids  = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id'         => (int)$row['id'],
        'text'       => $row['text'],
        'created_at' => $row['created_at']
    ];
    $ids[] = (int)$row['id'];
}
$stmt->close();

// 2) Помечаем как прочитанные
if ($ids) {
    $in = implode(',', $ids);
    $conn->query("UPDATE support_messages SET is_read = 1 WHERE id IN ($in)");
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
exit;