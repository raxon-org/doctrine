<?php

namespace Event\Raxon\Doctrine;

use Raxon\App;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Schema as SchemaModule;
use Raxon\Doctrine\Service\Table;

use Exception;
use Raxon\Node\Module\Node;

class Schema {

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
        if(array_key_exists('node', $options)){
            $node = $options['node'];
            d($node);
            if(property_exists($node, 'environment')){
                if(
                    is_array($node->environment) ||
                    is_object($node->environment)
                ){

                    foreach($node->environment as $name => $environments){
                        foreach($environments as $environment => $config){
                            $connection = $object->config('doctrine.environment.' . $options->connection . '.' . $options->environment);
                            if($connection === null){
                                $connection = $object->config('doctrine.environment.' . $options->connection . '.' . '*');
                            }
                            $em = Database::entity_manager($object, $config, $connection);
                            $config->table = $em->listTableNames();
                            ddd($config);
                            if(in_array($node->table, $config->table, true)){
                                /**
                                 * rename goes wrong (we need to rename to much like the indexes uniques)
                                 * we are going to export the old table and import it after the new table is created
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
                                        $options['node'],
                                        [
                                            'em' => $em,
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