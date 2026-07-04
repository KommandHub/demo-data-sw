<?php

declare(strict_types=1);

// If running in CI → skip Shopware bootstrap
if (getenv('CI') === 'true') {
    $rootAutoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

    if (is_file($rootAutoload)) {
        $loader = require $rootAutoload;
        $loader->addPsr4('Kommandhub\\DemoDataSW\\', dirname(__DIR__) . '/src');
        $loader->addPsr4('Kommandhub\\DemoDataSW\\Tests\\', __DIR__);

        return;
    }

    $loader = require __DIR__ . '/../vendor/autoload.php';
    $loader->addPsr4('Kommandhub\\DemoDataSW\\', dirname(__DIR__) . '/src');
    $loader->addPsr4('Kommandhub\\DemoDataSW\\Tests\\', __DIR__);

    return;
}

// Otherwise (local dev) → use Shopware bootstrap
require __DIR__ . '/TestBootstrap.php';
