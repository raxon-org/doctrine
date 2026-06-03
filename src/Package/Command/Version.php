<?php
namespace Package\Raxon\Doctrine\Command;

class Version extends \Symfony\Component\Console\Command\Command {

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }
}
