<?php
namespace Raxon\Doctrine\Service;

use Raxon\App;
use Raxon\Module\Core;
use Raxon\Module\Database;

use Exception;

class Sequence extends Main

{

    /**
     * @throws Exception
     */
    public static function all(App $object, $name, $environment=null, $options=null): array
    {
        //both mysql & sqlite do not support sequences
        throw new Exception('Operation "Doctrine\DBAL\Platforms\AbstractPlatform::getListSequencesSQL" is not supported by platform.');
        if($environment === null){
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $name = str_replace('.', '-', $name);
        try {
            $schema_manager = Database::schema_manager($object, $name, $environment);
        }
        catch(Exception $exception){
            try {
                Database::instance($object, $name, $environment);
                $schema_manager = Database::schema_manager($object, $name, $environment);
            }
            catch(Exception $exception){
                return [];
            }
        }
        $db  = $options->db ?? null;

        $sequences = [];
        if($schema_manager){
            if($db){
                $db = preg_replace('/[^a-zA-Z0-9_]/', '', $db);
                $list = $schema_manager->listSequences($db);
            } else {
                $list = $schema_manager->listSequences();
            }
            foreach($list as $sequence){
                d($sequence);
                d(get_class_methods($sequence));
                /*
                $record = (object) [
                    'column' => $index->getColumns(),
                    'is_simple_index' => $index->isSimpleIndex(),
                    'is_unique' => $index->isUnique(),
                    'is_primary' => $index->isPrimary(),
                    'flags' => $index->getFlags(),
                    'options' => $index->getOptions(),
                    'namespace' => $index->getNamespaceName(),
                    'name' => $index->getName(),
                    'is_quoted' => $index->isQuoted(),
                ];
                $indexes[] = $record;
                */
            }
        }
        return $sequences;
    }
}