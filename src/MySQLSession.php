<?php

namespace zardsama\session;

use zardsama\QBExtend\QBHandlerExtend;
use Pecee\Pixie\Exception;

/*
* MySQL DB 세션 핸들러 클래스
**/

class MySQLSession extends SessionHandler
{
    private QBHandlerExtend $qb;
    private string $table;

    /**
     * constructor
     * @param QBHandlerExtend $qb
     * @param string $table 테이블명
     * @param string $session_name 세션명
     * @throws Exception
     */
    public function __construct(QBHandlerExtend $qb, string $table, string $session_name = 'PHPSESSID')
    {
        $this->qb = &$qb;
        $this->table = $table;

        if ($this->qb->query("show tables like `$table`")->count() == 0) {
            $this->createTable();
        }

        $this->init($session_name);
    }


    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close() : bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function read($id) : string
    {
        return $this->qb->table($this->table)
            ->where('session_id', $id)
            ->single('data');
    }

    /**
     * @throws Exception
     */
    public function write($id, $data) : bool
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        }
        if (empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = 'localhost';
        }

        $count = $this->qb->table($this->table)
            ->where('session_id', $id)
            ->count();
        if ($count > 0) {
            $this->qb->table($this->table)
                ->where('session_id', $id)
                ->update([
                    'data' => $data,
                    'page' => $_SERVER['REQUEST_URI'],
                    'access_time' => $this->qb->raw('now()')
                ]);
        } else {
            $this->qb->table($this->table)
                ->insert([
                    'session_id' => $id,
                    'data' => $data,
                    'remote_addr' => $_SERVER['REMOTE_ADDR'],
                    'page' => $_SERVER['REQUEST_URI'],
                    'reg_date' => $this->qb->raw('now()'),
                    'access_time' => $this->qb->raw('now()')
                ]);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function destroy(string $id) : bool
    {
        $this->qb->table($this->table)
            ->where('session_id', $id)
            ->delete();
        return true;
    }

    /**
     * @throws Exception
     */
    public function gc(int $max_lifetime) : int|false
    {
        return $this->qb->table($this->table)
            ->where('access_time', '<', $this->qb->raw('date_sub(NOW(), INTERVAL ' . $max_lifetime . ' SECOND)'))
            ->delete()
            ->rowCount();
    }

    /**
     * @throws Exception
     */
    public function exists($id): bool
    {
        return (
            $this->qb->table($this->table)
                ->where('session_id', $id)
                ->count() > 0
        );
    }

    /**
     * @throws Exception
     */
    public function parse($id): array
    {
        $data = $this->qb->table($this->table)
            ->where('session_id', $id)
            ->single('data');
        return $this->unserialize($data);
    }

    /**
     * @throws Exception
     */
    private function createTable() : void
    {
        $this->qb->query("
            CREATE TABLE `$this->table` (
                `session_id` VARCHAR(64) NOT NULL COLLATE 'utf8_general_ci',
                `data` TEXT NOT NULL COLLATE 'utf8_general_ci',
                `remote_addr` VARCHAR(15) NOT NULL COLLATE 'utf8_general_ci',
                `page` VARCHAR(100) NOT NULL COLLATE 'utf8_general_ci',
                `reg_date` DATETIME NOT NULL,
                `access_time` DATETIME NOT NULL,
                PRIMARY KEY (`session_id`) USING BTREE,
                INDEX `accesstime` (`accesstime`) USING BTREE
            )
            COMMENT='session table'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;
        ");
    }

}