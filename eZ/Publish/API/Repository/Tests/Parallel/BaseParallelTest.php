<?php

/**
 * File containing the BaseParallelTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\API\Repository\Tests\Parallel;

use eZ\Publish\API\Repository\Tests\BaseTest;
use Jenner\SimpleFork\Process;

class BaseParallelTest extends BaseTest
{
    /** @var Jenner\SimpleFork\Process[] */
    private $pool = [];

    protected function addParallelProccess(callable $callback): void
    {
        $connection = $this->getRawDatabaseConnection();

        $this->pool[] = new Process(function () use ($callback, $connection) {
            $connection->connect();
            $callback();
            $connection->close();
        });
    }

    private function checkEnviroment(): void
    {
        $connection = $this->getRawDatabaseConnection();
        $driver = $connection->getParams()['driver'];

        if (!in_array($driver, ['pdo_mysql', 'pdo_pgsql'])) {
            $this->markTestSkipped('Parallel test require mysql or pgsql db');
        }
    }

    protected function runParalleledProcesses(): void
    {
        $this->checkEnviroment();

        $connection = $this->getRawDatabaseConnection();
        $connection->close();

        foreach ($this->pool as $process) {
            $process->start();
        }

        foreach ($this->pool as $process) {
            $process->wait();
        }

        $connection->connect();
        $this->pool = [];
    }
}
