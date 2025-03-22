<?php
namespace Raxon\Doctrine\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]

class Node
{
    public function __construct(
        public string $class,
        public array $filter=[],
        public array $where=[],
        public array $sort=[],
        public bool $relation=false,
    ) {

    }
}
