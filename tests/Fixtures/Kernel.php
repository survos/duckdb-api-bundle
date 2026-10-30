<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle\Tests\Fixtures;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Survos\DuckDbApiBundle\SurvosDuckDbApiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends \Symfony\Component\HttpKernel\Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $directory)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformBundle();
        yield new SurvosDuckDbApiBundle();
    }

    public function getProjectDir(): string
    {
        return $this->directory;
    }

    public function getCacheDir(): string
    {
        return $this->directory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory.'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'property_info' => ['enabled' => true]]);
        $container->extension('api_platform', ['doctrine' => ['enabled' => false], 'mapping' => ['paths' => [__DIR__]], 'formats' => ['jsonld' => ['application/ld+json']]]);
        $container->extension('survos_duck_db_api', ['connection_provider' => Connection::class]);
        $container->services()->set(Connection::class)->arg('$path', $this->directory.'/test.duckdb');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix('/api');
    }
}
