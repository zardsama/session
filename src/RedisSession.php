<?php

namespace zardsama\session;

/*
* Redis DB 세션 핸들러 클래스
**/

use Redis;

class RedisSession extends SessionHandler
{
    private Redis $db;

    public function __construct($config)
    {
        $config = explode(':', $config);
        $this->db = new Redis();
        $this->db->connect($config[0].':'.$config[1], $config[2]);

        $this->init();
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->db->get($id);
        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        return $this->db->setex($id, (int) ini_get('session.gc_maxlifetime'), $data);
    }

    public function destroy(string $id): bool
    {
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 1;
    }

    public function exists($id): bool
    {
        return $this->db->exists($id);
    }

    public function parse($id): array
    {
        $data = $this->db->get($id);
        return $this->unserialize($data);
    }
}