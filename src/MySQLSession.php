<?php

namespace zardsama\session;

use zardsama\pdo\PDODatabase;

/*
* MySQL DB 세션 핸들러 클래스
**/

class MySQLSession extends SessionHandler
{
    const TABLE = 'session';
    private $db;

    public function __construct(&$db)
    {
        $this->db = $db;
        if ($this->db->rowCount('SHOW TABLES LIKE '.SELF::TABLE) == 0) {
            $this->createTable();
        }
        $this->init();
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id, $field = 'data')
    {
        $session = $this->db->row("select $field from ".SELF::TABLE." where session_id='$id'");
        if ($session == false) $session = '';

        return $session;
    }

    public function write($id, $data)
    {
        $serialized = $this->unserialize($data);
        if (empty($_SERVER['REMOTE_ADDR']) == true) {
            $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        }
        if (empty($_SERVER['REQUEST_URI']) == true) {
            $_SERVER['REQUEST_URI'] = 'localhost';
        }

        if ($this->db->row("select count(*) from ".SELF::TABLE." where session_id='$id'") > 0)
        {
            $result = $this->db->query("
                update ".SELF::TABLE." set
                    accesstime=now(), data=?, page=?
                    where session_id='$id'
            ", array(
                $data, $_SERVER['REQUEST_URI']
            ));
        } else {
            $result = $this->db->query("
                insert into ".SELF::TABLE." (session_id, data, remote_addr, page, regdate, accesstime)
                values ('$id', ?, ?, ?, now(), now())"
            , array(
                $data, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']
            ));
        }
        return ($result != null);
    }

    public function destroy($id)
    {
        $result = @$this->db->query("delete from ".SELF::TABLE." where session_id='$id'");
        return true;
    }

    public function gc($maxlifetime)
    {
        $expire_time = time()-$maxlifetime;
        $result = @$this->db->query("delete from ".SELF::TABLE." where accesstime < '$expire_time'");
        return true;
    }

    public function exists($id) {
        $r = $this->db->row("select count(*) from ".SELF::TABLE." where session_id=:id", array(
            ':id' => $id
        ));
        return ($r > 0);
    }

    public function parse($id) {
        $data = $this->db->row("select data from ".SELF::TABLE." where session_id=:id", array(
            ':id' => $id
        ));
        return $this->unserialize($data);
    }

    private function createTable() {
        $this->db->query("
        CREATE TABLE `".SELF::TABLE."` (
            `session_id` VARCHAR(64) NOT NULL COLLATE 'utf8_general_ci',
            `data` TEXT NOT NULL COLLATE 'utf8_general_ci',
            `remote_addr` VARCHAR(15) NOT NULL COLLATE 'utf8_general_ci',
            `page` VARCHAR(100) NOT NULL COLLATE 'utf8_general_ci',
            `regdate` DATETIME NOT NULL,
            `accesstime` DATETIME NOT NULL,
            PRIMARY KEY (`session_id`) USING BTREE,
            INDEX `accesstime` (`accesstime`) USING BTREE
        )
        COMMENT='세션'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;
        ");
    }

}

?>