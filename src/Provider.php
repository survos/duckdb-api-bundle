<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/** Read-only API Platform 5 adapter. Independent of application entities and database schema. */
final readonly class Provider implements ProviderInterface
{
    public function __construct(private ConnectionProviderInterface $connections, private DenormalizerInterface $denormalizer)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $options = $operation->getStateOptions();
        if (!$options instanceof Options) {
            throw new \LogicException('Configure DuckDbApi Options on the operation.');
        }
        $db = $this->connections->open($operation, $uriVariables);
        if ('duckdb' !== $db->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new \LogicException('Native PDO DuckDB is required.');
        }
        $select = [];
        foreach ($options->columns as $property => $column) {
            $select[] = self::quote($column).' AS '.self::quote($property);
        }
        $from = ' FROM '.(null === $options->schema ? '' : self::quote($options->schema).'.').self::quote($options->table);
        $sql = 'SELECT '.implode(', ', $select).$from;
        $hydrate = fn (array $row): object => $this->denormalizer->denormalize(
            array_replace($uriVariables, $row), $operation->getClass(), context: ['allow_extra_attributes' => true],
        );
        $idColumn = self::quote($options->columns[$options->identifier]);
        if (!$operation instanceof CollectionOperationInterface) {
            $row = $this->execute($db, $sql.' WHERE '.$idColumn.' = ?', [$uriVariables[$options->identifier]])->fetch(\PDO::FETCH_ASSOC);

            return false === $row ? null : $hydrate($row);
        }

        $page = $this->positiveInt($this->value($operation, 'page', 1), 'page', 1000000);
        $limit = $this->positiveInt($this->value($operation, 'itemsPerPage', 30), 'itemsPerPage', 100);
        $clauses = $bindings = [];
        foreach ($options->filters as $property => $mode) {
            $value = $this->value($operation, $property);
            if (null === $value) {
                continue;
            }
            if (!is_scalar($value) || ('contains' === $mode && !is_string($value))) {
                throw new BadRequestHttpException('Invalid filter value for '.$property.'.');
            }
            $column = self::quote($options->columns[$property]);
            $clauses[] = 'contains' === $mode ? 'contains(lower('.$column.'), lower(?))' : $column.' = ?';
            $bindings[] = $value;
        }
        $where = [] === $clauses ? '' : ' WHERE '.implode(' AND ', $clauses);
        $sort = $this->value($operation, 'sort', $options->identifier);
        $direction = $this->value($operation, 'direction', 'asc');
        if (!in_array($sort, $options->sortable, true) || !in_array($direction, ['asc', 'desc'], true)) {
            throw new BadRequestHttpException('Unsupported sort or direction.');
        }
        $order = self::quote($options->columns[$sort]).' '.strtoupper($direction).' NULLS LAST, '.$idColumn.' ASC';
        $total = (int) $this->execute($db, 'SELECT count(*)'.$from.$where, $bindings)->fetchColumn();
        $rows = $this->execute($db, $sql.$where.' ORDER BY '.$order.' LIMIT '.$limit.' OFFSET '.(($page - 1) * $limit), $bindings)->fetchAll(\PDO::FETCH_ASSOC);

        return new Page(array_map($hydrate, $rows), $page, $limit, $total);
    }

    private function value(Operation $operation, string $name, mixed $default = null): mixed
    {
        $parameter = $operation->getParameters()?->get($name);
        $value = $parameter?->getValue();

        return $value instanceof ParameterNotFound || null === $value ? $default : $value;
    }

    private function positiveInt(mixed $value, string $name, int $max): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $integer || $integer < 1 || $integer > $max || is_bool($value)) {
            throw new BadRequestHttpException(sprintf('%s must be between 1 and %d.', $name, $max));
        }

        return $integer;
    }

    private static function quote(string $identifier): string
    {
        if ('' === $identifier || str_contains($identifier, "\0")) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function execute(\PDO $db, string $sql, array $bindings): \PDOStatement
    {
        $statement = $db->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }
}
