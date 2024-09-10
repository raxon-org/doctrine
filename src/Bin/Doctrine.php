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
use Raxon\Module\Database;

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;

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
    $commands = [];
    $entityManager = Database::entityManager($app, $options);
} catch (Exception | LocateException | ObjectException $exception) {
    echo $exception;
}
if(empty($entityManager)){
    return;
}
ConsoleRunner::run(
    new SingleManagerProvider($entityManager),
    $commands
);
