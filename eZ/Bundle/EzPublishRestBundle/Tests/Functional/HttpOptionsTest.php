<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Bundle\EzPublishRestBundle\Tests\Functional;

use Symfony\Component\Yaml\Yaml;

/**
 * Test sending OPTIONS header for REST routes.
 */
class HttpOptionsTest extends TestCase
{
    /**
     * Covers OPTIONS on selected routes.
     *
     * @dataProvider providerForTestHttpOptions
     *
     * @param string $route
     * @param string[] $expectedMethods
     */
    public function testHttpOptions(string $route, array $expectedMethods): void
    {
        $restAPIPrefix = '/api/ezp/v2';

        $response = $this->sendHttpRequest(
            $this->createHttpRequest('OPTIONS', "{$restAPIPrefix}{$route}")
        );

        self::assertHttpResponseCodeEquals($response, 200);
        self::assertEquals(0, (int)($response->getHeader('Content-Length')));

        self::assertHttpResponseHasHeader($response, 'Allow');
        $actualMethods = explode(',', $response->getHeader('Allow'));
        self::assertEquals($expectedMethods, $actualMethods);
    }

    /**
     * Data provider for testHttpOptions.
     *
     * @see testHttpOptions
     *
     * @return array Data Provider sets
     */
    public function providerForTestHttpOptions(): array
    {
        // initial predefined routes
        $data = [
            ['/', ['GET']],
            ['/content/locations/1/2', ['GET', 'PATCH', 'DELETE', 'COPY', 'MOVE', 'SWAP']],
        ];

        $routes = Yaml::parseFile(__DIR__ . '/../../Resources/config/routing.yml');
        foreach ($routes as $route) {
            // don't test methods with parameters (no reliable way to determine their values)
            if (isset($data[$route['path']])) {
                $data[$route['path']][1][] = $route['methods'];
            }
            $data[$route['path']] = $route['methods'];
        }

        foreach ($data as $route => $methods) {
            echo "['{$route}', [", implode(',', array_map(function ($x) { return "'{$x}'"; }, $methods)) ,']]', PHP_EOL;
        }

        exit;

        return array_values($data);
    }
}
