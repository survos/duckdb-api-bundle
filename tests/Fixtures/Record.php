<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests\Fixtures;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use Survos\DuckDbApiBundle\Options;
use Survos\DuckDbApiBundle\Provider;

#[ApiResource(operations: [new Get(uriTemplate: '/records/{id}'), new GetCollection(uriTemplate: '/records', parameters: [
    'title' => new QueryParameter(schema: ['type' => 'string']),
    'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1], castToNativeType: true, default: 1),
    'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], castToNativeType: true, default: 30),
])], provider: Provider::class, stateOptions: new Options(table: 'catalog', columns: ['id' => 'key', 'title' => 'caption'], filters: ['title' => 'contains'], sortable: ['id']), strictQueryParameterValidation: true,
    paginationEnabled: true, paginationClientEnabled: false, paginationItemsPerPage: 30, paginationClientItemsPerPage: true, paginationMaximumItemsPerPage: 100)]
final readonly class Record
{
    public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $title)
    {
    }
}
