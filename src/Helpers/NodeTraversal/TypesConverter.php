<?php

namespace App\Helpers\NodeTraversal;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypesConverter extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Property
            && count($node->props) > 0
            && $node->props[0] instanceof Node\Stmt\PropertyProperty) {
            return $node;
        }
    }

}