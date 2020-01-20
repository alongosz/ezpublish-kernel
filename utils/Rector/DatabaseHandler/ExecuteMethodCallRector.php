<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use Doctrine\DBAL\Driver\Statement;
use PhpParser\Node;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Utils\Rector\BaseDatabaseHandlerRector;

/**
 * Drop execute calls made to DBAL Statement which are result of PrepareMethodCallRector refactoring.
 */
final class ExecuteMethodCallRector extends BaseDatabaseHandlerRector
{
    public function getNodeTypes(): array
    {
        return [Node\Stmt\Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (
            !$node->expr instanceof Node\Expr\MethodCall
            || !$this->isName($node->expr->name, 'execute')
        ) {
            return null;
        }
        $invokingVariableName = $this->getName($node->expr->var);
        /** @var \PHPStan\Analyser\Scope $scope */
        $scope = $node->expr->getAttribute(AttributeKey::SCOPE);
        if (
            !$scope->hasVariableType($invokingVariableName)
            || !$this->isDBALStatement($scope->getVariableType($invokingVariableName))
        ) {
            return null;
        }

        // drop execute call
        return new Node\Stmt\Nop();
    }

    private function isDBALStatement(Type $variableType): bool
    {
        // all DBAL Driver statement classes are union types
        if (!$variableType instanceof UnionType) {
            return false;
        }

        foreach ($variableType->getTypes() as $type) {
            if ($type instanceof ObjectType && $type->getClassName() === Statement::class) {
                return true;
            }
        }

        return false;
    }
}
