<?php

namespace zardsama\session;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/*
* MySQL DB 세션 핸들러 클래스
**/

class MySQLSession extends SessionHandler
{
    private Connection $qb;
    private string $table;

    /**
     * constructor
     * @param Connection $qb
     * @param string $table 테이블명
     * @param string $session_name 세션명
     * @throws QueryException;
     */
    public function __construct(Connection $qb, string $table, string $session_name = 'PHPSESSID')
    {
        $this->qb = &$qb;
        $this->table = $table;

        if (!count($this->qb->select("show tables like '$table'"))) {
            if (!$this->createTable()) {
                exit('Session table create error.');
            }
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
     * @throws QueryException
     */
    public function read(string $id) : string|false
    {
        $data = $this->qb->table($this->table)
            ->where('session_id', $id)
            ->first('data')->data ?? null;
        return $data === null ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        }
        if (empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = 'localhost';
        }

        try {
            $this->qb->table($this->table)
                ->updateOrInsert(
                    [
                        'session_id' => $id,
                    ], [
                        'data' => $data,
                        'remote_addr' => $_SERVER['REMOTE_ADDR'],
                        'page' => $_SERVER['REQUEST_URI'],
                        'reg_date' => $this->qb->raw('now()'),
                        'access_time' => $this->qb->raw('now()')
                    ]
                );
        } catch (QueryException $e) {
            echo $e->getMessage();
            echo PHP_EOL;
            echo $e->getRawSql();
            return true;
        }
        return true;
    }

    /**
     * @throws QueryException
     */
    public function destroy(string $id) : bool
    {
        $this->qb->table($this->table)
            ->where('session_id', $id)
            ->delete();
        return true;
    }

    /**
     * @throws QueryException
     */
    public function gc(int $max_lifetime) : int|false
    {
        return $this->qb->table($this->table)
            ->where('access_time', '<', $this->qb->raw('date_sub(NOW(), INTERVAL ' . $max_lifetime . ' SECOND)'))
            ->delete();
    }

    /**
     * @throws QueryException
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
     * @throws QueryException
     */
    public function parse($id): array
    {
        $data = $this->qb->table($this->table)
            ->where('session_id', $id)
            ->first('data')->data;
        return $this->unserialize($data);
    }

    private function createTable(): bool
    {
        try {
            $this->qb->select("
                CREATE TABLE `$this->table` (
                    `session_id` VARCHAR(64) NOT NULL COLLATE 'utf8_general_ci',
                    `data` TEXT NOT NULL COLLATE 'utf8_general_ci',
                    `remote_addr` VARCHAR(15) NOT NULL COLLATE 'utf8_general_ci',
                    `page` VARCHAR(100) NOT NULL COLLATE 'utf8_general_ci',
                    `reg_date` DATETIME NOT NULL,
                    `access_time` DATETIME NOT NULL,
                    PRIMARY KEY (`session_id`) USING BTREE,
                    INDEX `access_time` (`access_time`) USING BTREE
                )
                COMMENT='session table'
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;
            ");
        } catch (QueryException) {
            return false;
        }
        return true;
    }

}