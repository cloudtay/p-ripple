<?php
declare(strict_types=1);

if (is_dir(__DIR__ . '/../../vendor')) {
    define('VENDOR_PATH', __DIR__ . '/../../vendor');
} elseif (is_dir(__DIR__ . '/../../../../../vendor')) {
    define('VENDOR_PATH', __DIR__ . '/../../../..');
} else {
    exit('Please use composer to install the library' . PHP_EOL);
}

require_once VENDOR_PATH . '/autoload.php';
