<?php
namespace Raxon\Doctrine\Service;

use Raxon\App;
use Raxon\Module\Core;
use Raxon\Module\Database;

use Exception;

class Table extends Main

{

    /**
     * @throws Exception
     */
    public static function all(App $object, $name, $environment=null): array
    {
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
        $tables = [];
        if($schema_manager){
            $tables = $schema_manager->listTableNames();
        }
        return $tables;
    }

    /**
     * @throws Exception
     */
    public static function truncate(App $object, $name, $environment=null, $options=null): bool
    {
        $options = Core::object($options);
        if($environment === null){
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $name = str_replace('.', '-', $name);
        if(!property_exists($options, 'table')){
            throw new Exception('table not set in options');
        }
        try {
            $connection = Database::connection($object, $name, $environment);
        }
        catch(Exception $exception){
            try {
                Database::instance($object, $name, $environment);
                $connection = Database::connection($object, $name, $environment);
            }
            catch(Exception $exception){
                return false;
            }
        }
        $sanitized_table = preg_replace('/[^a-zA-Z0-9_]/', '', $options->table);
        $driver = Database::driver($object, $name, $environment);
        $reset = false;
        switch($driver){
            case 'pdo_mysql':
                $sql = 'TRUNCATE TABLE ' . $sanitized_table;
                break;
            case 'pdo_sqlite':
                $sql = 'DELETE FROM ' . $sanitized_table;
                $reset = 'DELETE FROM SQLITE_SEQUENCE WHERE name = "' . $sanitized_table . '"';
                break;
            default:
                throw new Exception('Driver not supported.');
        }
        try {
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement();
            if($driver === 'pdo_sqlite' && $reset){
                try {
                    $stmt = $connection->prepare($reset);
                    $result = $stmt->executeStatement();
                }
                catch(Exception $exception){
                    return false;
                }
            }
            return true;
        }
        catch(Exception $exception){
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public static function delete(App $object, $name, $environment=null, $options=null): bool
    {
        $options = Core::object($options);
        if($environment === null){
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $name = str_replace('.', '-', $name);
        if(!property_exists($options, 'table')){
            throw new Exception('table not set in options');
        }
        try {
            $schema_manager = Database::schema_manager($object, $name, $environment);
        }
        catch(Exception $exception){
            try {
                Database::instance($object, $name, $environment);
                $schema_manager = Database::schema_manager($object, $name, $environment);
            }
            catch (Exception $exception){
                return false;
            }
        }
        $tables = Table::all($object, $name, $environment);
        $sanitized_table = preg_replace('/[^a-zA-Z0-9_]/', '', $options->table);
        if(in_array($sanitized_table, $tables, true)){
            $schema_manager->dropTable($sanitized_table);
            return true;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public static function rename(App $object, $name, $environment=null, $options=[]): bool | string
    {
        if($environment === null){
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $options = Core::object($options);
        $name = str_replace('.', '-', $name);
        if(!property_exists($options, 'table')){
            throw new Exception('table not set in options');
        }
        if(!property_exists($options, 'rename')){
            $options->rename = true;
        }
        $tables = Table::all($object, $name, $environment);
        $table = $options->table;
        $rename = '';
        if($options->rename === true){
            //new table name _old_nr
            $rename = $table . '_old';
            $counter = 1;
            while(true){
                if(
                    in_array(
                        $rename,
                        $tables,
                        true
                    ) === false
                ){
                    break;
                }
                $rename = $table . '_old_' . $counter;
                $counter++;
                if(
                    $counter >= PHP_INT_MAX ||
                    $counter < 0
                ){
                    throw new Exception('Out of range.');
                }
            }
        }
        elseif(is_string($options->rename)){
            if(
                in_array(
                    $options->rename,
                    $tables,
                    true
                )
            ){
                //log to sql exception
                return false;
            }
            $rename = $options->rename;
            //new table name
        }
        $sanitized_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sanitized_rename = preg_replace('/[^a-zA-Z0-9_]/', '', $rename);
        // Construct the SQL query with the sanitized table names
        if(
            strlen($sanitized_table) >= 2 &&
            strlen($sanitized_rename) >= 2
        ){
            $driver = Database::driver($object, $name, $environment);
            switch($driver){
                case 'pdo_mysql':
                    $sql = 'RENAME TABLE ' . $sanitized_table . ' TO ' . $sanitized_rename;
                    break;
                case 'pdo_sqlite':
                    $sql = 'ALTER TABLE ' . $sanitized_table . ' RENAME TO ' . $sanitized_rename;
                    break;
                default:
                    throw new Exception('Driver not supported.');
            }
            try {
                $connection = Database::connection($object, $name, $environment);
            }
            catch(Exception $exception){
                try {
                    Database::instance($object, $name, $environment);
                    $connection = Database::connection($object, $name, $environment);
                } catch(Exception $exception){
                    return false;
                }
            }
            if(!$connection){
                return false;
            }
            try {
                $stmt = $connection->prepare($sql);
                $result = $stmt->executeStatement();
            }
            catch(Exception $exception){
               return false;
            }
            return $sanitized_rename;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public static function foreign_keys(App $object, $name, $environment=null, $options=null): array
    {
        if (!property_exists($options, 'table')) {
            throw new Exception('table not set in options');
        }
        if ($environment === null) {
            $environment = $object->config('environment');
        } else {
            $environment = str_replace('.', '-', $environment);
        }
        $name = str_replace('.', '-', $name);
        try {
            $schema_manager = Database::schema_manager($object, $name, $environment);
        } catch (Exception $exception) {
            try {
                Database::instance($object, $name, $environment);
                $schema_manager = Database::schema_manager($object, $name, $environment);
            } catch (Exception $exception) {
                return [];
            }
        }
        $sanitized_table = preg_replace('/[^a-zA-Z0-9_]/', '', $options->table);
        $foreign_keys = [];
        if ($schema_manager) {
            $list = $schema_manager->listTableForeignKeys($sanitized_table);
            foreach ($list as $foreign_key) {
                $record = (object) [
                    'name' => $foreign_key->getName(),
                    'local_columns' => $foreign_key->getLocalColumns(),
                    'foreign_table' => $foreign_key->getForeignTableName(),
                    'foreign_columns' =>$foreign_key->getForeignColumns(),
                    'options' => $foreign_key->getOptions(),
                    'namespace' => $foreign_key->getNamespaceName(),
                    'is_quoted' => $foreign_key->isQuoted(),
                ];
                $foreign_keys[] = $record;
            }
        }
        return $foreign_keys;
    }
}