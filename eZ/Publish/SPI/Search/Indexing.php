<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\SPI\Search;

/**
 * Interface for indexing in search backend.
 */
interface Indexing
{
    /**
     * Purge search engine index.
     */
    public function purgeIndex();
}
