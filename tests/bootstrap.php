<?php

declare(strict_types=1);
$autoload = getenv('DUCKDB_API_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('Survos\\DuckDbApiBundle\\', dirname(__DIR__).'/src');
$loader->addPsr4('Survos\\DuckDbApiBundle\\Tests\\', __DIR__);
