<?php

namespace Event\Raxon\Doctrine;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Schema as SchemaModule;
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
            File::permission($object, [
                'dir' => $connection->path
            ]);
        }
        return $connection;
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function create(App $object, $event, $options=[]): void
    {
        //if exist rename table
        $node = false;
        $is_entity = false;
        $is_repository = false;
        $config = Database::config($object);
        $node = new Node($object);
        if(array_key_exists('node', $options)){
            if(property_exists($options['node'], 'environment')){
                if(
                    is_array($options['node']->environment) ||
                    is_object($options['node']->environment)
                ){
                    foreach($options['node']->environment as $name => $environments){
                        foreach($environments as $environment => $connection){
                            $connection = Schema::connection($object, $connection);
                            $connection->manager = Database::entity_manager($object, $config, $connection);
                            $connection->schema_manager = Database::schema_manager($connection->manager);
                            $connection->table = $connection->schema_manager->listTableNames();
                            /*
                            $em_connection = $em->getConnection();
                            $schema_manager = $em_connection->createSchemaManager();
                            $connection->table = $schema_manager->listTableNames();
                            */
                            if(in_array($options['node']->table, $connection->table, true)){
                                $class = 'System.Doctrine.Schema';
                                $role = $node->role_system();
                                $list = $node->list(
                                    $class,
                                    $role,
                                    [
                                        'filter' => [
                                            'environment' => $connection->uuid
                                        ]
                                    ]
                                );
                                $path = $connection->path;
                                $dir_name = Dir::name($path);
                                $file_name = File::basename($path, $object->config('extension.db'));
                                $date = date('Ymd_His');

                                ddd($object->config('project.dir.backup'));

                                $dir_backup = $dir_name .
                                    'Backup' .
                                    $object->config('ds') .
                                    $date .
                                    $object->config('ds')
                                ;
                                Dir::create($dir_backup, Dir::CHMOD);
                                File::permission($object, [
                                    'dir' => $dir_backup
                                ]);
                                foreach($connection->table as $nr => $table){
                                    $file = $dir_backup . $table . $object->config('extension.sql');
                                    $command = 'app raxon/doctrine table export -table=' . $table . ' -connection=' . $connection->uuid . ' -url=' . $file;
                                    d($command);
                                }

                                /**
                                 * rename goes wrong (we need to rename to much like the indexes uniques)
                                 * we are going to export the old table and import it after the new table is created
                                 * steps:
                                 * 1: query System.Doctrine.Schema for all current schema's
                                 * 2: export all old tables
                                 * 3: delete old tables
                                 * 4: create new tables from system.doctrine.schema
                                 * 5: import old tables
                                 *
                                 * app raxon/doctrine table export -table=user -connection=system
                                 *
                                 */
                                ddd('table exist work todo!');
                                $table = Table::rename(
                                    $object,
                                    $config->name,
                                    $config->environment,
                                    [
                                        'table' => $node->table,
                                        'rename' => true
                                    ]
                                );
                                d($table);
                                if($is_entity === false){
                                    SchemaModule::entity($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_entity = true;
                                }
                                if($is_repository === false){
                                    //only create repository class if not exist, resetting means deleting the repository class and rerun this event
                                    SchemaModule::repository($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_repository = true;
                                }
                                try {
                                    SchemaModule::sql($object,
                                        $options['class'],
                                        $options['role'],
                                        $connection,
                                        $options['node'],
                                        [
                                            'config' => $config,
                                        ]
                                    );
                                }
                                catch(Exception $exception){
                                    echo $exception;
                                }
                                d($table);
                                $is_rename = true;
                                /*
                                Table::rename($object, $config->name, $config->environment);
                                Table::import($object, $config->name, $config->environment, $config->table);
                                */
                            } else {
                                if($is_entity === false){
                                    SchemaModule::entity($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_entity = true;
                                }
                                if($is_repository === false){
                                    //only create repository class if not exist, resetting means deleting the repository class and rerun this event
                                    SchemaModule::repository($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_repository = true;
                                }
                                try {
                                    SchemaModule::sql($object,
                                        $options['class'],
                                        $options['role'],
                                        $connection,
                                        $options['node'],
                                        [
                                        ]
                                    );
                                }
                                catch(Exception $exception){
                                    echo $exception;
                                }
//                            Table::import($object, $config->name, $config->environment, $config->table);
                            }
                        }
                    }
                }
            }
        }
    }
}