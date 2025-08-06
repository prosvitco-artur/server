<?php

namespace Server;

use Ratchet\ConnectionInterface;

class MessageHandler
{
    protected $roomManager;
    protected $userManager;
    protected $clients;

    public function __construct($roomManager, $userManager, $clients)
    {
        $this->roomManager = $roomManager;
        $this->userManager = $userManager;
        $this->clients = $clients;
    }

    public function handleMessage(ConnectionInterface $from, $data)
    {
        // Додаємо метадані
        $data['timestamp'] = date('Y-m-d H:i:s');
        $data['from'] = $from->resourceId;

        // Обробляємо різні типи повідомлень
        switch ($data['type']) {
            case 'message':
                // Відправляємо повідомлення на сервер
                return $this->handleChatMessage($from, $data);
            case 'join_room':
                // Приєднуємося до кімнати
                return $this->handleJoinRoom($from, $data);
            case 'leave_room':
                // Покидаємо кімнату
                return $this->handleLeaveRoom($from, $data);
            case 'private_message':
                // Відправляємо приватне повідомлення
                return $this->handlePrivateMessage($from, $data);
            case 'set_username':
                return $this->handleSetUsername($from, $data);
            case 'get_users':
                return $this->handleGetUsers($from, $data);
            case 'ping':
                return ['type' => 'pong', 'timestamp' => date('Y-m-d H:i:s')];
            default:
                return $this->handleChatMessage($from, $data);
        }
    }

    protected function handleChatMessage(ConnectionInterface $from, $data)
    {
        // Отримуємо поточну кімнату клієнта
        $currentRoom = $this->roomManager->getClientRoom($from);
        
        // Додаємо ім'я користувача до повідомлення
        $username = $this->userManager->getUsername($from);
        $data['username'] = $username;
        
        // Відправляємо повідомлення тільки клієнтам в тій самій кімнаті
        $roomClients = $this->roomManager->getRoomClients($currentRoom);
        $recipients = [];
        
        foreach ($roomClients as $client) {
            // if ($from !== $client) {
                $recipients[] = $client;
            // }
        }
        
        return [
            'type' => 'message',
            'data' => $data,
            'recipients' => $recipients,
            'delivery_status' => [
                'type' => 'delivery_status',
                'messageId' => $data['messageId'] ?? uniqid(),
                'status' => 'delivered',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }

    protected function handleJoinRoom(ConnectionInterface $client, $data)
    {
        $roomName = $data['room'] ?? 'general';
        $this->roomManager->joinRoom($client, $roomName);
        
        $username = $this->userManager->getUsername($client);
        
        // Повідомлення для клієнта
        $clientMessage = [
            'type' => 'room_joined',
            'room' => $roomName,
            'message' => "Ви приєдналися до кімнати: {$roomName}",
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Повідомлення для інших учасників кімнати
        $roomClients = $this->roomManager->getRoomClients($roomName);
        $notifications = [];
        
        foreach ($roomClients as $roomClient) {
            if ($roomClient !== $client) {
                $notifications[] = [
                    'client' => $roomClient,
                    'data' => [
                        'type' => 'user_joined_room',
                        'room' => $roomName,
                        'user' => $client->resourceId,
                        'username' => $username,
                        'message' => "{$username} приєднався до кімнати",
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ];
            }
        }
        
        return [
            'type' => 'room_joined',
            'client_message' => $clientMessage,
            'notifications' => $notifications
        ];
    }

    protected function handleLeaveRoom(ConnectionInterface $client, $data)
    {
        $roomName = $data['room'] ?? 'general';
        $success = $this->roomManager->leaveRoom($client, $roomName);
        
        if ($success) {
            return [
                'type' => 'room_left',
                'client_message' => [
                    'type' => 'room_left',
                    'room' => $roomName,
                    'message' => "Ви покинули кімнату: {$roomName}",
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        return null;
    }

    protected function handlePrivateMessage(ConnectionInterface $from, $data)
    {
        $targetUsername = $data['to'] ?? null;
        $recipient = null;
        
        if ($targetUsername) {
            foreach ($this->clients as $client) {
                $clientUsername = $this->userManager->getUsername($client);
                if ($clientUsername === $targetUsername) {
                    $recipient = $client;
                    break;
                }
            }
        }
        
        if ($recipient) {
            $fromUsername = $this->userManager->getUsername($from);
            return [
                'type' => 'private_message',
                'recipient' => $recipient,
                'data' => [
                    'type' => 'private_message',
                    'from' => $fromUsername,
                    'content' => $data['content'],
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                'delivery_status' => [
                    'type' => 'delivery_status',
                    'messageId' => $data['messageId'] ?? uniqid(),
                    'status' => 'delivered',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        return null;
    }

    protected function handleSetUsername(ConnectionInterface $client, $data)
    {
        $username = $data['username'] ?? '';
        
        if ($this->userManager->setUsername($client, $username)) {
            return [
                'type' => 'username_set',
                'client_message' => [
                    'type' => 'username_set',
                    'username' => $username,
                    'message' => "Ім'я встановлено: {$username}",
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        return null;
    }

    protected function handleGetUsers(ConnectionInterface $client, $data)
    {
        $users = $this->userManager->getAllUsers($this->clients);
        
        // Додаємо інформацію про кімнати для кожного користувача
        foreach ($users as &$user) {
            // Знаходимо клієнта за іменем
            $userClient = null;
            foreach ($this->clients as $c) {
                $clientUsername = $this->userManager->getUsername($c);
                if ($clientUsername === $user['username']) {
                    $userClient = $c;
                    break;
                }
            }
            $user['room'] = $userClient ? $this->roomManager->getClientRoom($userClient) : 'general';
        }
        
        return [
            'type' => 'users_list',
            'client_message' => [
                'type' => 'users_list',
                'users' => $users,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
} 