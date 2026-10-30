<?php

declare(strict_types=1);

namespace Demo\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use Survos\DuckDbApiBundle\Options;
use Survos\DuckDbApiBundle\Provider;

#[ApiResource(
    operations: [
        new Get(uriTemplate: '/{sourceKey}/products/{id}', uriVariables: ['sourceKey' => new Link(fromClass: self::class, identifiers: ['sourceKey']), 'id' => new Link(fromClass: self::class, identifiers: ['id'])]),
        new GetCollection(uriTemplate: '/{sourceKey}/products', uriVariables: ['sourceKey' => new Link(fromClass: self::class, identifiers: ['sourceKey'])], parameters: [
            'title' => new QueryParameter(schema: ['type' => 'string']),
            'year' => new QueryParameter(schema: ['type' => 'integer'], castToNativeType: true),
            'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1], castToNativeType: true, default: 1),
            'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], castToNativeType: true, default: 30),
            'sort' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['id', 'title', 'year']], default: 'id'),
            'direction' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['asc', 'desc']], default: 'asc'),
        ]),
    ],
    provider: Provider::class,
    stateOptions: new Options(table: 'products', columns: ['id' => 'id', 'title' => 'title', 'year' => 'year'], filters: ['title' => 'contains', 'year' => 'exact'], sortable: ['id', 'title', 'year']),
    strictQueryParameterValidation: true,
    paginationEnabled: true, paginationClientEnabled: false, paginationItemsPerPage: 30, paginationClientItemsPerPage: true, paginationMaximumItemsPerPage: 100,
)]
final readonly class Product
{
    public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $title, public int $year, #[ApiProperty(identifier: true)] public string $sourceKey)
    {
    }
}
