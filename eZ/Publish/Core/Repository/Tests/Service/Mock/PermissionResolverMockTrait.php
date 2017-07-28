<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Repository\Tests\Service\Mock;

use eZ\Publish\API\Repository\Values\User\UserReference;
use eZ\Publish\Core\Repository\Helper\LimitationService;
use eZ\Publish\Core\Repository\Helper\RoleDomainMapper;
use eZ\Publish\Core\Repository\Permission\PermissionResolver;
use eZ\Publish\Core\Repository\Repository;

/**
 * Unit test trait to aid mocking PermissionResolver.
 *
 * @method \PHPUnit_Framework_MockObject_MockBuilder getMockBuilder(string $className)
 * @method \eZ\Publish\SPI\Persistence\Handler getPersistenceMock()
 */
trait PermissionResolverMockTrait
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repositoryMock;

    /**
     * @var \eZ\Publish\API\Repository\PermissionResolver
     */
    protected $permissionResolverMock;

    /**
     * @var \eZ\Publish\Core\Repository\Helper\LimitationService
     */
    protected $limitationServiceMock;

    /**
     * @var \eZ\Publish\Core\Repository\Helper\RoleDomainMapper
     */
    protected $roleDomainMapperMock;

    /**
     * @var \eZ\Publish\API\Repository\Values\User\UserReference
     */
    protected $userReferenceMock;

    protected function getUserReferenceMock()
    {
        if ($this->userReferenceMock === null) {
            $this->userReferenceMock = $this
                ->getMockBuilder(UserReference::class)
                ->getMock();
        }

        return $this->userReferenceMock;
    }

    /**
     * @param array $methods
     *
     * @return \eZ\Publish\API\Repository\PermissionResolver|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getPermissionResolverMock($methods = [])
    {
        if ($this->permissionResolverMock === null) {
            $this->permissionResolverMock = $this
                ->getMockBuilder(PermissionResolver::class)
                ->setMethods($methods)
                ->setConstructorArgs(
                    [
                        $this->getRoleDomainMapperMock(),
                        $this->getLimitationServiceMock(),
                        $this->getPersistenceMock()->userHandler(),
                        $this->getUserReferenceMock(),
                    ]
                )
                ->getMock();
        }

        return $this->permissionResolverMock;
    }

    /**
     * @param array $methods
     *
     * @return \eZ\Publish\Core\Repository\Helper\RoleDomainMapper|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRoleDomainMapperMock($methods = [])
    {
        if ($this->roleDomainMapperMock === null) {
            $this->roleDomainMapperMock = $this
                ->getMockBuilder(RoleDomainMapper::class)
                ->setMethods($methods)
                ->disableOriginalConstructor()
                ->getMock();
        }

        return $this->roleDomainMapperMock;
    }

    /**
     * @param array $methods
     *
     * @return \eZ\Publish\Core\Repository\Helper\LimitationService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getLimitationServiceMock($methods = [])
    {
        if ($this->limitationServiceMock === null) {
            $this->limitationServiceMock = $this
                ->getMockBuilder(LimitationService::class)
                ->setMethods($methods)
                ->disableOriginalConstructor()
                ->getMock();
        }

        return $this->limitationServiceMock;
    }

    /**
     * @param array $methods
     *
     * @return \eZ\Publish\API\Repository\Repository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRepositoryMock($methods = [])
    {
        if ($this->repositoryMock === null) {
            $methods[] = 'getPermissionResolver';
            $this->repositoryMock = $this
                ->getMockBuilder(Repository::class)
                ->setMethods($methods)
                ->disableOriginalConstructor()
                ->getMock();

            $this->repositoryMock
                ->expects($this->any())
                ->method('getPermissionResolver')
                ->will($this->returnValue($this->getPermissionResolverMock(null)));
        }

        return $this->repositoryMock;
    }
}
