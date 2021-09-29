<?php

namespace Mothership\PluginListCliExtension;

use Shopware\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class PluginListCommandExtensionBundle
 * @package MothershipGmbH\Sw6PluginListCliExtension
 */
class PluginListCommandExtensionBundle extends Bundle
{

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('config.xml');
        $this->registerMigrationPath($container);
    }

}