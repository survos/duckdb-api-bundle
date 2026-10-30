<?php

declare(strict_types=1);
$autoload = getenv('DUCKDB_API_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('Survos\\DuckDbApiBundle\\', dirname(__DIR__).'/src');
$loader->addPsr4('Survos\\DuckDbApiBundle\\Tests\\', __DIR__);

if (is_dir(dirname(__DIR__, 2).'/duckdb-bundle/src')) {
    $loader->addPsr4('Survos\\DuckDbBundle\\', dirname(__DIR__, 2).'/duckdb-bundle/src');
}
$loader->addPsr4('Demo\\', dirname(__DIR__).'/demo/src');
