# DuckDB API Bundle

Read-only API Platform **5** resources backed by native PDO DuckDB. PHP 8.5+,
Symfony 8.1+. Map SQL tables to typed API resources with filtering and pagination.

The connection provider selects a database; resource metadata selects its tables,
columns and identifiers. API Platform handles routing, validation and serialization.

```text
HTTP request → API Platform → state provider → PDO DuckDB → embedded DuckDB engine
                                           ← typed resource objects ← query rows
```

## Native PDO driver: required infrastructure

This bundle requires PHP's `ext-pdo` and **`ext-pdo_duckdb`**. The native driver is
[`thomas-0816/pdo-duckdb-php`](https://github.com/thomas-0816/pdo-duckdb-php).
It connects PDO to DuckDB in-process; its release binaries include the database
engine. Composer installs this bundle but does not install or enable that extension.
The DuckDB CLI alone does not satisfy the runtime requirement.

The tested baseline is PHP 8.5 with the driver's DuckDB 2.0 engine. Upstream currently
documents this installation command for its 2.x beta line:

```sh
pie install 'thomas-0816/pdo-duckdb-php:^2-beta'
composer require survos/duckdb-api-bundle
```

See the [driver installation guide](https://github.com/thomas-0816/pdo-duckdb-php#install-and-setup-with--pie)
for platform-specific setup, including containers and FrankenPHP. Match the build
to the PHP version, architecture and thread-safety mode serving the API.

Verify the actual PHP runtime, including the web-server/container runtime:

```sh
php --ri pdo_duckdb
php -r 'var_export(PDO::getAvailableDrivers());'
php -r '$db = new PDO("duckdb::memory:"); echo $db->query("SELECT version()")->fetchColumn(), PHP_EOL;'
composer check-platform-reqs
```

Keep three versions distinct: this bundle's Composer version, the PHP extension
version, and the embedded DuckDB engine version. `phpversion('pdo_duckdb')` reports
the extension; `SELECT version()` reports the engine. File compatibility must be
checked against the engine that opens the file, not the separately installed CLI.
The current Composer constraint checks extension presence, not engine compatibility.
Pin the driver distribution in your deployment image and test representative files
before upgrading. The initial tests cover strings, integers, nulls, bound parameters,
filtering and pagination; they do not claim coverage of every DuckDB complex type.

### Connection lifetime and read-only access

`ConnectionProviderInterface::open()` must return a native DuckDB PDO connection
configured for read-only access to the selected database. The default named-source provider enforces this through `duckdb-bundle`; custom
providers must enforce it themselves; issuing only SELECT statements is not a substitute for the engine's
read-only setting. The same returned connection serves count and page queries.

For embedded files, multiple processes can read the same file read-only; a separate
process must not write that file concurrently. Publish completed database snapshots
and open the selected snapshot for each request. See [DuckDB concurrency](https://duckdb.org/docs/stable/connect/concurrency).

Resource limits and extension loading also belong in connection configuration.
Choose memory, thread and temporary-storage limits appropriate to the web worker.
If using persistent workers or a connection pool, key connections by database scope
and revision; never reuse a previous tenant's connection for another request.

## Installation and connection boundary

`composer require survos/duckdb-api-bundle`.
Register `Survos\DuckDbApiBundle\SurvosDuckDbApiBundle` if Flex has not done so.
The connection bundle and kit bundle are registered through Symfony's RequiredBundle mechanism.

### Named sources (default)

Configure connections in `duckdb-bundle` and explicitly allow resource classes in
this bundle:

```yaml
survos_duck_db:
    sources:
        example:
            uri: '%kernel.project_dir%/var/example.duckdb'
        archive:
            uri: 's3://my-bucket/catalog.duckdb'
            s3:
                endpoint: 'https://s3.us-east-1.amazonaws.com'
                region: us-east-1
                access_key: '%env(DUCKDB_S3_ACCESS_KEY)%'
                secret_key: '%env(DUCKDB_S3_SECRET_KEY)%'

survos_duck_db_api:
    source_parameter: sourceKey
    resources:
        example: [App\ApiResource\Product]
        archive: [App\ApiResource\Product]
```

Use `/{sourceKey}/products` and `/{sourceKey}/products/{id}` operation templates
with the usual `/api` route prefix. Map both URI variables with `Link` and make
both properties API identifiers so generated item URLs retain the source name.
See `demo/src/ApiResource/Product.php` for the complete declaration. To use
`/{sourceKey}/api`, put that prefix in your operation templates instead.

Unknown sources and disallowed resource classes return 404 before opening a file.
Configuration is explicit: no directory registry, scanning, automatic table
exposure, or database connections during container compilation. Source selection
is not authentication; add application security as appropriate.

See [duckdb-bundle](https://github.com/survos/duckdb-bundle) for connection settings,
S3 credentials, `httpfs` provisioning, and snapshot requirements.

### Custom connection providers

Existing custom providers remain supported. Implement
`Survos\DuckDbApiBundle\ConnectionProviderInterface`:

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

Configuration controls file selection and resource allowlists. The application
owns authentication and any per-file entrypoint/OpenAPI documentation. URL scope can identify a tenant,
collection or database, and single-database applications can omit it.

## Testing

`composer install` then `vendor/bin/phpunit -c phpunit.xml.dist`.
When using an existing development environment, the test bootstrap accepts `DUCKDB_API_TEST_AUTOLOAD`
pointing to an existing Composer autoloader, and `DUCKDB_API_TEST_TMPDIR` for a
writable test directory. Neither is application wiring or a production fallback.

The test boots a minimal Symfony kernel with this bundle and API Platform and
queries a real read-only DuckDB file. It verifies collection and item responses,
filters, pagination links, missing items and rejected write operations.

## Independent demo

The `demo/` directory is a minimal Symfony application with its own Composer
dependencies and JSON fixture. It targets Symfony 8.2; both bundles also support
8.1 because this implementation needs no 8.2-only APIs. API Platform is restricted
to version 5, with `QueryParameter` metadata and no legacy `ApiFilter`.

```sh
cd demo
composer install
symfony server:start -d
symfony open:local --path=/api/example
```

The Composer script runs `php bin/console demo:prepare`, creating a small local
DuckDB database once. Existing files are preserved. Try:

- `/api/example/products`
- `/api/example/products/a`
- `/api/example/products?title=blue&itemsPerPage=1`
- `/api/example/products?year=2025&sort=title&direction=desc`

Edit `demo/config/sources.yaml` to add an S3 source and its resource allowlist.
Upload the checkpointed demo database to try the same schema remotely. The demo
entrypoint returns JSON links; it is not a schema-discovery UI.

Before the next release, a fresh Packagist install cannot use these additions:
publish both the new `duckdb-bundle` package and the updated `duckdb-api-bundle`
first. The demo deliberately uses normal Composer packages, without path or VCS
repository overrides. Prerelease Symfony 8.2 is allowed in the demo; stable
versions are preferred.

S3 integration tests use a local signed-request/range-read HTTP fixture with the
native driver and `httpfs`. They verify remote reads and rejected writes, not AWS
IAM policy behavior. Install `httpfs` before running this suite.
