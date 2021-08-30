<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Tower\Application;
use Tower\Console\Color;
use Tower\Unemployed;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

$watch = 0;
$daemonize = 0;

foreach ($argv as $value){
    if ($value == '--watch')
        $watch = 1;
    if ($value == '-d')
        $daemonize = 1;
}

if ($watch == 1 && $daemonize == 1){
    echo Color::error('daemon mode cannot be used in development mode');
    return;
}

Dotenv::createImmutable(basePath())->load();

$serverConfig = include configPath() . "server.php";
$appConfig = include configPath() . "app.php";

Worker::$pidFile = storagePath() . 'tower.pid';
Worker::$stdoutFile = storagePath() . 'logs/tower.log';
TcpConnection::$defaultMaxPackageSize = $serverConfig['max_package_size'] ?? 10*1024*1024;

$worker = new Worker($serverConfig['listen'], $serverConfig['context']);

$properties = [
    'name',
    'count',
    'user',
    'group',
    'reusePort',
    'transport',
];
foreach ($properties as $property)
    if (isset($serverConfig[$property]))
        $worker->$property = $serverConfig[$property];

date_default_timezone_set($appConfig['timezone']);

$boot = new Application();

$worker->onWorkerStart = [$boot , 'onWorkerStart'];

$worker->onMessage = [$boot , 'onMessage'];

if ($watch == 1 && $argv[1] == 'start'){
    $worker = new Worker();
    $unemployed = new Unemployed();

    $worker->name = 'unemployed man';

    $worker->onWorkerStart = [$unemployed , 'check'];
}

Worker::runAll();