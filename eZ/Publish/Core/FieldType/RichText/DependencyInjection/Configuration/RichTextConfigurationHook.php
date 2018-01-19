<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\RichText\DependencyInjection\Configuration;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\CustomConfigurationHook;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\ContextualizerInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configuration Hook for Symfony Bundle Extension.
 */
class RichTextConfigurationHook implements CustomConfigurationHook
{
    const SUPPORTED_ATTRIBUTE_TYPES = ['number', 'string', 'boolean', 'choice'];

    /**
     * {@inheritdoc}
     */
    public function getCustomNodeName()
    {
        return 'ezrichtext';
    }

    /**
     * {@inheritdoc}
     */
    public function addSemanticConfig(NodeBuilder $nodeBuilder)
    {
        $this->addCustomTagsSemanticConfig($nodeBuilder);
    }

    /**
     * {@inheritdoc}
     */
    public function preMap(array $config, ContextualizerInterface $contextualizer)
    {
        // Nothing to do
    }

    /**
     * {@inheritdoc}
     */
    public function mapConfig(array $config, ContainerBuilder $containerBuilder)
    {
        if (!isset($config['ezrichtext'])) {
            return;
        }

        $this->postMapCustomTags($config['ezrichtext'], $containerBuilder);
    }

    /**
     * RichText Custom Tags Semantic Configuration definition.
     *
     * The configuration is available at:
     * <code>
     * ezpublish: # or any Symfony Bundle Extension alias using the Hook
     *     ezrichtext:
     *         custom_tags:
     * </code>
     *
     * @param \Symfony\Component\Config\Definition\Builder\NodeBuilder $nodeBuilder
     */
    private function addCustomTagsSemanticConfig(NodeBuilder $nodeBuilder)
    {
        $nodeBuilder
            ->arrayNode('custom_tags')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('template')
                            ->isRequired()
                        ->end()
                        ->scalarNode('icon')
                        ->end()
                        ->arrayNode('attributes')
                            ->isRequired()
                            ->arrayPrototype()
                                ->validate()
                                    ->ifTrue(
                                        function ($v) {
                                            return $v['type'] === 'choice' && empty($v['choices']);
                                        }
                                    )
                                    ->thenInvalid('Choice type has to define choices list')
                                ->end()
                                ->validate()
                                    ->ifTrue(
                                        function ($v) {
                                            return $v['type'] !== 'choice' && !empty($v['choices']);
                                        }
                                    )
                                    ->thenInvalid('List of choices is supported by choices type only.')
                                ->end()
                                ->children()
                                    ->enumNode('type')
                                        ->isRequired()
                                        ->values(static::SUPPORTED_ATTRIBUTE_TYPES)
                                    ->end()
                                    ->booleanNode('required')
                                        ->defaultFalse()
                                    ->end()
                                    ->scalarNode('default_value')
                                    ->end()
                                    ->arrayNode('choices')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->variableNode('options')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Map RichText Custom Tags configuration to contextual parameters.
     *
     * @param array $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $containerBuilder
     */
    public function postMapCustomTags(array $config, ContainerBuilder $containerBuilder)
    {
        if (!isset($config['custom_tags'])) {
            return;
        }

        $containerBuilder->setParameter(
            'ezrichtext.custom_tags',
            $config['custom_tags']
        );
    }
}
