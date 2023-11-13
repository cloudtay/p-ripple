<?php
declare(strict_types=1);
include __DIR__ . '/vendor/autoload.php';

use App\Http\HttpWorker;
use App\Http\Request;
use App\ProcessManager\ProcessManager;
use Protocol\CCL;

$pRipple = $kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$processManager = ProcessManager::new('ProcessManager')
    ->bind('unix://' . ProcessManager::$UNIX_PATH)
    ->protocol(CCL::class);
$http = HttpWorker::new('http_worker_name')->bind('tcp://0.0.0.0:8001', [SO_REUSEPORT => true]);
$http->defineRequestHandler(function (Request $request) {

});

$pRipple->push($processManager, $http)->launch();
