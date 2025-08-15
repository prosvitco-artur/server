<?php

namespace Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketHandler implements MessageComponentInterface
{
    protected $clients;
    protected $userManager;
    protected $messageHandler;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userManager = new UserManager();
        echo "WebSocket сервер запущено!!!!\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Нове з'єднання! ({$conn->resourceId})\n";
        
        $this->messageHandler = new MessageHandler($this->userManager, $this->clients);
        
        $this->sendToClient($conn, [
            'type' => 'welcome',
            'message' => 'Ласкаво просимо до WebSocket сервера!',
            'timestamp' => date('Y-m-d H:i:s'),
            'clientId' => $conn->resourceId
        ]);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo sprintf('Повідомлення від %d: %s' . "\n", $from->resourceId, $msg);
        $data = json_decode($msg, true);
        
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
        
        $this->userManager->removeUser($conn);
        
        $username = $this->userManager->getUsername($conn);
        if ($username && $username !== "Користувач {$conn->resourceId}") {
            $this->broadcastSystemMessage("{$username} відключився. Всього клієнтів: " . count($this->clients));
        } else {
            $this->broadcastSystemMessage("Користувач відключився. Всього клієнтів: " . count($this->clients));
        }
        
        $this->broadcastUsersList();
    }

    public function onError(ConnectionInterface $conn, \Throwable $e)
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
                
            case 'username_set':
                $this->sendToClient($from, $result['client_message']);
                $this->broadcastUsersList();
                break;
                
            case 'users_list':
                $this->sendToClient($from, $result['client_message']);
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
} 