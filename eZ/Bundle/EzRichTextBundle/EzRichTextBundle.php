<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzRichTextBundle;

use eZ\Bundle\EzRichTextBundle\DependencyInjection\EzRichTextExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EzRichTextBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (!isset($this->extension)) {
            $this->extension = new EzRichTextExtension();
        }

        return $this->extension;
    }
}
