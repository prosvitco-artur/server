<?php

namespace Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketHandler implements MessageComponentInterface
{
    protected $clients;
    protected $roomManager;
    protected $userManager;
    protected $messageHandler;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->roomManager = new RoomManager();
        $this->userManager = new UserManager();
        echo "WebSocket сервер запущено!!!!\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Підключення нового користувача
        // Зберігаємо нове з'єднання
        $this->clients->attach($conn);
        echo "Нове з'єднання! ({$conn->resourceId})\n";
        
        // Ініціалізуємо message handler
        $this->messageHandler = new MessageHandler($this->roomManager, $this->userManager, $this->clients);
        
        // Автоматично додаємо клієнта до кімнати general
        $this->roomManager->joinRoom($conn, 'general');
        echo "Клієнт {$conn->resourceId} доданий до кімнати general\n";
        
        // Відправляємо привітання
        $this->sendToClient($conn, [
            'type' => 'welcome',
            'message' => 'Ласкаво просимо до WebSocket сервера!',
            'timestamp' => date('Y-m-d H:i:s'),
            'clientId' => $conn->resourceId
        ]);

        // Відправляємо інформацію про кількість підключених клієнтів
        $this->broadcastSystemMessage("Новий користувач підключився. Всього клієнтів: " . count($this->clients));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo sprintf('Повідомлення від %d: %s' . "\n", $from->resourceId, $msg);
        $data = json_decode($msg, true);
        // echo $data;
        
        if (!$data) {
            $data = ['type' => 'message', 'content' => $msg];
        }

        $result = $this->messageHandler->handleMessage($from, $data);
        
        if ($result) {
            $this->processMessageResult($from, $result);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "З'єднання {$conn->resourceId} закрито\n";
        
        $this->roomManager->removeClientFromAllRooms($conn);
        
        $this->userManager->removeUser($conn);
        
        $username = $this->userManager->getUsername($conn);
        if ($username && $username !== "Користувач {$conn->resourceId}") {
            $this->broadcastSystemMessage("{$username} відключився. Всього клієнтів: " . count($this->clients));
        } else {
            $this->broadcastSystemMessage("Користувач відключився. Всього клієнтів: " . count($this->clients));
        }
        
        $this->broadcastUsersList();
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Помилка: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function processMessageResult(ConnectionInterface $from, $result)
    {
        switch ($result['type']) {
            case 'message':
                foreach ($result['recipients'] as $recipient) {
                    $this->sendToClient($recipient, $result['data']);
                }
                $this->sendToClient($from, $result['delivery_status']);
                break;
                
            case 'room_joined':
                $this->sendToClient($from, $result['client_message']);
                foreach ($result['notifications'] as $notification) {
                    $this->sendToClient($notification['client'], $notification['data']);
                }
                $this->broadcastUsersList();
                break;
                
            case 'room_left':
                $this->sendToClient($from, $result['client_message']);
                break;
                
            case 'private_message':
                $this->sendToClient($result['recipient'], $result['data']);
                $this->sendToClient($from, $result['delivery_status']);
                break;
                
            case 'username_set':
                $this->sendToClient($from, $result['client_message']);
                $this->broadcastUsersList();
                break;
                
            case 'users_list':
                $this->sendToClient($from, $result['client_message']);
                break;
                
            case 'pong':
                $this->sendToClient($from, $result);
                break;
        }
    }

    protected function sendToClient(ConnectionInterface $client, $data)
    {
        $client->send(json_encode($data));
    }

    protected function broadcastSystemMessage($message)
    {
        $data = [
            'type' => 'system',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        foreach ($this->clients as $client) {
            $this->sendToClient($client, $data);
        }
    }

    protected function broadcastUsersList()
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
        
        $data = [
            'type' => 'users_list',
            'users' => $users,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        foreach ($this->clients as $client) {
            $this->sendToClient($client, $data);
        }
    }

    public function getConnectedClientsCount()
    {
        return count($this->clients);
    }

    public function getRoomsInfo()
    {
        return $this->roomManager->getRoomsInfo();
    }
} 