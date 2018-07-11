<?php

/**
 * File containing the eZ\Publish\Core\Repository\LocationService class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Repository;

use eZ\Publish\API\Repository\PermissionCriterionResolver;
use eZ\Publish\API\Repository\Values\Content\LocationUpdateStruct;
use eZ\Publish\API\Repository\Values\Content\LocationCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Location as APILocation;
use eZ\Publish\API\Repository\Values\Content\LocationList;
use eZ\Publish\API\Repository\Values\Content\VersionInfo;
use eZ\Publish\SPI\Persistence\Content\Location\UpdateStruct;
use eZ\Publish\API\Repository\LocationService as LocationServiceInterface;
use eZ\Publish\API\Repository\Repository as RepositoryInterface;
use eZ\Publish\SPI\Persistence\Handler;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd as CriterionLogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalNot as CriterionLogicalNot;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Subtree as CriterionSubtree;
use eZ\Publish\API\Repository\Exceptions\NotFoundException as APINotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentValue;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Base\Exceptions\BadStateException;
use eZ\Publish\Core\Base\Exceptions\UnauthorizedException;
use Exception;

/**
 * Location service, used for complex subtree operations.
 *
 * @example Examples/location.php
 */
class LocationService implements LocationServiceInterface
{
    /**
     * @var \eZ\Publish\Core\Repository\Repository
     */
    protected $repository;

    /**
     * @var \eZ\Publish\SPI\Persistence\Handler
     */
    protected $persistenceHandler;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var \eZ\Publish\Core\Repository\Helper\DomainMapper
     */
    protected $domainMapper;

    /**
     * @var \eZ\Publish\Core\Repository\Helper\NameSchemaService
     */
    protected $nameSchemaService;

    /**
     * @var \eZ\Publish\API\Repository\PermissionCriterionResolver
     */
    protected $permissionCriterionResolver;

    /**
     * Setups service with reference to repository object that created it & corresponding handler.
     *
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param \eZ\Publish\SPI\Persistence\Handler $handler
     * @param \eZ\Publish\Core\Repository\Helper\DomainMapper $domainMapper
     * @param \eZ\Publish\Core\Repository\Helper\NameSchemaService $nameSchemaService
     * @param \eZ\Publish\API\Repository\PermissionCriterionResolver $permissionCriterionResolver
     * @param array $settings
     */
    public function __construct(
        RepositoryInterface $repository,
        Handler $handler,
        Helper\DomainMapper $domainMapper,
        Helper\NameSchemaService $nameSchemaService,
        PermissionCriterionResolver $permissionCriterionResolver,
        array $settings = array()
    ) {
        $this->repository = $repository;
        $this->persistenceHandler = $handler;
        $this->domainMapper = $domainMapper;
        $this->nameSchemaService = $nameSchemaService;
        // Union makes sure default settings are ignored if provided in argument
        $this->settings = $settings + array(
            //'defaultSetting' => array(),
        );
        $this->permissionCriterionResolver = $permissionCriterionResolver;
    }

    /**
     * Copies the subtree starting from $subtree as a new subtree of $targetLocation.
     *
     * Only the items on which the user has read access are copied.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed copy the subtree to the given parent location
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user does not have read access to the whole source subtree
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the target location is a sub location of the given location
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $subtree - the subtree denoted by the location to copy
     * @param \eZ\Publish\API\Repository\Values\Content\Location $targetParentLocation - the target parent location for the copy operation
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location The newly created location of the copied subtree
     */
    public function copySubtree(APILocation $subtree, APILocation $targetParentLocation)
    {
        $loadedSubtree = $this->loadLocation($subtree->id);
        $loadedTargetLocation = $this->loadLocation($targetParentLocation->id);

        if (stripos($loadedTargetLocation->pathString, $loadedSubtree->pathString) !== false) {
            throw new InvalidArgumentException('targetParentLocation', 'target parent location is a sub location of the given subtree');
        }

        // check create permission on target
        if (!$this->repository->canUser('content', 'create', $loadedSubtree->getContentInfo(), $loadedTargetLocation)) {
            throw new UnauthorizedException('content', 'create');
        }

        /** Check read access to whole source subtree
         * @var bool|\eZ\Publish\API\Repository\Values\Content\Query\Criterion
         */
        $contentReadCriterion = $this->permissionCriterionResolver->getPermissionsCriterion();
        if ($contentReadCriterion === false) {
            throw new UnauthorizedException('content', 'read');
        } elseif ($contentReadCriterion !== true) {
            // Query if there are any content in subtree current user don't have access to
            $query = new Query(
                array(
                    'limit' => 0,
                    'filter' => new CriterionLogicalAnd(
                        array(
                            new CriterionSubtree($loadedSubtree->pathString),
                            new CriterionLogicalNot($contentReadCriterion),
                        )
                    ),
                )
            );
            $result = $this->repository->getSearchService()->findContent($query, array(), false);
            if ($result->totalCount > 0) {
                throw new UnauthorizedException('content', 'read');
            }
        }

        $this->repository->beginTransaction();
        try {
            $newLocation = $this->persistenceHandler->locationHandler()->copySubtree(
                $loadedSubtree->id,
                $loadedTargetLocation->id
            );

            $content = $this->repository->getContentService()->loadContent($newLocation->contentId);
            $urlAliasNames = $this->nameSchemaService->resolveUrlAliasSchema($content);
            foreach ($urlAliasNames as $languageCode => $name) {
                $this->persistenceHandler->urlAliasHandler()->publishUrlAliasForLocation(
                    $newLocation->id,
                    $loadedTargetLocation->id,
                    $name,
                    $languageCode,
                    $content->contentInfo->alwaysAvailable
                );
            }

            $this->persistenceHandler->urlAliasHandler()->locationCopied(
                $loadedSubtree->id,
                $newLocation->id,
                $loadedTargetLocation->id
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->domainMapper->buildLocationWithContent($newLocation, $content);
    }

    /**
     * {@inheritdoc}
     */
    public function loadLocation($locationId, array $prioritizedLanguages = null)
    {
        $spiLocation = $this->persistenceHandler->locationHandler()->load($locationId);
        $location = $this->domainMapper->buildLocation($spiLocation, $prioritizedLanguages ?: []);
        if (!$this->repository->canUser('content', 'read', $location->getContentInfo(), $location)) {
            throw new UnauthorizedException('content', 'read');
        }

        return $location;
    }

    /**
     * {@inheritdoc}
     */
    public function loadLocationByRemoteId($remoteId, array $prioritizedLanguages = null)
    {
        if (!is_string($remoteId)) {
            throw new InvalidArgumentValue('remoteId', $remoteId);
        }

        $spiLocation = $this->persistenceHandler->locationHandler()->loadByRemoteId($remoteId);
        $location = $this->domainMapper->buildLocation($spiLocation, $prioritizedLanguages ?: []);
        if (!$this->repository->canUser('content', 'read', $location->getContentInfo(), $location)) {
            throw new UnauthorizedException('content', 'read');
        }

        return $location;
    }

    /**
     * {@inheritdoc}
     */
    public function loadLocations(ContentInfo $contentInfo, APILocation $rootLocation = null, array $prioritizedLanguages = null)
    {
        if (!$contentInfo->published) {
            throw new BadStateException('$contentInfo', 'ContentInfo has no published versions');
        }

        $spiLocations = $this->persistenceHandler->locationHandler()->loadLocationsByContent(
            $contentInfo->id,
            $rootLocation !== null ? $rootLocation->id : null
        );

        $locations = [];
        $spiInfo = $this->persistenceHandler->contentHandler()->loadContentInfo($contentInfo->id);
        $content = $this->domainMapper->buildContentProxy($spiInfo, $prioritizedLanguages ?: []);
        foreach ($spiLocations as $spiLocation) {
            $location = $this->domainMapper->buildLocationWithContent($spiLocation, $content, $spiInfo);
            if ($this->repository->canUser('content', 'read', $location->getContentInfo(), $location)) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * {@inheritdoc}
     */
    public function loadLocationChildren(APILocation $location, $offset = 0, $limit = 25, array $prioritizedLanguages = null)
    {
        if (!$this->domainMapper->isValidLocationSortField($location->sortField)) {
            throw new InvalidArgumentValue('sortField', $location->sortField, 'Location');
        }

        if (!$this->domainMapper->isValidLocationSortOrder($location->sortOrder)) {
            throw new InvalidArgumentValue('sortOrder', $location->sortOrder, 'Location');
        }

        if (!is_int($offset)) {
            throw new InvalidArgumentValue('offset', $offset);
        }

        if (!is_int($limit)) {
            throw new InvalidArgumentValue('limit', $limit);
        }

        $childLocations = array();
        $searchResult = $this->searchChildrenLocations($location, $offset, $limit, $prioritizedLanguages ?: []);
        foreach ($searchResult->searchHits as $searchHit) {
            $childLocations[] = $searchHit->valueObject;
        }

        return new LocationList(
            array(
                'locations' => $childLocations,
                'totalCount' => $searchResult->totalCount,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function loadParentLocationsForDraftContent(VersionInfo $versionInfo, array $prioritizedLanguages = null)
    {
        if (!$versionInfo->isDraft()) {
            throw new BadStateException(
                '$contentInfo',
                sprintf(
                    'Content [%d] %s has been already published. Use LocationService::loadLocations instead.',
                    $versionInfo->contentInfo->id,
                    $versionInfo->contentInfo->name
                )
            );
        }

        $spiLocations = $this->persistenceHandler
            ->locationHandler()
            ->loadParentLocationsForDraftContent($versionInfo->contentInfo->id);

        $contentIds = [];
        foreach ($spiLocations as $spiLocation) {
            $contentIds[] = $spiLocation->contentId;
        }

        $locations = [];
        $permissionResolver = $this->repository->getPermissionResolver();
        $spiContentInfoList = $this->persistenceHandler->contentHandler()->loadContentInfoList($contentIds);
        $contentList = $this->domainMapper->buildContentProxyList($spiContentInfoList, $prioritizedLanguages ?: []);
        foreach ($spiLocations as $spiLocation) {
            $location = $this->domainMapper->buildLocationWithContent(
                $spiLocation,
                $contentList[$spiLocation->contentId],
                $spiContentInfoList[$spiLocation->contentId]
            );

            if ($permissionResolver->canUser('content', 'read', $location->getContentInfo(), [$location])) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * Load all Locations.
     *
     * @param int $limit paginator limit (-1 to disable)
     * @param int $offset paginator offset
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    public function loadAllLocations(int $limit, int $offset = 0): array
    {
        $spiLocations = $this
            ->persistenceHandler
            ->locationHandler()->loadAllLocations($limit, $offset);

        $locations = [];
        foreach ($spiLocations as $spiLocation) {
            $locations[] = $this->domainMapper->buildLocation($spiLocation);
        }

        return $locations;
    }

    /**
     * Returns the number of children which are readable by the current user of a location object.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     *
     * @return int
     */
    public function getLocationChildCount(APILocation $location)
    {
        $searchResult = $this->searchChildrenLocations($location, 0, 0);

        return $searchResult->totalCount;
    }

    /**
     * Searches children locations of the provided parent location id.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @param int $offset
     * @param int $limit
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Search\SearchResult
     */
    protected function searchChildrenLocations(APILocation $location, $offset = 0, $limit = -1, array $prioritizedLanguages = null)
    {
        $query = new LocationQuery([
            'filter' => new Criterion\ParentLocationId($location->id),
            'offset' => $offset >= 0 ? (int)$offset : 0,
            'limit' => $limit >= 0 ? (int)$limit : null,
            'sortClauses' => $location->getSortClauses(),
        ]);

        return $this->repository->getSearchService()->findLocations($query, ['languages' => $prioritizedLanguages]);
    }

    /**
     * Creates the new $location in the content repository for the given content.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to create this location
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the content is already below the specified parent
     *                                        or the parent is a sub location of the location of the content
     *                                        or if set the remoteId exists already
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param \eZ\Publish\API\Repository\Values\Content\LocationCreateStruct $locationCreateStruct
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location the newly created Location
     */
    public function createLocation(ContentInfo $contentInfo, LocationCreateStruct $locationCreateStruct)
    {
        $content = $this->repository->getContentService()->loadContent($contentInfo->id);
        $parentLocation = $this->loadLocation($locationCreateStruct->parentLocationId);

        if (!$this->repository->canUser('content', 'manage_locations', $content->contentInfo)) {
            throw new UnauthorizedException('content', 'manage_locations');
        }

        if (!$this->repository->canUser('content', 'create', $content->contentInfo, $parentLocation)) {
            throw new UnauthorizedException('content', 'create');
        }

        // Check if the parent is a sub location of one of the existing content locations (this also solves the
        // situation where parent location actually one of the content locations),
        // or if the content already has location below given location create struct parent
        $existingContentLocations = $this->loadLocations($content->contentInfo);
        if (!empty($existingContentLocations)) {
            foreach ($existingContentLocations as $existingContentLocation) {
                if (stripos($parentLocation->pathString, $existingContentLocation->pathString) !== false) {
                    throw new InvalidArgumentException(
                        '$locationCreateStruct',
                        'Specified parent is a sub location of one of the existing content locations.'
                    );
                }
                if ($parentLocation->id == $existingContentLocation->parentLocationId) {
                    throw new InvalidArgumentException(
                        '$locationCreateStruct',
                        'Content is already below the specified parent.'
                    );
                }
            }
        }

        $spiLocationCreateStruct = $this->domainMapper->buildSPILocationCreateStruct(
            $locationCreateStruct,
            $parentLocation,
            $content->contentInfo->mainLocationId !== null ? $content->contentInfo->mainLocationId : true,
            $content->contentInfo->id,
            $content->contentInfo->currentVersionNo
        );

        $this->repository->beginTransaction();
        try {
            $newLocation = $this->persistenceHandler->locationHandler()->create($spiLocationCreateStruct);

            $urlAliasNames = $this->nameSchemaService->resolveUrlAliasSchema($content);
            foreach ($urlAliasNames as $languageCode => $name) {
                $this->persistenceHandler->urlAliasHandler()->publishUrlAliasForLocation(
                    $newLocation->id,
                    $newLocation->parentId,
                    $name,
                    $languageCode,
                    $content->contentInfo->alwaysAvailable,
                    // @todo: this is legacy storage specific for updating ezcontentobject_tree.path_identification_string, to be removed
                    $languageCode === $content->contentInfo->mainLanguageCode
                );
            }

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->domainMapper->buildLocationWithContent($newLocation, $content);
    }

    /**
     * Updates $location in the content repository.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to update this location
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException   if if set the remoteId exists already
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @param \eZ\Publish\API\Repository\Values\Content\LocationUpdateStruct $locationUpdateStruct
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location the updated Location
     */
    public function updateLocation(APILocation $location, LocationUpdateStruct $locationUpdateStruct)
    {
        if (!$this->domainMapper->isValidLocationPriority($locationUpdateStruct->priority)) {
            throw new InvalidArgumentValue('priority', $locationUpdateStruct->priority, 'LocationUpdateStruct');
        }

        if ($locationUpdateStruct->remoteId !== null && (!is_string($locationUpdateStruct->remoteId) || empty($locationUpdateStruct->remoteId))) {
            throw new InvalidArgumentValue('remoteId', $locationUpdateStruct->remoteId, 'LocationUpdateStruct');
        }

        if ($locationUpdateStruct->sortField !== null && !$this->domainMapper->isValidLocationSortField($locationUpdateStruct->sortField)) {
            throw new InvalidArgumentValue('sortField', $locationUpdateStruct->sortField, 'LocationUpdateStruct');
        }

        if ($locationUpdateStruct->sortOrder !== null && !$this->domainMapper->isValidLocationSortOrder($locationUpdateStruct->sortOrder)) {
            throw new InvalidArgumentValue('sortOrder', $locationUpdateStruct->sortOrder, 'LocationUpdateStruct');
        }

        $loadedLocation = $this->loadLocation($location->id);

        if ($locationUpdateStruct->remoteId !== null) {
            try {
                $existingLocation = $this->loadLocationByRemoteId($locationUpdateStruct->remoteId);
                if ($existingLocation !== null && $existingLocation->id !== $loadedLocation->id) {
                    throw new InvalidArgumentException('locationUpdateStruct', 'location with provided remote ID already exists');
                }
            } catch (APINotFoundException $e) {
            }
        }

        if (!$this->repository->canUser('content', 'edit', $loadedLocation->getContentInfo(), $loadedLocation)) {
            throw new UnauthorizedException('content', 'edit');
        }

        $updateStruct = new UpdateStruct();
        $updateStruct->priority = $locationUpdateStruct->priority !== null ? $locationUpdateStruct->priority : $loadedLocation->priority;
        $updateStruct->remoteId = $locationUpdateStruct->remoteId !== null ? trim($locationUpdateStruct->remoteId) : $loadedLocation->remoteId;
        $updateStruct->sortField = $locationUpdateStruct->sortField !== null ? $locationUpdateStruct->sortField : $loadedLocation->sortField;
        $updateStruct->sortOrder = $locationUpdateStruct->sortOrder !== null ? $locationUpdateStruct->sortOrder : $loadedLocation->sortOrder;

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->update($updateStruct, $loadedLocation->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->loadLocation($loadedLocation->id);
    }

    /**
     * Swaps the contents held by $location1 and $location2.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to swap content
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location1
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location2
     */
    public function swapLocation(APILocation $location1, APILocation $location2)
    {
        $loadedLocation1 = $this->loadLocation($location1->id);
        $loadedLocation2 = $this->loadLocation($location2->id);

        if (!$this->repository->canUser('content', 'edit', $loadedLocation1->getContentInfo(), $loadedLocation1)) {
            throw new UnauthorizedException('content', 'edit');
        }
        if (!$this->repository->canUser('content', 'edit', $loadedLocation2->getContentInfo(), $loadedLocation2)) {
            throw new UnauthorizedException('content', 'edit');
        }

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->swap($loadedLocation1->id, $loadedLocation2->id);
            $this->persistenceHandler->urlAliasHandler()->locationSwapped(
                $location1->id,
                $location1->parentLocationId,
                $location2->id,
                $location2->parentLocationId
            );
            $this->persistenceHandler->bookmarkHandler()->locationSwapped($loadedLocation1->id, $loadedLocation2->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Hides the $location and marks invisible all descendants of $location.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to hide this location
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location $location, with updated hidden value
     */
    public function hideLocation(APILocation $location)
    {
        if (!$this->repository->canUser('content', 'hide', $location->getContentInfo(), $location)) {
            throw new UnauthorizedException('content', 'hide');
        }

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->hide($location->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->loadLocation($location->id);
    }

    /**
     * Unhides the $location.
     *
     * This method and marks visible all descendants of $locations
     * until a hidden location is found.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to unhide this location
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location $location, with updated hidden value
     */
    public function unhideLocation(APILocation $location)
    {
        if (!$this->repository->canUser('content', 'hide', $location->getContentInfo(), $location)) {
            throw new UnauthorizedException('content', 'hide');
        }

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->unHide($location->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->loadLocation($location->id);
    }

    /**
     * Moves the subtree to $newParentLocation.
     *
     * If a user has the permission to move the location to a target location
     * he can do it regardless of an existing descendant on which the user has no permission.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user is not allowed to move this location to the target
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user user does not have read access to the whole source subtree
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException If the new parent is in a subtree of the location
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @param \eZ\Publish\API\Repository\Values\Content\Location $newParentLocation
     */
    public function moveSubtree(APILocation $location, APILocation $newParentLocation)
    {
        $location = $this->loadLocation($location->id);
        $newParentLocation = $this->loadLocation($newParentLocation->id);

        // check create permission on target location
        if (!$this->repository->canUser('content', 'create', $location->getContentInfo(), $newParentLocation)) {
            throw new UnauthorizedException('content', 'create');
        }

        /** Check read access to whole source subtree
         * @var bool|\eZ\Publish\API\Repository\Values\Content\Query\Criterion
         */
        $contentReadCriterion = $this->permissionCriterionResolver->getPermissionsCriterion();
        if ($contentReadCriterion === false) {
            throw new UnauthorizedException('content', 'read');
        } elseif ($contentReadCriterion !== true) {
            // Query if there are any content in subtree current user don't have access to
            $query = new Query(
                array(
                    'limit' => 0,
                    'filter' => new CriterionLogicalAnd(
                        array(
                            new CriterionSubtree($location->pathString),
                            new CriterionLogicalNot($contentReadCriterion),
                        )
                    ),
                )
            );
            $result = $this->repository->getSearchService()->findContent($query, array(), false);
            if ($result->totalCount > 0) {
                throw new UnauthorizedException('content', 'read');
            }
        }

        if (strpos($newParentLocation->pathString, $location->pathString) === 0) {
            throw new InvalidArgumentException(
                '$newParentLocation',
                'new parent location is in a subtree of the given $location'
            );
        }

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->move($location->id, $newParentLocation->id);

            $content = $this->repository->getContentService()->loadContent($location->contentId);
            $urlAliasNames = $this->nameSchemaService->resolveUrlAliasSchema($content);
            foreach ($urlAliasNames as $languageCode => $name) {
                $this->persistenceHandler->urlAliasHandler()->publishUrlAliasForLocation(
                    $location->id,
                    $newParentLocation->id,
                    $name,
                    $languageCode,
                    $content->contentInfo->alwaysAvailable
                );
            }

            $this->persistenceHandler->urlAliasHandler()->locationMoved(
                $location->id,
                $location->parentLocationId,
                $newParentLocation->id
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Deletes $location and all its descendants.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException If the current user is not allowed to delete this location or a descendant
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     */
    public function deleteLocation(APILocation $location)
    {
        $location = $this->loadLocation($location->id);

        if (!$this->repository->canUser('content', 'manage_locations', $location->getContentInfo())) {
            throw new UnauthorizedException('content', 'manage_locations');
        }
        if (!$this->repository->canUser('content', 'remove', $location->getContentInfo(), $location)) {
            throw new UnauthorizedException('content', 'remove');
        }

        /** Check remove access to descendants
         * @var bool|\eZ\Publish\API\Repository\Values\Content\Query\Criterion
         */
        $contentReadCriterion = $this->permissionCriterionResolver->getPermissionsCriterion('content', 'remove');
        if ($contentReadCriterion === false) {
            throw new UnauthorizedException('content', 'remove');
        } elseif ($contentReadCriterion !== true) {
            // Query if there are any content in subtree current user don't have access to
            $query = new Query(
                array(
                    'limit' => 0,
                    'filter' => new CriterionLogicalAnd(
                        array(
                            new CriterionSubtree($location->pathString),
                            new CriterionLogicalNot($contentReadCriterion),
                        )
                    ),
                )
            );
            $result = $this->repository->getSearchService()->findContent($query, array(), false);
            if ($result->totalCount > 0) {
                throw new UnauthorizedException('content', 'remove');
            }
        }

        $this->repository->beginTransaction();
        try {
            $this->persistenceHandler->locationHandler()->removeSubtree($location->id);
            $this->persistenceHandler->urlAliasHandler()->locationDeleted($location->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Instantiates a new location create class.
     *
     * @param mixed $parentLocationId the parent under which the new location should be created
     *
     * @return \eZ\Publish\API\Repository\Values\Content\LocationCreateStruct
     */
    public function newLocationCreateStruct($parentLocationId)
    {
        return new LocationCreateStruct(
            array(
                'parentLocationId' => $parentLocationId,
            )
        );
    }

    /**
     * Instantiates a new location update class.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\LocationUpdateStruct
     */
    public function newLocationUpdateStruct()
    {
        return new LocationUpdateStruct();
    }
}
