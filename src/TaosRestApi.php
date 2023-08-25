<?php
namespace TDEngine;

class TaosRestApi{
    public $host = '';
    public $tz; //时区 如 America/New_York
    public $token;
    public $dbname;

    public $errno = 0;
    public $error = '';

    const FETCH_BOTH = 2;
    const FETCH_ASSOC = 1;
    const FETCH_NUM = 0;

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
        $this->errno = 0;
        $this->error = '';

        $restApi = $this->host . "/rest/sql";
        if ($this->dbname !== '') $restApi .= '/' . $this->dbname;

        $params = '';
        if ($this->tz !== '') $params .= '&tz=' . $this->tz;
        if ($req_id) $params .= '&req_id=' . $req_id;
        if ($params) $restApi .= '?_=1' . $params;

        $res = $this->curl($restApi, 'POST', $sql, 60, "Authorization: Basic ".$this->token);
        if ($res) {
            $ret = json_decode($res, true);
            if ($ret['code'] != 0) {
                $this->errno = $ret['code'];
                $this->error = $ret['desc'];
                return false;
            }
            return $ret;
        }
        return false;
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

    /** SQL安全过滤
     * @param $str
     * @return string
     */
    public function quote($str) {
        return str_replace("'", "''", $str);
    }

	public function exec($sql) {
        return $this->request($sql);
	}

    public function query($sql, $type = self::FETCH_ASSOC)
    {
        return $this->request($sql);
    }

	public function fetchAll($sql, $type = 'assoc'){

    }

	public function fetch($query, $type = 'assoc') {

	}

    /**
     * 结果集行数
     * @return int
     */
	public function rowCount() {
		return 0;
	}

    /**
     * 取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
     * @return mixed
     */
	public function lastInsertId($sequenceName=null) {
		return 0;
	}
}