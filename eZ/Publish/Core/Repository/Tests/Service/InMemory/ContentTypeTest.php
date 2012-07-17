<?php
/**
 * File contains: eZ\Publish\Core\Repository\Tests\Service\InMemory\ContentTypeTest class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Repository\Tests\Service\InMemory;
use eZ\Publish\Core\Repository\Tests\Service\ContentTypeBase as BaseContentTypeServiceTest;

/**
 * Test case for ContentType Service using InMemory storage class
 */
class ContentTypeTest extends BaseContentTypeServiceTest
{
    protected function getRepository( array $serviceSettings )
    {
        return Utils::getRepository( $serviceSettings );
    }
}
