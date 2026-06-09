<?php
namespace Raxon\Doctrine\Module;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Doctrine\DBAL\Logging;
use Doctrine\ORM\EntityManager;

use Doctrine\DBAL\Schema\OracleSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Schema\SqlServerSchemaManager;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;


use Doctrine\ORM\ORMSetup;

use Plugin\Environment_Logging;
use Raxon\App;
use Raxon\Config;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;

class Database {

    use Environment_Logging;

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
        //new installations does not have paths defined
        //new installations does not have a proxy dir (yet ???)
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
    public static function entity_manager(App $object, $config, object $connection): EntityManager
    {
        if(property_exists($connection, 'path')){
            $parameters = [];
            $parameters[] = $connection->path;
            $parameters = Config::parameters($object, $parameters);
            if(array_key_exists(0, $parameters)){
                $connection->path = $parameters[0];
            }
        }
        if(!$config){
            //clean installs have config null
            throw new Exception('Config is null.');
        }
        if (
            property_exists($connection, 'logging') &&
            !empty($connection->logging)
        ){
            if(
                is_string($connection->logging) &&
                $connection->logging === '{{environment.logging()}}'
            ){
                $log = new Log();
                $connection->logging = $log->environment_logging();
//                $class = '\\Plugin\\Environment_Logging';
//                $el = new $class()
//                d(class_exists($class));

                //load plugin
            }


            ddd($connection->logging);
            $logger = new Logger(\Raxon\Module\Database::LOGGER_DOCTRINE);
            $logger->pushHandler(new StreamHandler($object->config('project.dir.log') . 'sql.log', Logger::DEBUG));
            $logger->pushProcessor(new PsrLogMessageProcessor(null, true));
            $object->logger($logger->getName(), $logger);
            if($object->config('framework.environment') === Config::MODE_DEVELOPMENT){
                $logger->info('Logger initialised.');
            }
            if($config){
                $config->setMiddlewares([new Logging\Middleware($logger)]);
            }
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
        $connection_array = Core::object($connection, Core::OBJECT_ARRAY);
        $connection_array = DriverManager::getConnection($connection_array, $config);
        $eventManager = new EventManager();
        $em = new EntityManager($connection_array, $config, $eventManager);
        $em->getConfiguration()->enableNativeLazyObjects(true);
        return $em;
    }

    public static function schema_manager(EntityManager $em): mixed
    {
        $platform = $em->getConnection()->getDatabasePlatform();
        if($platform instanceof MySQLPlatform){
            return new MySqlSchemaManager($em->getConnection(), $platform);
        }
        elseif($platform instanceof SQLitePlatform){
            return new SQLiteSchemaManager($em->getConnection(), $platform);
        }
        elseif($platform instanceof SQLServerPlatform){
            return new SqlServerSchemaManager($em->getConnection(), $platform);
        }
        elseif($platform instanceof PostgreSQLPlatform){
            return new PostgreSqlSchemaManager($em->getConnection(), $platform);
        }
        elseif($platform instanceof OraclePlatform){
            return new OracleSchemaManager($em->getConnection(), $platform);
        }
        elseif($platform instanceof MariaDBPlatform){
            return new MySqlSchemaManager($em->getConnection(), $platform);
        }
        return false;
    }
}
