<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use PhpParser\Node;
use PhpParser\Node\Expr;
use Utils\Rector\BaseDatabaseHandlerRector;

class SimpleReplacementMethodCallRector extends BaseDatabaseHandlerRector
{
    public const REPLACEMENT_MAP = [
        'insertInto' => 'insert',
        'deleteFrom' => 'delete',
        'lAnd' => 'andX',
    ];

    public function getNodeTypes(): array
    {
        return [Expr\MethodCall::class];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall
     */
    public function refactor(Node $node): ?Node
    {
        if (!array_key_exists($this->getName($node), self::REPLACEMENT_MAP)) {
            return null;
        }

        $node->name = new Node\Identifier(self::REPLACEMENT_MAP[$this->getName($node)]);

        return $node;
    }
}
