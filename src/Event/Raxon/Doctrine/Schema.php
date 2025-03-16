<?php

namespace Event\Raxon\Doctrine;

use Raxon\App;

use Raxon\Doctrine\Module\Schema as SchemaModule;
use Raxon\Doctrine\Service\Table;

use Exception;

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
                            ddd($config);
                            $config->table = Table::all($object, $config->name, $config->environment);
                            d($config);
                            if(in_array($node->table, $config->table, true)){
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
                                    SchemaService::entity($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_entity = true;
                                }
                                if($is_repository === false){
                                    //only create repository class if not exist, resetting means deleting the repository class and rerun this event
                                    SchemaService::repository($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_repository = true;
                                }
                                try {
                                    SchemaService::sql($object,
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
                                    SchemaService::entity($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_entity = true;
                                }
                                if($is_repository === false){
                                    //only create repository class if not exist, resetting means deleting the repository class and rerun this event
                                    SchemaService::repository($object,
                                        $options['class'],
                                        $options['role'],
                                        $options['node']
                                    );
                                    $is_repository = true;
                                }
                                try {
                                    SchemaService::sql($object,
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
//                            Table::import($object, $config->name, $config->environment, $config->table);
                            }
                        }
                    }
                }
            }
        }
    }
}