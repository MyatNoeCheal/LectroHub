<?php
/**
 * chatbot_proxy.php -- server-side gateway between the browser and LectroBot.
 *
 * The browser never tells the chatbot WHO the user is. The user id is read
 * from the PHP session here and forwarded to the Flask service together with
 * a shared secret, so a visitor cannot ask about someone else's orders by
 * sending a different user_id.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$configFile = __DIR__ . '/../chatbot_config.local.php';
if (!file_exists($configFile)) {
    error_log('[CHATBOT] chatbot_config.local.php is missing.');
    http_response_code(503);
    echo json_encode(['text' => 'The assistant is not available right now.']);
    exit;
}
require_once $configFile;

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim((string)($input['message'] ?? ''));

if ($message === '' || mb_strlen($message) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

/* Identity comes ONLY from the server-side session. */
$user_id = null;
if (($_SESSION['user']['role'] ?? '') === 'user' && isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
}

$ch = curl_init(rtrim(CHATBOT_API_URL, '/') . '/predict');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['message' => $message, 'user_id' => $user_id]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Chatbot-Secret: ' . CHATBOT_SHARED_SECRET,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errno    = curl_errno($ch);
curl_close($ch);

if ($errno !== 0 || $code !== 200 || $response === false) {
    error_log("[CHATBOT] upstream failure (errno={$errno}, http={$code})");
    http_response_code(502);
    echo json_encode(['text' => 'Sorry, I\'m having trouble connecting right now.']);
    exit;
}

echo $response;
