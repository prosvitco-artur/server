<?php

namespace Server;

use Ratchet\ConnectionInterface;

class UserManager
{
    protected $clientUsernames = [];

    public function __construct()
    {
        $this->clientUsernames = [];
    }

    public function setUsername(ConnectionInterface $client, $username)
    {
        if ($username) {
            $this->clientUsernames[$client->resourceId] = $username;
            return true;
        }
        return false;
    }

    public function getUsername(ConnectionInterface $client)
    {
        return $this->clientUsernames[$client->resourceId] ?? "Користувач {$client->resourceId}";
    }

    public function removeUser(ConnectionInterface $client)
    {
        unset($this->clientUsernames[$client->resourceId]);
    }

    public function getAllUsers($clients)
    {
        $users = [];
        foreach ($clients as $client) {
            $username = $this->getUsername($client);
            $users[] = [
                'id' => $username, // Використовуємо ім'я як ID
                'username' => $username
            ];
        }
        return $users;
    }

    public function getClientUsernames()
    {
        return $this->clientUsernames;
    }
} 