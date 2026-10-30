<?php

declare(strict_types=1);

namespace Demo;

use ApiPlatform\Metadata\GetCollection;
use Demo\ApiResource\Product;
use Survos\DuckDbApiBundle\NamedSourceConnectionProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SourceController
{
    public function __construct(private NamedSourceConnectionProvider $connections)
    {
    }

    #[Route('/api/{sourceKey}', methods: ['GET'])]
    public function __invoke(string $sourceKey): JsonResponse
    {
        $this->connections->open(new GetCollection(class: Product::class), ['sourceKey' => $sourceKey]);

        return new JsonResponse(['source' => $sourceKey, 'products' => '/api/'.rawurlencode($sourceKey).'/products', 'example' => '/api/'.rawurlencode($sourceKey).'/products?title=blue&itemsPerPage=1']);
    }
}
