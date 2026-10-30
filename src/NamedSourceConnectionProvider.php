<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

use ApiPlatform\Metadata\Operation;
use Survos\DuckDbBundle\NamedSources;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class NamedSourceConnectionProvider implements ConnectionProviderInterface
{
    /** @param array<string, list<class-string>> $resources */
    public function __construct(private NamedSources $sources, private array $resources, private string $sourceParameter = 'sourceKey')
    {
    }

    public function open(Operation $operation, array $uriVariables): \PDO
    {
        $name = $uriVariables[$this->sourceParameter] ?? null;
        if (!is_string($name) || !$this->sources->has($name)
            || !in_array($operation->getClass(), $this->resources[$name] ?? [], true)) {
            throw new NotFoundHttpException('Unknown source or resource.');
        }

        return $this->sources->open($name);
    }
}
