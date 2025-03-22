<?php
namespace Raxon\Doctrine\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]

class Node
{
    public function __construct(
        public readonly string $class,
        public readonly array $filter=[],
        public readonly array $where=[],
        public readonly array $sort=[],
    ) {

    }
}
