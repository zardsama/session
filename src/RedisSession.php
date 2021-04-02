<?php

namespace zardsama\session;

/*
* Redis DB 세션 핸들러 클래스
**/

class RedisSession extends SessionHandler
{
    private $db;
    private $session_name;

    public function __construct($config)
    {
        $config = explode(':', $config);
        $this->db = new \Redis();
        $this->db->connect($config[0].':'.$config[1], $config[2]);

        $this->init();
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id)
    {
        return (string) $this->db->get($id);
    }

    public function write($id, $data)
    {
        return $this->db->setex($id, (int) ini_get('session.gc_maxlifetime'), $data);
    }

    public function destroy($id) {}

    public function gc($maxlifetime) {}

    public function exists($id) {
        return $this->db->exists($id);
    }

    public function parse($id) {
        $data = $this->db->get($id);
        return $this->unserialize($data);
    }
}

?>