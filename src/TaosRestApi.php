<?php
namespace TDEngine;

class TaosRestApi{
    const FETCH_BOTH = 2;
    const FETCH_ASSOC = 1;
    const FETCH_NUM = 0;

    public $host = '';
    public $tz; //时区 如 America/New_York
    public $token;
    public $dbname;

    public $errno = 0;
    public $error = '';
    public $columns = [];
    public $columnNames = [];
    public $data = [];
    private $rowCount = 0; //影响的条数
    private $idx = 0; //循环数据下标

    // 连接数据库
    public function __construct($host, $user, $pwd, $dbname='', $timezone='') {
        $this->host = (strpos($host, '://') === false ? 'http://' : '') . $host;
        $this->tz = $timezone; //Asia/Chongqing
        $this->token = base64_encode($user . ':' . $pwd);
        $this->dbname = $dbname;
    }

    public function useDb($dbname)
    {
        $this->dbname = $dbname;
    }

    /**
     * HTTP 请求格式
     *   http://<fqdn>:<port>/rest/sql/[db_name][?tz=timezone[&req_id=req_id]]
     * fqdn: 集群中的任一台主机 FQDN 或 IP 地址。
     * port: 配置文件中 httpPort 配置项，缺省为 6041。
     * db_name: 可选参数，指定本次所执行的 SQL 语句的默认数据库库名。
     * tz: 可选参数，指定返回时间的时区，遵照 IANA Time Zone 规则，如 America/New_York。
     * req_id: 可选参数，指定请求 id，可以用于 tracing。
     *
     * HTTP 请求的 Header 里需带有身份认证信息
     * 自定义身份认证信息如所示  Authorization: Taosd <TOKEN>
     * Basic 身份认证信息如所示 Authorization: Basic <TOKEN>
     * @param $sql
     * @param string $req_id
     * @return array|bool|false|string
     */
    public function request($sql, $req_id=''){
        $this->columns = [];
        $this->columnNames = [];
        $this->data = []; //new SplFixedArray(5);
        $this->rowCount = 0;
        $this->idx = 0;
        $this->errno = 0;
        $this->error = '';

        if ($this->dbname === '') {
            throw new \Exception('Database not specified');
            $this->errno = 9750;
            $this->error = "Database not specified";
            return false;
        }
        $restApi = $this->host . "/rest/sql/" . $this->dbname;

        $params = '';
        if ($this->tz !== '') $params .= '&tz=' . $this->tz;
        if ($req_id) $params .= '&req_id=' . $req_id;
        if ($params) $restApi .= '?_=1' . $params;

        $res = $this->curl($restApi, 'POST', $sql, 60, "Authorization: Basic " . $this->token);
        if ($res === false) return false;
        \myphp\Log::write($res, 'log');
        $ret = json_decode($res, true);
        if ($ret['code'] != 0) {
            $this->errno = $ret['code'];
            $this->error = $ret['desc'];
            return false;
        }
        $columns = $columnNames = [];
        foreach ($ret['column_meta'] as $row)
        {
            $columns[$row[0]] = ['type_name'=>$row[1],'length'=>$row[2]];
            $columnNames[] = $row[0];
        }
        $this->columns = $columns;
        $this->columnNames = $columnNames;
        $this->data = $ret['data'];

        $this->rowCount = $ret['rows'] ?? 0;
        return true;
    }

    //通过curl 自定义发送请求
    public function curl($url, $type='GET', $data=null, $timeout=30, $header=[], $opt=[])
    {
        /*
        GET（SELECT）：从服务器取出资源（一项或多项）。
        POST（CREATE）：在服务器新建一个资源。
        PUT（UPDATE）：在服务器更新资源（客户端提供改变后的完整资源）。
        PATCH（UPDATE）：在服务器更新资源（客户端提供改变的属性）。
        DELETE（DELETE）：从服务器删除资源。
        HEAD：获取资源的元数据。
        */
        $connect_timeout = isset($opt['connect_timeout']) ? $opt['connect_timeout'] : $timeout;
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connect_timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout * 1000,
            CURLOPT_CONNECTTIMEOUT_MS => $connect_timeout * 1000,
            //CURLOPT_ENCODING => ''
        ];

        //$timeoutRequiresNoSignal = false; $timeoutRequiresNoSignal |= $timeout < 1;
        if ($timeout < 1 && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $options[CURLOPT_NOSIGNAL] = true;
        }

        if(substr($url,0,5)=='https'){ //ssl
            $options[CURLOPT_SSL_VERIFYHOST] = 0; //检查服务器SSL证书 正式环境中使用 2
            $options[CURLOPT_SSL_VERIFYPEER] = false; //取消验证证书

            if(isset($opt['cert']) && isset($opt['key'])){
                $opt['type'] = isset($opt['type']) ? $opt['type'] : 'PEM';
                $options[CURLOPT_SSLCERTTYPE] = $opt['type'];
                $options[CURLOPT_SSLKEYTYPE] = $opt['type'];
                $options[CURLOPT_SSLCERT] = $opt['cert'];
                $options[CURLOPT_SSLKEY] = $opt['key'];
            }
            if(isset($opt['cainfo']) || isset($opt['capath'])){
                isset($opt['cainfo']) && $options[CURLOPT_CAINFO] = $opt['cainfo'];
                isset($opt['capath']) && $options[CURLOPT_CAPATH] = $opt['capath'];
                $options[CURLOPT_SSL_VERIFYHOST] = 2;
                $options[CURLOPT_SSL_VERIFYPEER] = true;
            }
        }

        $type = strtoupper($type);
        $options[CURLOPT_CUSTOMREQUEST] = $type;
        switch ($type) {
            case 'GET':
                if ( $data ) {
                    $data = is_array($data) ? http_build_query($data) : $data;
                    $url = strpos($url, '?') === false ? ($url . '?' . $data) : ($url . '&' . $data);
                    $options[CURLOPT_URL] = $url;
                }
                break;
            case 'POST':
                //https 使用数组的在某些未知情况下数据长度超过一定长度会报SSL read: error:00000000:lib(0):func(0):reason(0), errno 10054
                if(is_array($data) && (!isset($opt['post_encode']) || $opt['post_encode'])){
                    //针对 CURLFile 上传文件不要编码
                    $data = http_build_query($data);
                    /*$toBuild = true;
                    if(class_exists('CURLFile')){ //针对上传文件处理
                        foreach ($data as $v){
                            if($v instanceof CURLFile){
                                $toBuild = false;
                                break;
                            }
                        }
                    }
                    if($toBuild) $data = http_build_query($data);*/
                }
                $options[CURLOPT_POSTFIELDS] = $data;
                break;
        }

        $options[CURLOPT_HTTPHEADER] = is_string($header) ? explode("\r\n", $header) : $header;

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        //批量配置
        if (isset($opt['opts']) && is_array($opt['opts'])) {
            curl_setopt_array($ch, $opt['opts']);
        }

        $result = false;
        if (isset($opt['res'])) {
            curl_setopt($ch, CURLOPT_HEADER, true); // 是否需要响应 header
            $output = curl_exec($ch);
            if ($output !== false) {
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);    // 获得响应结果里的：头大小
                $result = [
                    //'req_url'        => $url,
                    //'req_body'       => $data,
                    //'req_header'     => $header,
                    'res_http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), // 获取响应状态码
                    'res_body' => substr($output, $header_size),
                    'res_header' => substr($output, 0, $header_size), //根据头大小去获取头信息内容
                    'res_errno' => curl_errno($ch),
                    'res_error' => curl_error($ch),
                ];
            }
        } else {
            $result = curl_exec($ch);
        }
        $this->errno = curl_errno($ch);
        $this->error = '';
        if ($this->errno) {
            $this->error = 'err:' . curl_error($ch) . "\nurl:" . $url . ($data !== null ? "\ndata:" . (is_scalar($data) ? urldecode($data) : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '');
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 客户端版本
     * @return bool|mixed
     */
    public function clientVer()
    {
        if (!$this->exec('SELECT CLIENT_VERSION()')) return false;
        return $this->fetch(self::FETCH_NUM)[0];
    }

    /**
     * 服务端版本
     * @return bool|mixed
     */
    public function serverVer()
    {
        if (!$this->exec('SELECT SERVER_VERSION()')) return false;
        return $this->fetch(self::FETCH_NUM)[0];
    }

    /**
     * 显示接入集群的应用（客户端）信息。
     * @return array|bool
     */
    public function apps()
    {
        return $this->query("SHOW APPS");
    }

    /**
     * 显示当前集群的信息
     * @return array|bool
     */
    public function cluster()
    {
        return $this->query("SHOW CLUSTER");
    }

    /**
     * 显示当前系统中存在的连接的信息
     * @return array|bool
     */
    public function connections()
    {
        return $this->query("SHOW CONNECTIONS");
    }

    /**
     * 显示当前数据库下所有消费者的信息。
     * @return array|bool
     */
    public function consumers()
    {
        return $this->query("SHOW CONSUMERS");
    }

    /**
     * 删除过期数据
     * 删除过期数据，并根据多级存储的配置归整数据。
     * @param string $dbname
     * @return array|bool|false|string
     */
    public function trimDb($dbname = '')
    {
        return $this->exec("TRIM DATABASE " . ($dbname !== '' ? $dbname : $this->dbname));
    }

    /**
     * 落盘内存数据
     * 在关闭节点之前，执行这条命令可以避免重启后的数据回放，加速启动过程。
     * @param string $dbname
     * @return array|bool|false|string
     */
    public function flushDb($dbname = '')
    {
        return $this->exec("FLUSH DATABASE " . ($dbname !== '' ? $dbname : $this->dbname));
    }
    /**
     * 创建数据库
     * @param $dbname
     * @param bool $ifNotExists
     * @param string $options
     * @return array|bool|false|string
     */
    public function createDb($dbname, $ifNotExists=false, $options = '')
    {
        $sql = "CREATE DATABASE " . ($ifNotExists ? "IF NOT EXISTS " : "") . $dbname . " " . $options;
        return $this->exec($sql);
    }

    //TBNAME 可以视为超级表中一个特殊的标签，代表子表的表名。
    public function subTables()
    {
        return $this->query("SELECT TBNAME FROM " . $this->dbname);
    }

    /**
     * 删除数据库
     * @param $dbname
     * @param bool $ifExists
     * @return array|bool|false|string
     */
    public function dropDb($dbname, $ifExists=false)
    {
        $sql = "DROP DATABASE " . ($ifExists ? 'IF EXISTS ' : '') . $dbname;
        return $this->exec($sql);
    }

    /**
     * 删除数据表
     * @param $tb_name
     * @param bool $ifExists
     * @return array|bool|false|string
     */
    public function dropTb($tb_name, $ifExists=false)
    {
        $sql = "DROP TABLE " . ($ifExists ? 'IF EXISTS ' : '') . $tb_name;
        return $this->exec($sql);
    }

    /** SQL安全过滤
     * @param $str
     * @return string
     */
    public function quote($str) {
        return "'" . strtr($str, ["\\"=>"\\\\", "'"=>"\'"]) . "'";
        //return "'" . str_replace(["\\", "'"], ["\\\\", "\'"], $str) . "'";
    }

    /**
     * 执行sql
     * @param $sql
     * @return bool|int
     */
	public function exec($sql) {
        if (!$this->request($sql)) return false;
        $rowCount = $this->rowCount();

        if($rowCount==0) \myphp\Log::write($sql, 'log');


        return $rowCount ?: true;
	}

    /**
     * 执行sql返回数据结果
     * @param $sql
     * @param int $mode
     * @return array|bool
     */
    public function query($sql, $mode = self::FETCH_ASSOC)
    {
        if (!$this->request($sql)) return false;

        return $this->fetchAll($mode);
    }

    /**
     * 对exec执行的数据输出
     * @param int $mode
     * @return array|bool
     */
	public function fetchAll($mode = self::FETCH_ASSOC){
        if (!$this->data) return false;

        if ($mode == self::FETCH_ASSOC) {
            foreach ($this->data as $k => $row) {
                $this->data[$k] = array_combine($this->columnNames, $row);
            }
        } elseif ($mode == self::FETCH_BOTH) {
            foreach ($this->data as $k => $row) {
                $this->data[$k] = array_merge($row, array_combine($this->columnNames, $row));
            }
        }
        return $this->data;
    }

    /**
     * 对exec执行数据获取一行数据
     * @param int $mode
     * @return array|bool
     */
	public function fetch($mode = self::FETCH_ASSOC) {
        if (!$this->data) return false;
        if (!isset($this->data[$this->idx])) return false;

        $row = $this->data[$this->idx];

        $this->idx++;
        if ($mode == self::FETCH_ASSOC) {
            return array_combine($this->columnNames, $row);
        } elseif ($mode == self::FETCH_BOTH) {
            return array_merge($row, array_combine($this->columnNames, $row));
        }
        return $row;
	}

    /**
     * 取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
     * @return mixed
     */
	public function lastInsertId($sequenceName=null) {
		return 0;
	}

	public function rowCount(){
        if (isset($this->columns['affected_rows']) && count($this->columnNames) == 1) {
            $this->rowCount = $this->data[0][0];
        }
        return $this->rowCount;
    }
}