<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

/** Trusted resource configuration; identifiers are quoted, never treated as SQL expressions. */
final readonly class Options implements \ApiPlatform\State\OptionsInterface
{
    /** @param array<string,string> $columns API property => SQL column
     * @param array<string,'exact'|'contains'> $filters  API parameter => comparison
     * @param list<string>                     $sortable
     */
    public function __construct(
        public string $table,
        public array $columns,
        public array $filters,
        public array $sortable,
        public string $identifier = 'id',
        public ?string $schema = null,
    ) {
        foreach ([$identifier, ...array_keys($filters), ...$sortable] as $property) {
            if (!isset($columns[$property])) {
                throw new \InvalidArgumentException('Unmapped DuckDB property: '.$property);
            }
        }
        foreach ($filters as $mode) {
            if (!in_array($mode, ['exact', 'contains'], true)) {
                throw new \InvalidArgumentException('Unsupported DuckDB comparison.');
            }
        }
    }
}
