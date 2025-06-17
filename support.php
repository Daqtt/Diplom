<?php
// support.php — ТЕСТОВАЯ ВЕРСИЯ
session_start();
require 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost'); // подставьте ваш фронт

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Метод не поддерживается']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['message']) || empty($data['name']) || empty($data['organization'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Неверные данные']));
}

$user_id      = $_SESSION['user_id'] ?? null;
$message      = trim($data['message']);
$name         = trim($data['name']);
$organization = trim($data['organization']);

if (!$user_id) {
    http_response_code(401);
    exit(json_encode(['error' => 'Неавторизованный пользователь']));
}

// 1) Сохраняем заявку в БД
$conn = get_db_connection();
$stmt = $conn->prepare("
    INSERT INTO support_messages (user_id, from_admin, text)
    VALUES (?, 0, ?)
");
$stmt->bind_param("is", $user_id, $message);
$stmt->execute();
$stmt->close();

// === TESTING: мгновенная отправка в Telegram ===
// Замените на реальные chat_id админов, чтобы проверить
$adminChatIds = [
    123456789,   // chat_id первого админа
    987654321    // chat_id второго админа
];

$botToken = '7924338819:AAFqXKBFo3N24aGXGGLEfQLm9OE4kaxmXwQ';
$text = urlencode(
    "💬 Новая заявка от {$name} ({$organization}), user_id={$user_id}:\n\n{$message}"
);
foreach ($adminChatIds as $chatId) {
    file_get_contents(
      "https://api.telegram.org/bot{$botToken}/sendMessage?"
      . "chat_id={$chatId}&text={$text}"
    );
}
// === /TESTING ===

echo json_encode(['status' => 'ok']);
exit;
