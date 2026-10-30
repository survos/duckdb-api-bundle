<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

/** Application boundary: resolve an authorized snapshot, never a client-supplied path. */
interface ConnectionProviderInterface
{
    public function open(\ApiPlatform\Metadata\Operation $operation, array $uriVariables): \PDO;
}
