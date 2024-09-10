<?php
namespace Raxon\Doctrine\Service;

use Raxon\App;
use Raxon\Module\Core;
use Raxon\Module\Database;
use Raxon\Module\File;

use Exception;

class Column extends Main

{
    /**
     * @throws Exception
     */
    public static function all(App $object, $name, $environment = null, $options = null): array
    {
        $options = Core::object($options);
        if ($environment === null) {
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $name = str_replace('.', '-', $name);
        if (!property_exists($options, 'table')) {
            throw new Exception('table not set in options');
        }
        try {
            $schema_manager = Database::schema_manager($object, $name, $environment);
        }
        catch (Exception $exception) {
            try {
                Database::instance($object, $name, $environment);
                $schema_manager = Database::schema_manager($object, $name, $environment);
            } catch (Exception $exception) {
                return [];
            }
        }
        if (!$schema_manager) {
            return [];
        }
        $tables = Table::all($object, $name, $environment);
        $sanitized_table = preg_replace('/[^a-zA-Z0-9_]/', '', $options->table);
        if (in_array($sanitized_table, $tables, true)) {
            $columns = $schema_manager->listTableColumns($sanitized_table);
            if($columns){
                $list = [];
                foreach($columns as $column){
                    $record = $column->toArray();
                    $record['type'] = File::basename(get_class($column->getType()));
                    $explode = explode('Type', $record['type'], 2);
                    $record['type'] = strtolower($explode[0]);
                    $list[]= $record;
                }
                return $list;
            }
        }
        return [];
    }
}
