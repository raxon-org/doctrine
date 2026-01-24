<?php

namespace Event\Raxon\Doctrine;

use Raxon\App;
use Raxon\Config;

use Raxon\Doctrine\Event\Raxon\Doctrine\Schema_old;
use Raxon\Module\Cli as CliModule;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Build;
use Raxon\Doctrine\Service\Table;

use Raxon\Node\Module\Node;

use Exception;

use Raxon\Exception\ObjectException;

class Schema {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function connection(App $object, $connection){
        $connection = Core::object($connection, Core::OBJECT);
        if(property_exists($connection, 'path')){
            $parameters = [];
            $parameters[] = $connection->path;
            $parameters = Config::parameters($object, $parameters);
            if(array_key_exists(0, $parameters)){
                $connection->path = $parameters[0];
            }
        }
        return $connection;
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function create(App $object, $event, $options=[]): void
    {
        $config = Database::config($object);
        $node = new Node($object);
        d($options);
        if(array_key_exists('node', $options)){
            if(property_exists($options['node'], 'environment')){
                if(
                    is_array($options['node']->environment) ||
                    is_object($options['node']->environment)
                ){
                    Build::entity($object,
                        $options['class'],
                        $options['role'],
                        $options['node']
                    );
                    Build::repository($object,
                        $options['class'],
                        $options['role'],
                        $options['node']
                    );
                    foreach($options['node']->environment as $name => $environments) {
                        foreach ($environments as $environment => $connection) {
                            $connection = Schema::connection($object, $connection);
                            $connection->manager = Database::entity_manager($object, $config, $connection);
                            $connection->schema_manager = Database::schema_manager($connection->manager);
                            $connection->table = $connection->schema_manager->listTableNames();
                            Build::sql($object,
                                $options['class'],
                                $options['role'],
                                $connection,
                                $options['node'],
                                [
                                    'config' => $config,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}