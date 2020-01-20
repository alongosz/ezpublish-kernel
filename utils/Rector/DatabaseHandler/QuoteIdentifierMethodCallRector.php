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

final class QuoteIdentifierMethodCallRector extends BaseDatabaseHandlerRector
{
    public function getNodeTypes(): array
    {
        return [Expr\MethodCall::class];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'quote*')) {
            return null;
        }

        $callingObjectNode = $node->var->getAttribute(AttributeKey::PARENT_NODE)->var;
        $callingObjectVariableName = $this->getName($callingObjectNode);
        if ($callingObjectVariableName !== 'dbHandler') {
            return null;
        }
        // remove "quote*" call, keeping just the argument

        // prepend table name if passed for quoteColumn
        $argumentName = $node->args[0]->value->value;
        if (count($node->args) === 2) {
            $argumentName = $node->args[1]->value->value . '.' . $argumentName;
        }
        $node->args[0]->value->value = $argumentName;

        return $node->args[0];
    }
}
