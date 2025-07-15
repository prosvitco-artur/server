#!/bin/bash

# Скрипт розгортання WebSocket сервера на Heroku

echo "🚀 Розгортання WebSocket сервера на Heroku"
echo "=========================================="

# Перевірка наявності Heroku CLI
if ! command -v heroku &> /dev/null; then
    echo "❌ Heroku CLI не встановлено"
    echo "📦 Встановлення Heroku CLI..."
    
    # Для Ubuntu/Debian
    if command -v apt-get &> /dev/null; then
        curl https://cli-assets.heroku.com/install.sh | sh
    # Для macOS
    elif command -v brew &> /dev/null; then
        brew tap heroku/brew && brew install heroku
    else
        echo "❌ Не вдалося встановити Heroku CLI автоматично"
        echo "📖 Встановіть вручну: https://devcenter.heroku.com/articles/heroku-cli"
        exit 1
    fi
fi

# Логін в Heroku
echo "🔐 Логін в Heroku..."
heroku login

# Створення нового додатку (якщо потрібно)
read -p "🤔 Створити новий додаток? (y/n): " create_new
if [[ $create_new == "y" || $create_new == "Y" ]]; then
    echo "📱 Створення нового додатку..."
    heroku create
else
    read -p "📝 Введіть ім'я існуючого додатку: " app_name
    heroku git:remote -a $app_name
fi

# Отримання імені додатку
APP_NAME=$(heroku apps:info --json | grep -o '"name":"[^"]*"' | cut -d'"' -f4)
echo "📱 Додаток: $APP_NAME"

# Встановлення buildpack для PHP
echo "🔧 Встановлення PHP buildpack..."
heroku buildpacks:set heroku/php

# Налаштування змінних середовища
echo "⚙️ Налаштування змінних середовища..."
heroku config:set PHP_VERSION=8.1
heroku config:set APP_ENV=production

# Додавання до Git (якщо потрібно)
if ! git remote | grep -q heroku; then
    echo "📦 Додавання Heroku remote..."
    heroku git:remote -a $APP_NAME
fi

# Коміт змін (якщо потрібно)
if [[ -n $(git status --porcelain) ]]; then
    echo "💾 Коміт змін..."
    git add .
    git commit -m "Deploy to Heroku"
fi

# Розгортання
echo "🚀 Розгортання на Heroku..."
git push heroku main

# Запуск додатку
echo "▶️ Запуск додатку..."
heroku ps:scale web=1

# Перевірка статусу
echo "📊 Статус додатку:"
heroku ps

# Отримання URL
echo "🌐 URL додатку:"
heroku info -s | grep web_url

echo "✅ Розгортання завершено!"
echo "🔗 WebSocket URL: wss://$APP_NAME.herokuapp.com"
echo "📊 Логи: heroku logs --tail" 