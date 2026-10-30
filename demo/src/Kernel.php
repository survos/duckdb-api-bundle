<?php

declare(strict_types=1);

namespace Demo;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Survos\DuckDbApiBundle\SurvosDuckDbApiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends \Symfony\Component\HttpKernel\Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'dev', bool $debug = true, private readonly ?string $directory = null)
    {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->directory ?? dirname(__DIR__);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformBundle();
        yield new SurvosDuckDbApiBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'standalone-duckdb-demo', 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'property_info' => ['enabled' => true]]);
        $container->extension('api_platform', ['doctrine' => ['enabled' => false], 'mapping' => ['paths' => [__DIR__.'/ApiResource']], 'formats' => ['jsonld' => ['application/ld+json']]]);
        $container->import($this->getProjectDir().'/config/sources.yaml');
        $container->services()->set(PrepareService::class)->autowire()->autoconfigure()->arg('$directory', $this->getProjectDir());
        $container->services()->set(SourceController::class)->autowire()->autoconfigure()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix('/api');
        $routes->import(__DIR__.'/SourceController.php', 'attribute');
    }
}
