<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Tower\Console\Color;
use Tower\JobWorker;
use Tower\Loader;
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

if (!class_exists('App\Jobs\Kernel')){
    echo Color::error('no job found');
    return;
}

Dotenv::createImmutable(basePath())->load();

$app = include configPath() . "app.php";

Loader::save([
    'app' => configPath() . "app.php" ,
    'database' => configPath() . "database.php" ,
    'elastic' => configPath() . "elastic.php" ,
    'mail' => configPath() . "mail.php" ,
    'redis' => configPath() . "redis.php" ,
    'server' => configPath() . "server.php" ,
    'errors' => languagePath() . 'validation/' . $app['locale'] . '/errors.php' ,
]);

$serverConfig = Loader::get('server');
$appConfig = Loader::get('app');

Worker::$pidFile = storagePath() . 'queue.pid';
Worker::$stdoutFile = storagePath() . 'logs/queue.log';
TcpConnection::$defaultMaxPackageSize = $serverConfig['max_package_size'] ?? 10*1024*1024;

$worker = new Worker();
$worker->name = 'queue';

date_default_timezone_set($appConfig['timezone']);

$jobWorker = new JobWorker();

$worker->onWorkerStart = [$jobWorker, 'onWorkerStart'];

if ($watch == 1 && $argv[1] == 'start'){
    $worker = new Worker();
    $unemployed = new Unemployed();

    $worker->name = 'unemployed man';

    $worker->onWorkerStart = [$unemployed , 'check'];
}

Worker::runAll();