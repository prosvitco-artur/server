<?php

namespace Server;

use Ratchet\ConnectionInterface;

class RoomManager
{
    protected $rooms = [];
    protected $clientRooms = [];

    public function __construct()
    {
        $this->rooms = [];
        $this->clientRooms = [];
    }

    public function joinRoom(ConnectionInterface $client, $roomName = 'general')
    {
        // Видаляємо клієнта з попередньої кімнати
        $previousRoom = $this->clientRooms[$client->resourceId] ?? null;
        if ($previousRoom && isset($this->rooms[$previousRoom])) {
            $this->rooms[$previousRoom]->detach($client);
        }
        
        // Додаємо клієнта до нової кімнати
        if (!isset($this->rooms[$roomName])) {
            $this->rooms[$roomName] = new \SplObjectStorage;
        }
        
        $this->rooms[$roomName]->attach($client);
        $this->clientRooms[$client->resourceId] = $roomName;
        return $roomName;
    }

    public function leaveRoom(ConnectionInterface $client, $roomName = 'general')
    {
        if (isset($this->rooms[$roomName])) {
            $this->rooms[$roomName]->detach($client);
            
            // Видаляємо з відстеження кімнат
            if ($this->clientRooms[$client->resourceId] === $roomName) {
                unset($this->clientRooms[$client->resourceId]);
            }
            
            return true;
        }
        
        return false;
    }

    public function getClientRoom(ConnectionInterface $client)
    {
        return $this->clientRooms[$client->resourceId] ?? 'general';
    }

    public function getRoomClients($roomName)
    {
        $roomClients = $this->rooms[$roomName] ?? new \SplObjectStorage;
        return $roomClients;
    }

    public function removeClientFromAllRooms(ConnectionInterface $client)
    {
        foreach ($this->rooms as $roomName => $roomClients) {
            if ($roomClients->contains($client)) {
                $roomClients->detach($client);
            }
        }
        
        // Видаляємо з відстеження кімнат
        unset($this->clientRooms[$client->resourceId]);
    }

    public function getRoomsInfo()
    {
        $info = [];
        foreach ($this->rooms as $roomName => $roomClients) {
            $info[$roomName] = count($roomClients);
        }
        return $info;
    }

    public function getRooms()
    {
        return $this->rooms;
    }
} 