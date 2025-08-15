<?php

namespace Server;

use Ratchet\ConnectionInterface;

class MessageHandler
{
    protected $userManager;
    protected $clients;

    public function __construct($userManager, $clients)
    {
        $this->userManager = $userManager;
        $this->clients = $clients;
    }

    public function handleMessage(ConnectionInterface $from, $data)
    {
        $data['timestamp'] = date('Y-m-d H:i:s');
        $data['from'] = $from->resourceId;

        switch ($data['type']) {
            case 'message':
                return $this->handleChatMessage($from, $data);
            case 'set_username':
                return $this->handleSetUsername($from, $data);
            case 'get_users':
                return $this->handleGetUsers($from, $data);
            default:
                return $this->handleChatMessage($from, $data);
        }
    }

    protected function handleChatMessage(ConnectionInterface $from, $data)
    {
        $username = $this->userManager->getUsername($from);
        $data['username'] = $username;
        
        $recipients = [];
        
        foreach ($this->clients as $client) {
            $recipients[] = $client;
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