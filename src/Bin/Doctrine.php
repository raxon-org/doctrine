<?php
/**
 * @author          Remco van der Velde
 * @since           2022-07-31
 * @version         1.0
 * @license         MIT
 * @changeLog
 *     -    all
 */

use Raxon\App;
use Raxon\Config;
use Raxon\Doctrine\Module\Database;

use Doctrine\ORM\Version;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Symfony\Component\Console\Helper\HelperSet;

use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;

$dir = dirname(__DIR__);
$dir_vendor =
    $dir .
    DIRECTORY_SEPARATOR .
    'vendor' .
    DIRECTORY_SEPARATOR;

$autoload = $dir_vendor . 'autoload.php';
$autoload = require $autoload;

try {
    $config = new Config(
        [
            'dir.vendor' => $dir_vendor,
            'silence' => true
        ]
    );
    $app = new App($autoload, $config);
    echo App::run($app);
    $options = App::options($app);
    if(!property_exists($options, 'connection')) {
        $options->connection = 'system';
    }
    $config = Database::config($app);
    $environments = $app->config('doctrine.environment');
    $framework_environment = $app->config('framework.environment');
    $connection = false;
    foreach($environments as $name => $list){
        if($name === $options->connection){
            foreach($list as $environment => $connection){
                if($environment === $framework_environment){
                    break 2;
                }
                elseif($environment === '*'){
                    break 2;
                }
            }
        }
    }
    if($connection){
        $connection->manager = Database::entity_manager($app, $config, $connection);
    } else {

    }
} catch (Exception | LocateException | ObjectException $exception) {
    echo $exception;
}
if(empty($connection->manager)){
   echo  Cli::error('error:'). ' No connection found...';
}

$helperSet = new HelperSet([
    'db' => new DBALConsole\Helper\ConnectionHelper($connection->manager),
]);

$cli = ConsoleRunner::createApplication($helperSet);
// Runs console application
$cli->setCatchExceptions(true);
$cli->addCommands([
    (new Package\Raxon\Doctrine\Command\Version())
]);
$cli->run();
//$cli->setHelperSet($helperSet);
//$cli = new Application('Doctrine Command Line Interface', Version::VERSION);
/*
ConsoleRunner::run(
    new SingleManagerProvider($connection->manager),
    $commands
);
*/
