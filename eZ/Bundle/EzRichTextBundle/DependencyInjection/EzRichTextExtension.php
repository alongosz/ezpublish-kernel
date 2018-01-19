<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzRichTextBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EzRichTextExtension extends Extension
{
    public function getAlias()
    {
        return 'ezrichtext';
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $this->mapCustomTagsConfig($config, $container);
    }

    /**
     * Map RichText Custom Tags config to Container parameters.
     *
     * @param array $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    private function mapCustomTagsConfig(array $config, ContainerBuilder $container)
    {
        foreach ($config['custom_tags'] as $customTagName => $customTagSettings) {
            $container->setParameter("ezrichtext.custom_tags.{$customTagName}", $customTagSettings);
        }
    }
}
