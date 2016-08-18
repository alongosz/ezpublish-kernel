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

    /**
     * Commits changes to search index, making it available for search.
     *
     * Search engines implementing this should expose configuration to specify if this should
     * do a flush, aka hard commit, meaning changes should be forced to be written to durable
     * storage or not. By default this should be the case, but if transaction logs are enabled
     * it can be safe to optimize performance and allow disabling flushing.
     */
    public function commit();
}
