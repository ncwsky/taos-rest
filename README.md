##使用
```
composer require myphps/taos-rest
```
##示例
```php
<?php
require __DIR__ . '/vendor/autoload.php';

$host = '127.0.0.1:6041';
$user = 'root';
$pwd = 'taosdata';

$taos = new \TDEngine\TaosRestApi($host, $user, $pwd, 'test');

$taos->exec('SHOW TABLES');
echo json_encode($taos->fetch()).PHP_EOL;
echo json_encode($taos->query('SHOW TABLES')).PHP_EOL;
```