<?php
namespace Raxon\Doctrine\Module;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

use Raxon\App;
use Raxon\Config;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;

class Database {

    /**
     * @throws Exception
     */
    public static function config(App $object){
        $paths = $object->config('doctrine.paths');
        $paths = Config::parameters($object, $paths);
        $parameters = [];
        $parameters[] = $object->config('doctrine.proxy.dir');
        $parameters = Config::parameters($object, $parameters);
        if(array_key_exists(0, $parameters)){
            $proxyDir = $parameters[0];
        }
        if(empty($paths)){
            return false;
        }
        if(empty($proxyDir)){
            return false;
        }
        $cache = null;
        return ORMSetup::createAttributeMetadataConfiguration($paths, false, $proxyDir, $cache);
    }

    /**
     * @throws Exception
     */
    public static function entity_manager(App $object, $config, $connection=[]): EntityManager
    {
        $connection = Core::object($connection, Core::OBJECT_OBJECT);
        if(property_exists($connection, 'path')){
            $parameters = [];
            $parameters[] = $connection->path;
            $parameters = Config::parameters($object, $parameters);
            if(array_key_exists(0, $parameters)){
                $connection->path = $parameters[0];
            }
        }
        if (
            property_exists($connection, 'logging') &&
            !empty($connection->logging)
        ){
            $logger = new Logger(\Raxon\Module\Database::LOGGER_DOCTRINE);
            $logger->pushHandler(new StreamHandler($object->config('project.dir.log') . 'sql.log', Logger::DEBUG));
            $logger->pushProcessor(new PsrLogMessageProcessor(null, true));
            $object->logger($logger->getName(), $logger);
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $logger->info('Logger initialised.');
            }
            $config->setMiddlewares([new Logging\Middleware($logger)]);
        }
        if(
            property_exists($connection, 'driver') &&
            $connection->driver === 'pdo_sqlite' &&
            property_exists($connection, 'path') &&
            !File::exist($connection->path)
        ){
            $dir = Dir::name($connection->path);
            Dir::create($dir, Dir::CHMOD);
            $command = 'sqlite3 ' . $connection->path . ' "VACUUM;"';
            exec($command);
            File::permission($object, [
                'dir' => $dir,
                'file' => $connection->path
            ]);
        }
        $connection = Core::object($connection, Core::OBJECT_ARRAY);
        $connection = DriverManager::getConnection($connection, $config);
        $eventManager = new EventManager();
        return new EntityManager($connection, $config, $eventManager);
    }
}
