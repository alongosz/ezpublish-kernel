<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzRichTextBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * EzRichTextBundle Semantic configuration.
 *
 * Configuration settings are available under <code>ezrichtext</code> key.
 */
class Configuration implements ConfigurationInterface
{
    const CUSTOM_TAG_ATTRIBUTE_TYPES = ['number', 'string', 'boolean', 'choice'];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder
            ->root('ezrichtext')
            ->children()
        ;

        $this->addCustomTagsSemanticConfig($rootNode);

        $rootNode->end();

        return $treeBuilder;
    }

    /**
     * RichText Custom Tags Semantic Configuration definition.
     *
     * The configuration is available at:
     * <code>
     * ezrichtext:
     *     custom_tags:
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
                                        ->values(static::CUSTOM_TAG_ATTRIBUTE_TYPES)
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
}
