<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Visitor;

use PhpParser\Node;

trait RewritePropertyHelperTrait
{
    protected function getInjectPropertyType(Node\Name $type): ?string
    {
        if (\in_array($type->toString(), $this->baseType(), true))
        {
            return $type->toString();
        }

        return match (true)
        {
            $type->isRelative(), $type->isQualified(), $type->isUnqualified() => $this->namespace
                ? ($this->namespace->name->toString() . '\\' . $type->toString())
                : $type->toString(),
            default => $type->toString(),
        };
    }

    protected function guessName(string $name): Node\Name
    {
        if (str_contains($name, '\\'))
        {
            $name = ltrim($name, '\\');

            return new Node\Name\FullyQualified($name);
        }
        else
        {
            return new Node\Name($name);
        }
    }

    protected function guessClassPropertyType(Node\Stmt\Property $node, \ReflectionProperty $property): array
    {
        $fromComment = false;
        if ($node->type)
        {
            return [$node->type, $fromComment];
        }
        if ($type = $this->readTypeFromPropertyComment($property))
        {
            $fromComment = true;
            if (str_ends_with($type, '[]'))
            {
                return [new Node\Name('array'), $fromComment];
            }
            if ('callable' !== $type)
            {
                return [$this->guessName($type), $fromComment];
            }
        }

        return [null, false];
    }

    protected function readTypeFromPropertyComment(\ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if (!$docComment)
        {
            return null;
        }
        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches))
        {
            [, $type] = $matches;
        }
        else
        {
            return null;
        }

        return $type;
    }
}
