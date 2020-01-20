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

final class ExprPropertyFetchRector extends BaseDatabaseHandlerRector
{
    public function getNodeTypes(): array
    {
        return [Expr\PropertyFetch::class];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'expr')) {
            return null;
        }

        $node->name = new Node\Identifier('expr()');

        return $node;
    }
}
