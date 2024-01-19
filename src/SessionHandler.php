<?php

/**
 * DB 세션 핸들러 클래스
 **/

namespace zardsama\session;

abstract class SessionHandler
{

    private string $session_name;
    private string $cookie_domain;

    /**
     * 세션 핸들러 등록
     * @param string|null $session_name
     * @return void
     */
	protected function init(string | null $session_name) : void
    {
        if (ini_get('session.auto_start') == 1) {
            session_write_close();
        }

        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );

        preg_match('/(?:http[s]*\:\/\/)*(.*?)\.(?=[^\/]*\..{2,5})/i', $_SERVER['HTTP_HOST'], $match);
        $this->cookie_domain = preg_replace('/^'.preg_quote($match[1]).'/', '', $_SERVER['HTTP_HOST']);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $this->cookie_domain,
            'secure' => ($_SERVER['HTTPS'] == 'on') ? true : false,
            'httponly' => false,
        ]);

        session_start();
        if ($session_name) {
            session_name($session_name);
        }
        $this->session_name = session_name();
	}

    /**
     * 세션 내용 분석
     * @param string $session_data
     * @return array
     */
    public function unserialize(string $session_data) : array
    {
         $return_data = [];
         $offset = 0;
         while ($offset < strlen($session_data)) {
             if (!strstr(substr($session_data, $offset), "|")) {
                 return [];
             }
             $pos = strpos($session_data, "|", $offset);
             $num = $pos - $offset;
             $var_name = substr($session_data, $offset, $num);
             $offset += $num + 1;
             $data = unserialize(substr($session_data, $offset));
             $return_data[$var_name] = $data;
             $offset += strlen(serialize($data));
         }
         return $return_data;
    }

    /**
     * 세션 열기
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    abstract public function open(string $path, string $name): bool;

    /**
     * 세션 닫기
     * @return bool
     */
    abstract public function close(): bool;

    /**
     * 세션 읽기
     * PHP7부터 값이 null 로 리턴되면 세션이 동작하지 않습니다.
     * 값이 없더라도 빈 스트링으로 전송해야 합니다.
     * @param string $id
     * @return string
     */
    abstract public function read(string $id) : string|false;

    /**
     * 세션 쓰기
     * @param string $id
     * @param string $data
     * @return bool
     */
    abstract public function write(string $id, string $data) : bool;

    /**
     * 세션 삭제
     * @param string $id
     * @return bool
     */
    abstract public function destroy(string $id) : bool;

    /**
     * 만료 된 세션 삭제
     * @param int $maxlifetime
     * @return int|false
     */
    abstract public function gc(int $max_lifetime) : int|false;

    /**
     * 지정된 세션 아이디가 존재하는지 리턴
     * @param string $id
     * @return bool
     */
    abstract public function exists(string $id) : bool;

    /**
     * 세션의 내용을 배열로 리턴
     * @param string $id
     * @return array
     */
    abstract public function parse(string $id) : array;

}