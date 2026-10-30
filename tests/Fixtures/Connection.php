<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests\Fixtures;

use ApiPlatform\Metadata\Operation;
use Survos\DuckDbApiBundle\ConnectionProviderInterface;

final readonly class Connection implements ConnectionProviderInterface
{
    public function __construct(private string $path)
    {
    }

    public function open(Operation $operation, array $uriVariables): \PDO
    {
        return new \PDO('duckdb:'.$this->path, options: [\PDO::DUCKDB_ATTR_CONFIG => ['access_mode' => 'read_only']]);
    }
}
