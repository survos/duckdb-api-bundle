<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

use Survos\DuckDbBundle\SurvosDuckDbBundle;
use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(SurvosDuckDbBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosDuckDbApiBundle extends AbstractSurvosBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()->children()
            ->scalarNode('source_parameter')->defaultValue('sourceKey')->cannotBeEmpty()->end()
            ->arrayNode('resources')->useAttributeAsKey('source')->normalizeKeys(false)->arrayPrototype()->scalarPrototype()->end()->end()->end()
            ->scalarNode('connection_provider')->defaultNull()
                ->info('Service implementing ConnectionProviderInterface. Alternatively define the interface alias in the application.')
            ->end()
        ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);
        $services = $container->services();
        $services->set(NamedSourceConnectionProvider::class)->autowire()
            ->arg('$resources', $config['resources'])->arg('$sourceParameter', $config['source_parameter']);
        $services->alias(ConnectionProviderInterface::class, NamedSourceConnectionProvider::class);
        if (null !== $config['connection_provider']) {
            $services->alias(ConnectionProviderInterface::class, $config['connection_provider']);
        }
        $services->set(Provider::class)->autowire()->autoconfigure()->tag('api_platform.state_provider');
    }
}
