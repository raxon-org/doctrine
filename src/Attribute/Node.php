<?php
namespace Raxon\Doctrine\Attribute;

class Node
{
    public function __construct(
        public readonly string $class,
    ) {

    }
}
