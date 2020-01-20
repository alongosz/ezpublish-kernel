<?php

namespace Utils\Rector;

use Rector\Rector\AbstractRector as AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

abstract class BaseDatabaseHandlerRector extends AbstractRector
{
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Change usages of DatabaseHandler to Doctrine\DBAL', [
                new CodeSample(
                    '$dbHandler->createSelectQuery();',
                    '$connection->createQueryBuiler("123456");'
                ),
            ]
        );
    }
}
