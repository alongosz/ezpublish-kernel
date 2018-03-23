<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Compiler;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\EzPublishCoreExtension;
use eZ\Publish\Core\FieldType\RichText\Validator\CustomTagsValidator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RichTextFeaturesPass implements CompilerPassInterface
{
    /**
     * Process ezpublish.ezrichtext.features Semantic Configuration node.
     *
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // Check if Custom Tags validation is enabled
        $validate = $container->getParameter(EzPublishCoreExtension::RICHTEXT_VALIDATE_CUSTOM_TAGS_PARAMETER);
        if (!$validate && ($validatorDefinition = $container->getDefinition(CustomTagsValidator::class))) {
            var_dump($validatorDefinition);
            exit;
            $validatorDefinition->setClass(NullValidator::class);
        }
    }
}
