<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use PhpParser\Node;
use PhpParser\Node\Expr;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Utils\Rector\BaseDatabaseHandlerRector;

class SimpleDbhWithConnectionReplacementForMethodCallRector extends BaseDatabaseHandlerRector
{
    public const USE_CONNECTION_FOR = ['lastInsertId', 'getSequenceName'];

    public function getNodeTypes(): array
    {
        return [Expr\MethodCall::class];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall
     */
    public function refactor(Node $node): ?Node
    {
        if (!in_array($this->getName($node->name), self::USE_CONNECTION_FOR, true)) {
            return null;
        }

        $callingObjectNode = $node->var->getAttribute(AttributeKey::PARENT_NODE)->var;
        $callingObjectVariableName = $this->getName($callingObjectNode);
        if ($callingObjectVariableName === 'dbHandler') {
            $callingObjectNode->name = new Node\Identifier('connection');
        }

        return $node;
    }
}
