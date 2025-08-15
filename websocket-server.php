<?php

require_once 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Server\WebSocketHandler;


$port = 8080;
$host = '0.0.0.0';
$dev = false;

foreach ($argv as $arg) {
    if ($arg === '--dev') {
        $dev = true;
    } elseif (preg_match('/--port=(\d+)/', $arg, $matches)) {
        $port = (int)$matches[1];
    } elseif (preg_match('/--host=(.+)/', $arg, $matches)) {
        $host = $matches[1];
    }
}

if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    if ($dev) {
        echo "🔧 Режим розробки активовано\n";
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
}

try {
    $handler = new WebSocketHandler();

    $server = IoServer::factory(
        new HttpServer(
            new WsServer($handler)
        ),
        $port,
        $host
    );

    echo "🚀 WebSocket сервер запущено на {$host}:{$port}\n";

    $server->run();
    
} catch (Exception $e) {
    echo "❌ Помилка запуску сервера: " . $e->getMessage() . "\n";
    echo "📝 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 