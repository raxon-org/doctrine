<?php
namespace Package\Raxon\Doctrine\Command;

use Composer\InstalledVersions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Version extends \Symfony\Component\Console\Command\Command {

    public function __construct(null|string $name=null)
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        echo 'symfony/console: ' . InstalledVersions::getVersion('symfony/console') . PHP_EOL;
        echo 'doctrine/orm: ' .InstalledVersions::getVersion('doctrine/orm') . PHP_EOL;
        echo 'doctrine/dbal: ' .InstalledVersions::getVersion('doctrine/dbal') . PHP_EOL;
        return 0;
    }
}
