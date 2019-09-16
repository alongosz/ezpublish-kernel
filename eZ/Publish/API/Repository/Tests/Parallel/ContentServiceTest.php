<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Tests\Parallel;

use eZ\Publish\API\Repository\Exceptions\BadStateException;

class ContentServiceTest extends BaseParallelTestCase
{
    /**
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
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

        $processList = new ParallelProcessList();
        $this->addParallelProcess($processList, function () use ($version1 , $contentService) {
            $contentService->publishVersion($version1->versionInfo);
        });

        $this->addParallelProcess($processList, function () use ($version2 , $contentService) {
            $this->expectException(BadStateException::class);
            $this->expectExceptionMessage('Someone just published another Version of the Content item');
            $contentService->publishVersion($version2->versionInfo);
        });

        $this->runParallelProcesses($processList);
    }
}
