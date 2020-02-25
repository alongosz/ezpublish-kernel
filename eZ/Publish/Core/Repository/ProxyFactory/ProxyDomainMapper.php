<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\Core\Repository\ProxyFactory;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\LanguageService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SectionService;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Language;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\Section;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use eZ\Publish\API\Repository\Values\User\User;
use ProxyManager\Proxy\LazyLoadingInterface;

/**
 * @internal
 */
final class ProxyDomainMapper implements ProxyDomainMapperInterface
{
    /** @var \ProxyManager\Factory\LazyLoadingValueHolderFactory */
    private $proxyGenerator;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\ContentTypeService */
    private $contentTypeService;

    /** @var \eZ\Publish\API\Repository\LocationService */
    private $locationService;

    /** @var \eZ\Publish\API\Repository\SectionService */
    private $sectionService;

    /** @var \eZ\Publish\API\Repository\UserService */
    private $userService;
    private $languageService;

    public function __construct(
        ProxyGeneratorInterface $proxyGenerator,
        ContentService $contentService,
        ContentTypeService $contentTypeService,
        LanguageService $languageService,
        LocationService $locationService,
        SectionService $sectionService,
        UserService $userService
    ) {
        $this->proxyGenerator = $proxyGenerator;
        $this->contentService = $contentService;
        $this->contentTypeService = $contentTypeService;
        $this->languageService = $languageService;
        $this->locationService = $locationService;
        $this->sectionService = $sectionService;
        $this->userService = $userService;
    }

    public function createContentProxy(
        int $contentId,
        array $prioritizedLanguages = Language::ALL,
        bool $useAlwaysAvailable = true
    ): Content {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($contentId, $prioritizedLanguages, $useAlwaysAvailable): bool {
            $initializer = null;
            $wrappedObject = $this->contentService->loadContent(
                $contentId,
                $prioritizedLanguages,
                null,
                $useAlwaysAvailable
            );

            return true;
        };

        return $this->proxyGenerator->createProxy(Content::class, $initializer);
    }

    public function createContentInfoProxy(int $contentId): ContentInfo
    {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($contentId): bool {
            $initializer = null;
            $wrappedObject = $this->contentService->loadContentInfo(
                $contentId
            );

            return true;
        };

        return $this->proxyGenerator->createProxy(ContentInfo::class, $initializer);
    }

    public function createContentTypeProxy(
        int $contentTypeId,
        array $prioritizedLanguages = Language::ALL
    ): ContentType {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($contentTypeId, $prioritizedLanguages): bool {
            $initializer = null;
            $wrappedObject = $this->contentTypeService->loadContentType(
                $contentTypeId,
                $prioritizedLanguages
            );

            return true;
        };

        return $this->proxyGenerator->createProxy(ContentType::class, $initializer);
    }

    public function createContentTypeGroupProxy(
        int $contentTypeGroupId,
        array $prioritizedLanguages = Language::ALL
    ): ContentTypeGroup {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($contentTypeGroupId, $prioritizedLanguages): bool {
            $initializer = null;
            $wrappedObject = $this->contentTypeService->loadContentTypeGroup(
                $contentTypeGroupId,
                $prioritizedLanguages
            );

            return true;
        };

        return $this->proxyGenerator->createProxy(ContentTypeGroup::class, $initializer);
    }

    public function createContentTypeGroupProxyList(
        array $contentTypeGroupIds,
        array $prioritizedLanguages = Language::ALL
    ): array {
        $groups = [];
        foreach ($contentTypeGroupIds as $contentTypeGroupId) {
            $groups[] = $this->createContentTypeGroupProxy($contentTypeGroupId, $prioritizedLanguages);
        }

        return $groups;
    }

    public function createLanguageProxy(string $languageCode): Language
    {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($languageCode): bool {
            $initializer = null;
            $wrappedObject = $this->languageService->loadLanguage($languageCode);

            return true;
        };

        return $this->proxyGenerator->createProxy(Language::class, $initializer);
    }

    public function createLanguageProxyList(array $languageCodes): array
    {
        $languages = [];
        foreach ($languageCodes as $languageCode) {
            $languages[] = $this->createLanguageProxy($languageCode);
        }

        return $languages;
    }

    public function createLocationProxy(
        int $locationId,
        array $prioritizedLanguages = Language::ALL
    ): Location {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($locationId, $prioritizedLanguages): bool {
            $initializer = null;
            $wrappedObject = $this->locationService->loadLocation(
                $locationId,
                $prioritizedLanguages
            );

            return true;
        };

        return $this->proxyGenerator->createProxy(Location::class, $initializer);
    }

    public function createSectionProxy(int $sectionId): Section
    {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($sectionId): bool {
            $initializer = null;
            $wrappedObject = $this->sectionService->loadSection($sectionId);

            return true;
        };

        return $this->proxyGenerator->createProxy(Section::class, $initializer);
    }

    public function createUserProxy(int $userId, array $prioritizedLanguages = Language::ALL): User
    {
        $initializer = function (
            &$wrappedObject, LazyLoadingInterface $proxy, $method, array $parameters, &$initializer
        ) use ($userId, $prioritizedLanguages): bool {
            $initializer = null;
            $wrappedObject = $this->userService->loadUser($userId, $prioritizedLanguages);

            return true;
        };

        return $this->proxyGenerator->createProxy(User::class, $initializer);
    }
}
