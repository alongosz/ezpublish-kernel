<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use PhpParser\Node;
use Utils\Rector\BaseDatabaseHandlerRector;

final class PrepareMethodCallRector extends BaseDatabaseHandlerRector
{
    public function getNodeTypes(): array
    {
        return [Node\Expr\MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'prepare')) {
            return null;
        }

        /** @var \PhpParser\Node\Expr\MethodCall $node */
        $node->name = new Node\Identifier('execute');

        return $node;
    }
}
