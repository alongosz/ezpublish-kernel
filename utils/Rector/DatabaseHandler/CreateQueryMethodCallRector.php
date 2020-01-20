<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Utils\Rector\BaseDatabaseHandlerRector;

final class CreateQueryMethodCallRector extends BaseDatabaseHandlerRector
{
    public function getNodeTypes(): array
    {
        return [
            Expr\MethodCall::class,
        ];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'create*Query')) {
            return null;
        }

        $callingObjectNode = $node->var->getAttribute(AttributeKey::PARENT_NODE)->var;
        $callingObjectVariableName = $this->getName($callingObjectNode);
        if ($callingObjectVariableName === 'dbHandler') {
            $callingObjectNode->name = new Node\Identifier('connection');
        }

        $methodCallName = $this->getName($node);
        $node->name = new Node\Identifier(
            Strings::replace($methodCallName, '#^create.*Query#', 'createQueryBuilder')
        );

        return $node;
    }
}
