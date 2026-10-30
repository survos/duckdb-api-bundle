<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests;

use Demo\Kernel;
use Demo\PrepareService;
use PHPUnit\Framework\TestCase;
use Survos\DuckDbBundle\ConnectionFactory;
use Survos\DuckDbBundle\NamedSources;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

final class NamedSourcesTest extends TestCase
{
    public function testDemoAndSourceIsolation(): void
    {
        $directory = (getenv('DUCKDB_API_TEST_TMPDIR') ?: sys_get_temp_dir()).'/duckdb-named-'.bin2hex(random_bytes(5));
        $fs = new Filesystem();
        $fs->mkdir($directory.'/config');
        $kernel = new Kernel('test', true, $directory);
        try {
            $io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
            self::assertSame(0, (new PrepareService($directory))->prepare($io));
            self::assertSame(0, (new PrepareService($directory))->prepare($io));
            $db = new \PDO('duckdb:'.$directory.'/var/other.duckdb');
            $db->exec("CREATE TABLE products (id VARCHAR PRIMARY KEY, title VARCHAR, year INTEGER); INSERT INTO products VALUES ('a', 'Other source', 2026); CHECKPOINT");
            $db = null;
            $fs->dumpFile($directory.'/config/sources.yaml', "survos_duck_db:\n  sources:\n    example:\n      uri: '$directory/var/example.duckdb'\n    other:\n      uri: '$directory/var/other.duckdb'\n    blocked:\n      uri: '$directory/does-not-exist.duckdb'\nsurvos_duck_db_api:\n  resources:\n    example: [Demo\\ApiResource\\Product]\n    other: [Demo\\ApiResource\\Product]\n");
            $read = static function (string $uri, int $status = 200) use ($kernel): array {
                $response = $kernel->handle(Request::create($uri, server: ['HTTP_ACCEPT' => 'application/ld+json']));
                self::assertSame($status, $response->getStatusCode(), $response->getContent());

                return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
            };
            self::assertSame('example', $read('/api/example')['source']);
            $page = $read('/api/example/products?title=BLUE&itemsPerPage=1');
            self::assertSame(2, $page['totalItems']);
            self::assertSame('a', $page['member'][0]['id']);
            self::assertSame('Blue notebook', $read($page['member'][0]['@id'])['title']);
            self::assertSame('b', $read($page['view']['next'])['member'][0]['id']);
            self::assertSame('Other source', $read('/api/other/products/a')['title']);
            self::assertSame(2, $read('/api/example/products?year=2025')['totalItems']);
            self::assertSame('c', $read('/api/example/products?sort=year&direction=desc')['member'][1]['id']);
            $read('/api/missing/products', 404);
            $read('/api/blocked/products', 404);
            $read('/api/example/products/missing', 404);
            $read('/api/example/products?itemsPerPage=101', 422);
            $read('/api/example/products?sort=invalid', 422);
            self::assertSame(405, $kernel->handle(Request::create('/api/example/products', 'POST'))->getStatusCode());
            $sources = new NamedSources(new ConnectionFactory(), ['example' => ['uri' => $directory.'/var/example.duckdb']]);
            $db = $sources->open('example');
            try {
                $db->exec('DELETE FROM products');
                self::fail('Read-only database accepted a write.');
            } catch (\PDOException) {
                self::assertSame(3, $db->query('SELECT count(*) FROM products')->fetchColumn());
            }
            $db = null;
        } finally {
            $kernel->shutdown();
            $fs->remove($directory);
        }
    }
}
