<?php
define('APP_PATH',__DIR__.'/app');

require __DIR__ . "/src/TaosRestApi.php";

$taos = new \TDEngine\TaosRestApi('192.168.0.219:6041', 'root', 'taosdata');
#var_dump($taos->createDb('monitor', false, "KEEP 180 DURATION 31 BUFFER 32"), $taos->errno, $taos->error);
var_dump($taos->dropDb('monitor'), $taos->errno, $taos->error);

//req_sec,idle,load1,load5,load15,io,up,down,cpu_usage,mem_usage,disk1_usage,disk2_usage
//tags:chain_id,cpu,mem,disk
/*
[
  "0.3356",
  1692861994,
  {
    "init_sleep": 0,
    "base": {
      "hostname": "ea7cd6a251b2",
      "os": "Linux",
      "arch": "x86_64",
      "system": "Ubuntu 20.04.2 LTS",
      "php_ver": "7.2.33",
      "cpu_name": "Intel(R) Xeon(R) Platinum 8269CY CPU @ 2.50GHz",
      "cpu_cores": 8
    },
    "load": [
      1.72,
      1.35,
      1.23
    ],
    "cpu": {
      "cpu": 17.91
    },
    "load_rate": 22,
    "mem": {
      "total": 16013,
      "free": 550,
      "available": 7837,
      "buffers": 972,
      "cached": 7201,
      "used": 7290,
      "used_percent": 45.5
    },
    "disk": [
      {
        "mounted": "\/",
        "type": "overlay",
        "size": "40G",
        "used": "17G",
        "avail": "21G",
        "use%": "46%",
        "Inodes": "2621440",
        "IUsed": "301168",
        "IFree": "2320272",
        "IUse%": "12%"
      },
      {
        "mounted": "\/home\/mysql",
        "type": "ext4",
        "size": "492G",
        "used": "139G",
        "avail": "330G",
        "use%": "30%",
        "Inodes": "2049638",
        "IUsed": "3",
        "IFree": "2049635",
        "IUse%": "1%"
      },
      {
        "mounted": "\/etc\/hosts",
        "type": "ext4",
        "size": "40G",
        "used": "17G",
        "avail": "21G",
        "use%": "46%",
        "Inodes": "32768000",
        "IUsed": "136180",
        "IFree": "32631820",
        "IUse%": "1%"
      }
    ],
    "disk_io": {
      "all": {
        "rd_ios": 24,
        "rd_bytes": 491520,
        "rd_time": 8,
        "wr_ios": 1188,
        "wr_bytes": 11280384,
        "wr_time": 460,
        "rd_merges": 0,
        "wr_merges": 1174,
        "io_time": 632,
        "util": 63.2
      },
      "vda": {
        "rd_ios": 0,
        "rd_bytes": 0,
        "rd_time": 0,
        "wr_ios": 547,
        "wr_bytes": 4370432,
        "wr_time": 216,
        "rd_merges": 0,
        "wr_merges": 463,
        "io_time": 164,
        "util": 16.4
      },
      "vda1": {
        "rd_ios": 0,
        "rd_bytes": 0,
        "rd_time": 0,
        "wr_ios": 540,
        "wr_bytes": 4370432,
        "wr_time": 216,
        "rd_merges": 0,
        "wr_merges": 463,
        "io_time": 164,
        "util": 16.4
      },
      "vdb": {
        "rd_ios": 12,
        "rd_bytes": 245760,
        "rd_time": 4,
        "wr_ios": 51,
        "wr_bytes": 1269760,
        "wr_time": 14,
        "rd_merges": 0,
        "wr_merges": 124,
        "io_time": 152,
        "util": 15.2
      },
      "vdb1": {
        "rd_ios": 12,
        "rd_bytes": 245760,
        "rd_time": 4,
        "wr_ios": 50,
        "wr_bytes": 1269760,
        "wr_time": 14,
        "rd_merges": 0,
        "wr_merges": 124,
        "io_time": 152,
        "util": 15.2
      }
    },
    "net_io": {
      "all": {
        "rx_bytes": 291493,
        "rx_packets": 1194,
        "rx_errs": 0,
        "rx_drop": 0,
        "rx_fifo": 0,
        "tx_bytes": 904109,
        "tx_packets": 1261,
        "tx_errs": 0,
        "tx_drop": 0,
        "tx_fifo": 0
      },
      "eth0": {
        "rx_bytes": 291493,
        "rx_packets": 1194,
        "rx_errs": 0,
        "rx_drop": 0,
        "rx_fifo": 0,
        "tx_bytes": 904109,
        "tx_packets": 1261,
        "tx_errs": 0,
        "tx_drop": 0,
        "tx_fifo": 0
      },
      "_all": {
        "rx_packets": 18660341867,
        "rx_bytes": 5021888036094,
        "rx_errs": 0,
        "rx_drop": 0,
        "rx_fifo": 0,
        "tx_bytes": 9645869616690,
        "tx_packets": 18026499956,
        "tx_errs": 0,
        "tx_drop": 0,
        "tx_fifo": 0
      }
    },
    "uptime": {
      "run_time": "417 day, 16:6",
      "run_idle": 89.87
    }
  }
]

      "hostname": "ea7cd6a251b2",
      "os": "Linux",
      "arch": "x86_64",
      "system": "Ubuntu 20.04.2 LTS",
      "php_ver": "7.2.33",
      "cpu_name": "Intel(R) Xeon(R) Platinum 8269CY CPU @ 2.50GHz",
      "cpu_cores": 8
*/

//超级表
$sql = "CREATE STABLE monitor (ts timestamp, req_sec float, idle float,load1 float,load5 float,load15 float,io int,up int,down int, disk1 smallint,disk2 smallint) TAGS (system binary(64), php_ver binary(16), cpu_name binary(128), cpu_cores tinyint, mem_total int)";
$taos->exec($sql);
//超级表
$sql = "CREATE TABLE m24819 (ts timestamp, req_sec float, idle float,load1 float,load5 float,load15 float,io int,up int,down int, disk1 smallint,disk2 smallint) TAGS (system binary(64), php_ver binary(16), cpu_name binary(128), cpu_cores tinyint, mem_total int)";

$sql = 'CREATE TABLE m24819 USING monitor TAGS ("California.SanFrancisco", 2)';
$taos->exec($sql);
