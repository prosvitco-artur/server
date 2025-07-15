<?php
// Простий HTTP endpoint для Heroku health check
header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'message' => 'WebSocket Server is running',
    'timestamp' => date('Y-m-d H:i:s'),
    'websocket_url' => 'wss://' . $_SERVER['HTTP_HOST'] . '/ws'
];

echo json_encode($response, JSON_UNESCAPED_UNICODE); 