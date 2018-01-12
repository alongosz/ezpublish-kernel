<?php

/**
 * File containing the RichTextTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Configuration\Parser\FieldType;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\EzPublishCoreExtension;
use eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Configuration\Parser\AbstractParserTestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\Parser\FieldType\RichText as RichTextConfigParser;
use Symfony\Component\Yaml\Yaml;

class RichTextTest extends AbstractParserTestCase
{
    /**
     * Return an array of container extensions you need to be registered for each test (usually just the container
     * extension you are testing.
     *
     * @return ExtensionInterface[]
     */
    protected function getContainerExtensions()
    {
        return array(
            new EzPublishCoreExtension(array(new RichTextConfigParser())),
        );
    }

    protected function getMinimalConfiguration()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/../../../Fixtures/ezpublish_minimal.yml'));
    }

    public function testDefaultContentSettings()
    {
        $this->load();

        $this->assertConfigResolverParameterValue(
            'fieldtypes.ezrichtext.tags.default',
            array(
                'template' => 'EzPublishCoreBundle:FieldType/RichText/tag:default.html.twig',
            ),
            'ezdemo_site'
        );
        $this->assertConfigResolverParameterValue(
            'fieldtypes.ezrichtext.output_custom_xsl',
            array(
                0 => array(
                    'path' => '%kernel.root_dir%/../vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/docbook/xhtml5/output/core.xsl',
                    'priority' => 0,
                ),
            ),
            'ezdemo_site'
        );
    }

    /**
     * @dataProvider richTextSettingsProvider
     */
    public function testRichTextSettings(array $config, array $expected)
    {
        $this->load(
            array(
                'system' => array(
                    'ezdemo_site' => $config,
                ),
            )
        );

        foreach ($expected as $key => $val) {
            $this->assertConfigResolverParameterValue($key, $val, 'ezdemo_site');
        }
    }

    public function richTextSettingsProvider()
    {
        return [
            [
                [
                    'fieldtypes' => [
                        'ezrichtext' => [
                            'tags' => [
                                'default' => [
                                    'template' => 'MyBundle:FieldType/RichText/tag:default.html.twig',
                                    'config' => [
                                        'watch' => 'out',
                                        'only' => 'first level',
                                        'can' => 'be mapped to ezxml',
                                    ],
                                ],
                                'math_equation' => [
                                    'template' => 'MyBundle:FieldType/RichText/tag:math_equation.html.twig',
                                    'config' => [
                                        'some' => 'arbitrary',
                                        'hash' => [
                                            'structure' => 12345,
                                            'works' => [
                                                'drink' => 'beer',
                                                'explode' => false,
                                            ],
                                            'does not work' => [
                                                'drink' => 'whiskey',
                                                'deeble' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'fieldtypes.ezrichtext.tags.default' => [
                        'template' => 'MyBundle:FieldType/RichText/tag:default.html.twig',
                        'config' => [
                            'watch' => 'out',
                            'only' => 'first level',
                            'can' => 'be mapped to ezxml',
                        ],
                    ],
                    'fieldtypes.ezrichtext.tags.math_equation' => [
                        'template' => 'MyBundle:FieldType/RichText/tag:math_equation.html.twig',
                        'config' => [
                            'some' => 'arbitrary',
                            'hash' => [
                                'structure' => 12345,
                                'works' => [
                                    'drink' => 'beer',
                                    'explode' => false,
                                ],
                                'does not work' => [
                                    'drink' => 'whiskey',
                                    'deeble' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'fieldtypes' => [
                        'ezrichtext' => [
                            'embed' => [
                                'content' => [
                                    'template' => 'MyBundle:FieldType/RichText/embed:content.html.twig',
                                    'config' => [
                                        'have' => [
                                            'spacesuit' => [
                                                'travel' => true,
                                            ],
                                        ],
                                    ],
                                ],
                                'location_inline_denied' => [
                                    'template' => 'MyBundle:FieldType/RichText/embed:location_inline_denied.html.twig',
                                    'config' => [
                                        'have' => [
                                            'location' => [
                                                'index' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'fieldtypes.ezrichtext.embed.content' => [
                        'template' => 'MyBundle:FieldType/RichText/embed:content.html.twig',
                        'config' => [
                            'have' => [
                                'spacesuit' => [
                                    'travel' => true,
                                ],
                            ],
                        ],
                    ],
                    'fieldtypes.ezrichtext.embed.location_inline_denied' => [
                        'template' => 'MyBundle:FieldType/RichText/embed:location_inline_denied.html.twig',
                        'config' => [
                            'have' => [
                                'location' => [
                                    'index' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'fieldtypes' => [
                        'ezrichtext' => [
                            'custom_tags' => [
                                'ezyoutube' => [
                                    'attributes' => [
                                        'title' => [
                                            'type' => 'string',
                                            'required' => true,
                                            'default_value' => '',
                                        ],
                                        'inline' => [
                                            'type' => 'boolean',
                                            'required' => false,
                                            'default_value' => true,
                                        ],
                                        'limit' => [
                                            'type' => 'number',
                                            'required' => true,
                                            'default_value' => 5,
                                        ],
                                        'cssClass' => [
                                            'type' => 'choice',
                                            'required' => false,
                                            'default_value' => '',
                                            'choices' => [
                                                'super-class',
                                                'extra-class',
                                                'super-extra-class',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'fieldtypes.ezrichtext.custom_tags' => [
                        'ezyoutube' => [
                            'attributes' => [
                                'title' => [
                                    'type' => 'string',
                                    'required' => true,
                                    'default_value' => '',
                                    'choices' => [],
                                ],
                                'inline' => [
                                    'type' => 'boolean',
                                    'required' => false,
                                    'default_value' => true,
                                    'choices' => [],
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'required' => true,
                                    'default_value' => 5,
                                    'choices' => [],
                                ],
                                'cssClass' => [
                                    'type' => 'choice',
                                    'required' => false,
                                    'default_value' => '',
                                    'choices' => [
                                        'super-class',
                                        'extra-class',
                                        'super-extra-class',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
