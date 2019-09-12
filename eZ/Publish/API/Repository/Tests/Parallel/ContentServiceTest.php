<?php

/**
 * File containing the BaseParallelTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\API\Repository\Tests\Parallel;

class ContentServiceTest extends BaseParallelTest
{
    public function testPublishMultipleVersions(): void
    {
        $repository = $this->getRepository();

        $contentService = $repository->getContentService();
        $content = $this->createFolder(
            [
                'eng-US' => 'Content',
            ],
            $this->generateId('location', 2)
        );

        $version1 = $contentService->createContentDraft($content->contentInfo, $content->versionInfo);
        $version2 = $contentService->createContentDraft($content->contentInfo, $content->versionInfo);

        $this->addParallelProccess(function () use ($version1 , $contentService) {
            $version = $contentService->publishVersion($version1->versionInfo);
        });

        $this->addParallelProccess(function () use ($version2 , $contentService) {
            $version = $contentService->publishVersion($version2->versionInfo);
        });

        $this->runParalleledProcesses();

        $version1 = $contentService->loadVersionInfo($version1->contentInfo, 2);
        $version2 = $contentService->loadVersionInfo($version1->contentInfo, 3);

        $this->assertTrue($version1->isArchived(), 'Version 1 should be archived');
        $this->assertTrue($version2->isPublished(), 'Version 2 should be published');
    }
}
