<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins(
        'KommandhubDemoDataSW'
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\\DemoDataSW\\Tests\\', __DIR__);
