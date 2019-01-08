<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\Core\Repository\SiteAccessAware\Tests\Language;

use eZ\Publish\API\Repository\Values\Content\Language;
use eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver;
use PHPUnit\Framework\TestCase;

/**
 * @see \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver
 */
class LanguageResolverTest extends TestCase
{
    /**
     * @var \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver
     */
    private $resolver;

    /**
     * @var array
     */
    private $configLanguages = ['eng-GB', 'nor-NO'];

    public function setUp()
    {
        $this->resolver = new LanguageResolver($this->configLanguages);
    }

    /**
     * Data provider for testGetPrioritizedLanguages method.
     */
    public function providerForTestGetPrioritizedLanguages(): array
    {
        // ?array $forcedLanguages, ?string $contextLanguage, array $expectedPrioritizedLanguagesList
        return [
            [
                // test that forcing empty array will return that array instead of config languages
                [],
                null,
                [],
            ],
            [
                // test that by default config languages are returned
                null,
                null,
                $this->configLanguages,
            ],
            [
                // test that forced languages take priority over context and config
                ['ger-DE', 'pol-PL'],
                'pol-PL',
                ['ger-DE', 'pol-PL'],
            ],
            [
                // test that context language takes priority over config
                null,
                'pol-PL',
                ['pol-PL', 'eng-GB', 'nor-NO'],
            ],
            [
                null,
                'nor-NO',
                ['nor-NO', 'eng-GB'],
            ],
            [
                null,
                'eng-GB',
                ['eng-GB', 'nor-NO'],
            ],
        ];
    }

    /**
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::getPrioritizedLanguages
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::setContextLanguage
     *
     * @dataProvider providerForTestGetPrioritizedLanguages
     *
     * @param array|null $forcedLanguages
     * @param string|null $contextLanguage
     * @param array $expectedPrioritizedLanguagesList
     */
    public function testGetPrioritizedLanguages(
        ?array $forcedLanguages,
        ?string $contextLanguage,
        array $expectedPrioritizedLanguagesList
    ) {
        $this->resolver->setContextLanguage($contextLanguage);

        self::assertSame(
            $expectedPrioritizedLanguagesList,
            $this->resolver->getPrioritizedLanguages($forcedLanguages)
        );
    }

    /**
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::getPrioritizedLanguages
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::setConfigLanguages
     */
    public function testSetConfigLanguages()
    {
        // sanity check
        self::assertEquals(
            $this->configLanguages,
            $this->resolver->getPrioritizedLanguages(null)
        );

        $newConfigLanguages = ['nor-NO', 'pol-PL'];
        $this->resolver->setConfigLanguages($newConfigLanguages);

        self::assertEquals(
            $newConfigLanguages,
            $this->resolver->getPrioritizedLanguages(null)
        );
    }

    /**
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::getPrioritizedLanguages
     * @covers \eZ\Publish\Core\Repository\SiteAccessAware\Language\LanguageResolver::setShowAllTranslations
     */
    public function testSetShowAllTranslations()
    {
        // sanity check
        self::assertEquals(
            $this->configLanguages,
            $this->resolver->getPrioritizedLanguages(null)
        );

        $this->resolver->setShowAllTranslations(true);

        self::assertSame(
            Language::ALL,
            $this->resolver->getPrioritizedLanguages(null)
        );
    }
}
