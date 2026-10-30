<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\DuckDbBundle\ConnectionFactory;
use Survos\DuckDbBundle\NamedSources;
use Survos\DuckDbBundle\S3Configuration;
use Symfony\Component\Filesystem\Filesystem;

final class S3ConnectionTest extends TestCase
{
    public function testSignedS3RangeReadsAndReadOnlyAttachment(): void
    {
        $directory = (getenv('DUCKDB_API_TEST_TMPDIR') ?: sys_get_temp_dir()).'/duckdb-s3-'.bin2hex(random_bytes(5));
        $fs = new Filesystem();
        $fs->mkdir($directory.'/bucket');
        $server = null;
        try {
            $db = new \PDO('duckdb:'.$directory.'/bucket/test.duckdb');
            $db->exec("CREATE TABLE records (id INTEGER PRIMARY KEY, title VARCHAR); INSERT INTO records VALUES (1, 'Remote'); CHECKPOINT");
            $db = null;
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $server = proc_open([PHP_BINARY, '-S', $address, __DIR__.'/Support/s3-router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes, $directory);
            self::assertIsResource($server);
            fclose($pipes[0]);
            $ready = false;
            for ($i = 0; $i < 100; ++$i) {
                $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05);
                if (false !== $probe) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            self::assertTrue($ready, 'S3 transport fixture did not start.');
            $sources = new NamedSources(new ConnectionFactory(), ['remote' => [
                'uri' => 's3://bucket/test.duckdb',
                's3' => ['endpoint' => 'http://'.$address, 'region' => 'us-east-1', 'url_style' => 'path', 'access_key' => 'test-key', 'secret_key' => 'test-secret'],
            ]]);
            $db = $sources->open('remote');
            self::assertSame('Remote', $db->query('SELECT title FROM records WHERE id = 1')->fetchColumn());
            try {
                $db->exec('DELETE FROM records');
                self::fail('Remote attachment accepted a write.');
            } catch (\PDOException) {
                self::assertSame(1, $db->query('SELECT count(*) FROM records')->fetchColumn());
            }
            $db = null;
        } finally {
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
            $fs->remove($directory);
        }
    }

    public function testUnknownSourceDoesNotOpenAnyConfiguredFile(): void
    {
        $sources = new NamedSources(new ConnectionFactory(), ['missing' => ['uri' => '/does-not-exist.duckdb']]);
        self::assertTrue($sources->has('missing'));
        self::assertFalse($sources->has('unknown'));
        $this->expectException(\OutOfBoundsException::class);
        $sources->open('unknown');
    }

    public function testPartialCredentialsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new S3Configuration(accessKey: 'key-without-secret');
    }
}
