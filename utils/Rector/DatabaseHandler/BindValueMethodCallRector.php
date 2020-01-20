<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Utils\Rector\DatabaseHandler;

use Doctrine\DBAL\ParameterType;
use PhpParser\Node;
use PhpParser\Node\Expr;
use RuntimeException;
use Utils\Rector\BaseDatabaseHandlerRector;

final class BindValueMethodCallRector extends BaseDatabaseHandlerRector
{
    public const PARAM_TYPE_MAP = [
        'PARAM_INT' => 'INTEGER',
        'PARAM_BOOL' => 'BOOLEAN',
        'PARAM_STR' => 'STRING',
    ];

    public function getNodeTypes(): array
    {
        return [Expr\MethodCall::class];
    }

    /**
     * @param \PhpParser\Node\Expr\MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'bindValue')) {
            return null;
        }

        $parameterType = $node->args[2] ?? null;

        $node->name = new Node\Identifier('createPositionalParameter');
        $node->args = [$node->args[0]];

        if (null !== $parameterType) {
            $parameterType->value->name = new Node\Identifier(
                $this->mapType($parameterType->value->name)
            );
            $parameterType->value->class->parts[0] = ParameterType::class;
            $node->args[] = $parameterType;
        }

        return $node;
    }

    private function mapType(Node\Identifier $name): string
    {
        if (!isset(self::PARAM_TYPE_MAP[$name->name])) {
            throw new RuntimeException("Missing PARAM_TYPE_MAP[$name->name]");
        }

        return self::PARAM_TYPE_MAP[$name->name];
    }
}
