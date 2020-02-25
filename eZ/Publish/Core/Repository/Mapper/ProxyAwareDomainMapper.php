<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\Core\Repository\Mapper;

use eZ\Publish\Core\Repository\ProxyFactory\ProxyDomainMapperInterface;

/**
 * Common abstraction for domain mappers providing properties loaded via proxy.
 *
 * @internal For internal use by Domain Mappers
 */
abstract class ProxyAwareDomainMapper
{
    /** @var \eZ\Publish\Core\Repository\ProxyFactory\ProxyDomainMapperInterface */
    protected $proxyFactory;

    public function __construct(ProxyDomainMapperInterface $proxyFactory)
    {
        $this->proxyFactory = $proxyFactory;
    }
}
