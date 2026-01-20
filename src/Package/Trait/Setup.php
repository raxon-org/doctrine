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
     */
    public function schema_register(object $flags, object $options): void
    {
        $object = $this->object();
        $dir_schema =  $object->config('project.dir.shared') . 'Schema' . $object->config('ds');
        $dir = new Dir();
        $read = $dir->read($dir_schema, true);
        if(!$read){
            return;
        }
        $read = Sort::list($read)->with(['name' => 'ASC']);
        $node = new Node($object);
        $class = 'System.Doctrine.Schema';
        $trigger = false;
        foreach($read as $file){
            if($file->type === File::TYPE){
                $file->entity = File::basename($file->name, $object->config('extension.json'));
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
                    $trigger = true;
                }
            }
        }
        if($trigger){
            //each record in System.Doctrine.Schema needs to be exported
            $list = $node->list($class, $node->role_system(), ['limit' => 100000]);
            if($list['count'] > 0){
                ddd($list);
            } else {
                foreach($read as $file){
                    ddd($file);
                }
                //new installation
            }
        }
        ddd($read);
    }
}