<?php

namespace Package\Raxon\Doctrine\Controller;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;

use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Doctrine\DBAL\Logging;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManager;

use Doctrine\ORM\ORMSetup;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\Database;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;

use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\UrlEmptyException;
use Raxon\Exception\UrlNotExistException;

class Index extends Controller {


    public static function run(App $object){
        ddd($object->config());


        $paths = $object->config('doctrine.paths');
        $paths = Config::parameters($object, $paths);
        $parameters = [];
        $parameters[] = $object->config('doctrine.proxy.dir');
        $parameters = Config::parameters($object, $parameters);
        if(array_key_exists(0, $parameters)){
            $proxyDir = $parameters[0];
        }
        $cache = null;
        $config = ORMSetup::createAnnotationMetadataConfiguration($paths, false, $proxyDir, $cache);

        if(!empty($connection['logging'])){
            $logger = new Logger(Database::LOGGER_DOCTRINE);
            $logger->pushHandler(new StreamHandler($object->config('project.dir.log') . 'sql.log', Logger::DEBUG));
            $logger->pushProcessor(new PsrLogMessageProcessor(null, true));
            $object->logger($logger->getName(), $logger);
            $logger->info('Logger initialised.');
            $config->setMiddlewares([new Logging\Middleware($logger)]);
        }
        $connection = DriverManager::getConnection($connection, $config, new EventManager());
        $em = EntityManager::create($connection, $config);
        $object->set(Database::NAME .'.entityManager.' . $name . '.' . $environment, $em);
        return $em;
    }
}



