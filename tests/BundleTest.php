<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\DuckDbApiBundle\Tests\Fixtures\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

final class BundleTest extends TestCase
{
    public function testStandaloneKernelAndReadOnlyProvider(): void
    {
        self::assertContains('duckdb', \PDO::getAvailableDrivers());
        $directory = (getenv('DUCKDB_API_TEST_TMPDIR') ?: sys_get_temp_dir()).'/duckdb-api-'.bin2hex(random_bytes(5));
        $fs = new Filesystem();
        $fs->mkdir($directory);
        $kernel = new Kernel($directory);
        try {
            $db = new \PDO('duckdb:'.$directory.'/test.duckdb');
            $db->exec("CREATE TABLE catalog (key VARCHAR PRIMARY KEY, caption VARCHAR); INSERT INTO catalog VALUES ('a', 'Alpha'), ('b', 'Alpha beta'), ('c', 'Other'); CHECKPOINT");
            $db = null;
            $read = static function (string $uri, int $status = 200) use ($kernel): array {
                $response = $kernel->handle(Request::create($uri, server: ['HTTP_ACCEPT' => 'application/ld+json']));
                self::assertSame($status, $response->getStatusCode(), $response->getContent());

                return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
            };
            $page = $read('/api/records?title=ALPHA&itemsPerPage=1');
            self::assertSame(2, $page['totalItems']);
            self::assertCount(1, $page['member']);
            self::assertSame('a', $page['member'][0]['id']);
            self::assertSame('Alpha', $read($page['member'][0]['@id'])['title']);
            self::assertSame('b', $read($page['view']['next'])['member'][0]['id']);
            self::assertSame([], $read('/api/records?page=100')['member']);
            self::assertSame(0, $read('/api/records?title='.rawurlencode("' OR 1=1 --"))['totalItems']);
            $read('/api/records/missing', 404);
            $response = $kernel->handle(Request::create('/api/records', 'POST'));
            self::assertSame(405, $response->getStatusCode());
        } finally {
            $kernel->shutdown();
            $fs->remove($directory);
        }
    }
}
