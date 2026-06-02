<?php
namespace Package\Raxon\Doctrine\Trait;

use Event\Raxon\Doctrine\Schema;
use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Doctrine\Module\Database;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\File;
use Raxon\Module\Dir;
use Raxon\Module\Sort;
use Raxon\Node\Module\Node;

trait Setup {
    const SQLITE_COMMAND = 'sqlite3';

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     */
    public function role_system_import(): void
    {
        $object = $this->object();
        $package = $object->request('package');
        if($package){
            $node = new Node($object);
            $node->role_system_create($package);
        }
    }

    /**
     * @throws Exception
     */
    public function register(): bool
    {
        $object = $this->object();
        $status = false;
        $options = App::options($object);
        $node = new Node($object);
        $record_options = [
                'where' => [
                        [
                                'value' => $object->request('package'),
                                'attribute' => 'name',
                                'operator' => '===',
                        ]
                ]
        ];
        $class = 'System.Installation';
        $response = $node->record($class, $node->role_system(), $record_options);
        if(
                $response &&
                array_key_exists('node', $response)
        ){
            if(property_exists($options, 'force')){
                $record = $response['node'];
                $record->mtime = time();
                $response = $node->put($class, $node->role_system(), $record);
                echo 'Register update ' . $object->request('package') . ' installation...' . PHP_EOL;
                $status = true;
            }
            elseif(property_exists($options, 'patch')){
                $record = $response['node'];
                $record->mtime = time();
                $response = $node->patch($class, $node->role_system(), $record);
                echo 'Register update ' . $object->request('package') . ' installation...' . PHP_EOL;
                $status = true;
            }
            else {
                echo 'Skipping ' . $object->request('package') . ' installation...' . PHP_EOL;
            }
        } else {
            $time = time();
            $record = (object) [
                    'name' => $object->request('package'),
                    'ctime' => $time,
                    'mtime' => $time,
            ];
            $response = $node->create($class, $node->role_system(), $record);
            echo 'Registering ' . $object->request('package') . ' installation...' . PHP_EOL;
            $status = true;
        }
        return $status;
    }

    /**
     * @throws Exception
     */
    public function doctrine_bin(object $flags, object $options): void
    {
        $object = $this->object();
        $posix_id = $object->config(Config::POSIX_ID);
        if(
                !in_array(
                        $posix_id,
                        [
                                0,
                        ],
                        true
                )
        ){
            throw new Exception('Access denied...');
        }
        $url_bin = $object->config('project.dir.vendor') . 'raxon/doctrine/src/Bin/Doctrine.php';
        $url_target = $object->config('project.dir.binary') . 'Doctrine.php';
        File::copy($url_bin, $url_target);
        $url_bin_source = $object->config('project.dir.vendor') . 'raxon/doctrine/src/Bin/doctrine';
        $url_bin_target = '/usr/bin/doctrine';
        File::copy($url_bin_source, $url_bin_target);
        $command = 'chmod +x ' . $url_bin_target;
        exec($command);
        File::permission($object, [
                'url_target' => $url_target,
        ]);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function system_config(object $flags, object $options): void
    {
        $object = $this->object();
        $posix_id = $object->config(Config::POSIX_ID);
        if(
                !in_array(
                        $posix_id,
                        [
                                0,
                                33
                        ],
                        true
                )
        ){
            throw new Exception('Access denied...');
        }
        $node = new Node($object);
        $config = $node->record('System.Config', $node->role_system());
        if(
                $config &&
                is_array($config) &&
                array_key_exists('node', $config) &&
                property_exists($config['node'], 'uuid') // &&
//            !property_exists($config['node'], 'doctrine')
        ){
            $patch = (object) [
                    'uuid' => $config['node']->uuid,
                    'doctrine' => '*'
            ];
            $config = $node->patch('System.Config', $node->role_system(), $patch);
            if(
                    is_array($config) &&
                    array_key_exists('node', $config)
            ){
                //nothing
            } else {
                throw new Exception('Could not patch node System.Config');
            }
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function system_doctrine(object $flags, object $options): void
    {
        $object = $this->object();
        $posix_id = $object->config(Config::POSIX_ID);
        if (
                !in_array(
                        $posix_id,
                        [
                                0,
                                33
                        ],
                        true
                )
        ) {
            throw new Exception('Access denied...');
        }
        $url = $object->config('project.dir.vendor') .
                'raxon/doctrine/src/Node/Template/System.Doctrine' .
                $object->config('extension.json')
        ;
        $node = new Node($object);
        $class = 'System.Doctrine';
        $response = $node->record($class, $node->role_system());
        if($response){
            if(
                    property_exists($options, 'patch') &&
                    $options->patch === true
            ){
                $record = $response['node'];
                $data = $object->data_read($url);
                if($data){
                    $default = $data->get('System.Doctrine.0');
                    $record = Core::object_merge($record, $default);
                }
                $result = $node->patch($class, $node->role_system(), $record);
            }
            return;
        }
        $data = $object->data_read($url);
        if($data){
            $default = $data->get('System.Doctrine.0');
            $result = $node->create($class, $node->role_system(), $default);
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function system_doctrine_environment(object $flags, object $options): void
    {
        $object = $this->object();
        $posix_id = $object->config(Config::POSIX_ID);
        if (
                !in_array(
                        $posix_id,
                        [
                                0,
                                33
                        ],
                        true
                )
        ) {
            throw new Exception('Access denied...');
        }
        $node = new Node($object);
        $class = 'System.Doctrine.Environment';
        $response = $node->record($class, $node->role_system(), [
                'where' => [
                        [
                                'attribute' => 'name',
                                'operator' => '===',
                                'value' => 'system'
                        ]
                ]
        ]);
        $url = $object->config('project.dir.vendor') .
                'raxon/doctrine/src/Node/Template/System.Doctrine.Environment' .
                $object->config('extension.json')
        ;
        if($response) {
            if (
                    property_exists($options, 'patch') &&
                    $options->patch === true
            ) {
                $record = $response['node'];
                $data = $object->data_read($url);
                if ($data) {
                    $default = $data->get('System.Doctrine.Environment.0');
                    $record = Core::object_merge($record, $default);
                }
                $response = $node->patch($class, $node->role_system(), $record);
            }
        } else {
            $data = $object->data_read($url);
            if($data) {
                $default = $data->get('System.Doctrine.Environment.0');
                $response = $node->create($class, $node->role_system(), $default);
            }
        }
        //reload config to include the doctrine configuration
        Config::configure($object, (object) [
            'clear' => true
        ]);
        $connection = $response['node'] ?? null;
        if(!$connection) {
            throw new Exception('Could not create System.Doctrine.Environment');
        }
        $config = Database::config($object);
        $connection = Schema::connection($object, $connection);
        $connection->manager = Database::entity_manager($object, $config, $connection);
        $connection->schema_manager = Database::schema_manager($connection->manager);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function schema_update(object $flags, object $options): void
    {
        $object = $this->object();
        $dir_schema =  $object->config('project.dir.shared') . 'Schema' . $object->config('ds');
        $dir = new Dir();
        $read = $dir->read($dir_schema, true);
        if(!$read){
            return;
        }
        $list = Sort::list($read)->with(['name' => 'ASC']);
        $node = new Node($object);
        $class = 'System.Doctrine.Schema';
        $trigger = false;
        foreach($list as $file){
            if($file->type === File::TYPE){
                $file->entity = str_replace('.', '', File::basename($file->name, $object->config('extension.json')));
                $schema = $node->record($class, $node->role_system(), [
                        'where' => [
                                [
                                        'attribute' => 'entity',
                                        'operator' => '===',
                                        'value' => $file->entity,
                                ]
                        ]
                ]);
                if(!$schema){
                    echo 'Updating, no schema found for: ' . $file->entity . PHP_EOL;
                    $trigger = true;
                } else {
                    $file->read = $object->data_read($file->url);
                    if($file->read){
                        $version = $file->read->get('System.Doctrine.Schema.0.version');
                        if(
                                $version &&
                                array_key_exists('node', $schema) &&
                                property_exists($schema['node'], 'version') &&
                                $version !== $schema['node']->version
                        ){
                            echo 'Updating ' . $file->entity . ' from version: '. $schema['node']->version . ' to version: '. $version . PHP_EOL;
                            $trigger = true;
                        }
                    }
                }
            }
        }
        $trigger = true; //force update for testing...
        if($trigger === true){
            $response = $node->list($class, $node->role_system(), [
                    'limit' => 100000,
                    'relation' => true
            ]);
            if($response['count'] > 0){
                $dir_sqlite = $object->config('project.dir.data') . 'Sqlite' . $object->config('ds');
                $url_system = $dir_sqlite. 'System.db';
                $url_system_backup_temp = $dir_sqlite . 'System.backup.db';
                $dir_system_backup = $dir_sqlite . 'Backup' . $object->config('ds');
                $url_system_backup_write = $dir_system_backup .'System.backup.' . date('Y.m.d') . '.db';
                File::move($url_system, $url_system_backup_temp);
                $node = new Node($object);
                $class = 'System.Doctrine.Environment';
                $response = $node->record($class, $node->role_system(), [
                        'where' => [
                                [
                                        'attribute' => 'name',
                                        'operator' => '===',
                                        'value' => 'system'
                                ]
                        ]
                ]);
                $connection = $response['node'] ?? null;
                $config = Database::config($object);
                $connection = Schema::connection($object, $connection);
                $connection->manager = Database::entity_manager($object, $config, $connection);
                $connection->schema_manager = Database::schema_manager($connection->manager);
                foreach($list as $file){
                    if(
                            property_exists($options, 'patch') &&
                            $options->patch === true
                    ){
                        $command = Core::binary($object) . ' raxon/doctrine schema import -url="' . $file->url . '" -connection=system -patch';
                    }
                    elseif(
                            property_exists($options, 'force') &&
                            $options->force === true
                    ){
                        $command = Core::binary($object) . ' raxon/doctrine schema import -url="' . $file->url . '" -connection=system -force';
                    } else {
                        $command = Core::binary($object) . ' raxon/doctrine schema import -url="' . $file->url . '" -connection=system';
                    }
                    echo $command . PHP_EOL;
                    exec($command, $output);
                    echo implode(PHP_EOL, $output) . PHP_EOL;
                }
                $connection_backup = clone $response['node'] ?? null;
                $connection_backup->name = 'system_backup';
                $connection_backup->path = $url_system_backup_temp;
                $config = Database::config($object);
                $connection_backup = Schema::connection($object, $connection_backup);
                $connection_backup->manager = Database::entity_manager($object, $config, $connection_backup);
                $connection_backup->schema_manager = Database::schema_manager($connection_backup->manager);
                foreach($list as $file){
                    if($file->type === File::TYPE && property_exists($file, 'entity')){
                        echo $file->entity . PHP_EOL;
                        //get all data from this entity from the backup connection
                        $file->data = $connection_backup->manager->getRepository('Entity\\' . $file->entity)->findBy(
                                [],
                                [
                                        'id' => 'ASC'
                                ]
                        );
                        $columns = $file->read->get('System.Doctrine.Schema.0.column');
                        foreach($columns as $column){
                            $column_options = new Data($column->options ?? []);
                            if($column_options->has('join.table')){
                                $query = 'SELECT * FROM ' . $column_options->get('join.table');
                                $stmt = $connection_backup->manager->getConnection()->prepare($query);
                                $result_set = $stmt->executeQuery();
                                $key_count = 0;
                                while ($row = $result_set->fetchAssociative()) {
                                    $key_count = count($row);
                                    $values = str_repeat('?,', $key_count - 1) . '?';
                                    $query = 'INSERT INTO ' . $column_options->get('join.table') . ' VALUES ('.$values.')';
                                    $stmt = $connection->manager->getConnection()->prepare($query);
                                    $bind_nr = 1;
                                    foreach($row as $key => $value){
                                        $stmt->bindValue($bind_nr, $value);
                                        $bind_nr++;
                                    }
                                    $stmt->executeQuery();
                                }
                                echo $column_options->get('join.table') . PHP_EOL;
                            }
                        }
                    }
                    elseif($file->type === File::TYPE){
//                        d($file);
                    }
                }
                $connection = $response['node'] ?? null;
                $config = Database::config($object);
                $connection = Schema::connection($object, $connection);
                $connection->manager = Database::entity_manager($object, $config, $connection);
                $connection->schema_manager = Database::schema_manager($connection->manager);
                $tables = $connection->schema_manager->listTableNames();
                foreach($list as $file) {
                    if ($file->type === File::TYPE && property_exists($file, 'data')) {
                        if (!empty($file->data)) {
                            foreach ($file->data as $item) {
                                try {
                                    $connection->manager->persist($item);
                                    $connection->manager->flush();
                                } catch (Exception $e) {
                                    echo 'Error copying ' . $file->entity . ': ' . $e->getMessage() . PHP_EOL;
                                }
                            }
                            echo 'Copied ' . count($file->data) . ' records for ' . $file->entity . PHP_EOL;
                        }
                    }

                }
                if(!Dir::is($dir_system_backup)){
                    Dir::create($dir_system_backup, Dir::CHMOD);
                    File::permission($object, [
                            'dir' => $dir_system_backup,
                    ]);
                }
                File::move($url_system_backup_temp, $url_system_backup_write);
                File::permission($object, [
                        'file' => $url_system_backup_write,
                ]);
            } else {
                //new installation
                foreach($list as $file){
                    $command = Core::binary($object) . ' raxon/doctrine schema import -url="' . $file->url . '" -connection=system';
                    exec($command, $output);
                    echo implode(PHP_EOL, $output) . PHP_EOL;
                }
            }
        }
    }
}