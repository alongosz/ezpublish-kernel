<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Custom Configuration Hook for Core Extension.
 * Defines custom Semantic configuration nodes to be added under "ezpublish:" key.
 */
interface CustomConfigurationHook
{
    /**
     * Get the name of a node key of the Configuration Hook.
     *
     * @return string
     */
    public function getCustomNodeName();

    /**
     * Define Semantic Configuration for Core Bundle Extension configuration section.
     *
     * @param \Symfony\Component\Config\Definition\Builder\NodeBuilder $nodeBuilder
     */
    public function addSemanticConfig(NodeBuilder $nodeBuilder);

    /**
     * Map config to Container (e.g. Parameters).
     *
     * @param array $config Processed Semantic Configuration
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $containerBuilder
     */
    public function mapConfig(array $config, ContainerBuilder $containerBuilder);
}
