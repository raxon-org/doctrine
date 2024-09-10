<?php

namespace Package\Raxon\Doctrine\Output\Filter\System;

use Raxon\App;

use Raxon\Module\Controller;

class Doctrine extends Controller {
    const DIR = __DIR__ . '/';

    public static function environment(App $object, $response=null): object
    {
        $result = [];
        if(
            !empty($response) &&
            is_array($response)
        ){
            foreach($response as $nr => $record){
                if(
                    is_array($record) &&
                    array_key_exists('name', $record) &&
                    array_key_exists('environment', $record)
                ){
                    $name = str_replace('.', '-', $record['name']);
                    $environment = str_replace('.', '-', $record['environment']);
                    if($environment === '*'){
                        $result[$name] = [];
                        $result[$name]['*'] = $record;
                    } else {
                        if(!array_key_exists($record['name'], $result)){
                            $result[$name] = [];
                        }
                        $result[$name][$environment] = $record;
                    }
                }
                elseif(
                    is_object($record) &&
                    property_exists($record, 'name') &&
                    property_exists($record, 'environment')
                ){
                    $name = str_replace('.', '-', $record->name);
                    $environment = str_replace('.', '-', $record->environment);
                    if($environment === '*'){
                        $result[$name] = [];
                        $result[$name]['*'] = $record;
                    } else {
                        if(!array_key_exists($record->name, $result)){
                            $result[$name] = [];
                        }
                        $result[$name][$environment] = $record;
                    }
                }
            }
        }
        return (object) $result;
    }
}