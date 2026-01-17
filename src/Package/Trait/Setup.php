<?php
namespace Package\Raxon\Doctrine\Trait;

use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\File;
use Raxon\Module\Dir;
use Raxon\Node\Module\Node;

trait Setup {

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
                    ddd($default);
                }
                d($data);
                ddd($record);

                /*
                $record->environment = '*';
                $record->proxy = (object) [
                    'dir' => '/tmp/doctrine/'
                ];
                $record->paths = [
                    "{{config('project.dir.shared')}}Entity\/"
                ];
                $record->entity = (object) [
                    'prefix'  => '\\Entity\\'
                ];
                */
                $result = $node->patch($class, $node->role_system(), $record);
            }
            return;
        }
        $data = $object->data_read($url);
        ddd($data);
        $result = $node->create($class, $node->role_system(), $data);
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
        if($response){
            if(
                property_exists($options, 'patch') &&
                $options->patch === true
            ){
                $record = $response['node'];
                $record->environment = '*';
                $record->driver = 'pdo_sqlite';
                $record->path = "{{config('project.dir.data')}}Sqlite\/System.db";
                $record->logging = true;
                $response = $node->patch($class, $node->role_system(), $record);
            }
            return;
        }
        $data = [
            'name' => 'system',
            'environment' => '*',
            'driver' => 'pdo_sqlite',
            'path' => "{{config('project.dir.data')}}Sqlite\/System.db",
            'logging' => true
        ];
        $response = $node->create($class, $node->role_system(), $data);
    }
}