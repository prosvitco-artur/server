#!/bin/bash

echo "🌐 Запуск Cloudflare Tunnel для WebSocket сервера"
echo "================================================="

# Перевірка чи запущено WebSocket сервер
if ! pgrep -f "websocket-server.php" > /dev/null; then
    echo "⚠️  WebSocket сервер не запущено!"
    echo "📝 Спочатку запустіть: php websocket-server.php --dev"
    exit 1
fi

echo "✅ WebSocket сервер працює на localhost:8080"
echo "🚀 Створюю Cloudflare тунель..."

# Запуск тимчасового тунелю (не потребує налаштування домену)
cloudflared tunnel --url http://localhost:8080

echo "🔗 Тунель створено! Використовуйте надану URL для підключення."
echo "⚠️  Це тимчасовий тунель - URL зміниться після перезапуску."