<?php

declare(strict_types=1);

namespace Demo;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

final readonly class PrepareService
{
    public function __construct(private string $directory)
    {
    }

    #[AsCommand('demo:prepare', 'Create the demo DuckDB file from the bundled JSON array')]
    public function prepare(SymfonyStyle $io): int
    {
        $path = $this->directory.'/var/example.duckdb';
        if (is_file($path)) {
            $io->note('Demo database already exists.');

            return Command::SUCCESS;
        }
        $fs = new Filesystem();
        $fs->mkdir(dirname($path));
        $temporary = $path.'.'.bin2hex(random_bytes(6));
        try {
            $db = new \PDO('duckdb:'.$temporary);
            $db->exec('CREATE TABLE products (id VARCHAR PRIMARY KEY, title VARCHAR NOT NULL, year INTEGER NOT NULL)');
            $db->prepare('INSERT INTO products SELECT id, title, year FROM read_json(?)')->execute([dirname(__DIR__).'/data/products.json']);
            $db->exec('CHECKPOINT');
            $db = null;
            $fs->rename($temporary, $path);
        } finally {
            $db = null;
            $fs->remove([$temporary, $temporary.'.wal']);
        }
        $io->success('Created var/example.duckdb. Open /api/example.');

        return Command::SUCCESS;
    }
}
