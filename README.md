# DuckDB API Bundle

Read-only API Platform **5** resources backed by native PDO DuckDB. PHP 8.5+,
Symfony 8.1+. No Folio, Harvest, Doctrine, or dataset-registry dependency.

This is one Symfony bundle, not a separate library plus integration package.
Its query implementation is independent of application entities and schemas.

## Installation and connection boundary

Once published: `composer require survos/duckdb-api-bundle`.
Register `Survos\DuckDbApiBundle\SurvosDuckDbApiBundle` if Flex has not done so.
The required kit bundle is registered through Symfony's RequiredBundle mechanism.

Implement `Survos\DuckDbApiBundle\ConnectionProviderInterface`:

```php
public function open(Operation $operation, array $uriVariables): \PDO
{
    // Resolve a trusted file and authorize the resource for this URL scope.
    // Never accept a client-supplied path or SQL identifier.
    return new \PDO('duckdb:'.$trustedPath, options: [
        \PDO::DUCKDB_ATTR_CONFIG => ['access_mode' => 'read_only'],
    ]);
}
```

```yaml
survos_duck_db_api:
    connection_provider: App\Api\DatabaseConnectionProvider
```

Alternatively alias `ConnectionProviderInterface` to your service. The provider
requires a native DuckDB connection; your implementation must open it read-only.
The adapter issues SELECT statements only. No connections, filesystem paths,
tables, routes, or API resources are exposed automatically when the bundle loads.

## Explicit resources

Declare a normal API Platform resource with Get and GetCollection operations,
`provider: Survos\DuckDbApiBundle\Provider::class`, and:

```php
stateOptions: new Options(
    table: 'painting',
    schema: 'public', // optional, quoted separately from the table
    columns: ['id' => 'painting_id', 'title' => 'title', 'year' => 'created_year'],
    identifier: 'id',
    filters: ['title' => 'contains', 'year' => 'exact'],
    sortable: ['id', 'title', 'year'],
)
```

Columns map API property names to SQL columns; expressions are deliberately not
accepted. The identifier must be unique, non-null, and mapped to an item URI
variable of the same name. Output classes are hydrated by Symfony Serializer.
Unmapped URI variables may supply scope properties on the output object, keeping
generated item links in the selected database's scope.

Declare API Platform 5 `QueryParameter` entries for configured filters and
`sort`, `direction`, `page`, and `itemsPerPage`. Set parameter schemas/constraints,
`strictQueryParameterValidation: true`, pagination enabled, client pagination
disabling off, 30 default items per page and 100 maximum. The adapter currently
uses those fixed limits (page 1–1,000,000). See the executable fixture resource
in `tests/Fixtures/Record.php` for a minimal working declaration.

Filters combine with AND; `contains` is a case-insensitive literal substring,
not SQL LIKE. Sort defaults to the declared identifier ascending, nulls last,
with the identifier as a stable tiebreaker. Values are PDO-bound. Count and page
queries share the same predicates and connection. The paginator reports the
actual current-page count, including empty pages beyond the last page.

The application owns file selection, authentication, resource allowlists, and
any per-file entrypoint/OpenAPI documentation. A database scope need not be called
folioCode: it can be a tenant, collection, database ID, or no scope at all.

## Testing and status

`composer install` then `vendor/bin/phpunit -c phpunit.xml.dist`.
For monorepo development the test bootstrap accepts `DUCKDB_API_TEST_AUTOLOAD`
pointing to an existing Composer autoloader, and `DUCKDB_API_TEST_TMPDIR` for a
writable test directory. Neither is application wiring or a production fallback.

The test boots a minimal Symfony kernel with this bundle and API Platform, without
Doctrine or any Folio/Harvest services, and queries a real read-only DuckDB file.

Initial extraction from Harvest. Not yet published. Harvest's existing adapter
remains in place until this new package is installable, then switch its imports,
register this bundle, and retain only the application connection/catalog code.
Do not add a path repository to work around local package linking rules.
