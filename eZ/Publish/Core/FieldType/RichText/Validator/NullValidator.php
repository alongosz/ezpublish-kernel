<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\RichText\Validator;

use DOMDocument;

/**
 * Validator which doesn't return any errors.
 */
class NullValidator implements XmlValidator
{
    /**
     * Validate contents of DocBook XML Document.
     *
     * @param \DOMDocument $xmlDocument
     *
     * @return string[] an array of error messages
     */
    public function validateDocument(DOMDocument $xmlDocument): array
    {
        return [];
    }
}
