<?php
namespace Raxon\Doctrine\Module;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

use Exception;

use Raxon\App;
use Raxon\Config;

use Raxon\Exception\AuthorizationException;
use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\File;
use Raxon\Module\Parse;

use Raxon\Exception\FileWriteException;
use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Node\Module\Node;


class Entity {

    /**
     * @throws LocateException
     * @throws Exception
     */
    public static function validate(App $object, object $validation, object $record=null, array $options=[]): object
    {
        $method = $options['function'] ?? false;
        $extra = $options['extra'] ?? false;
        $extension = $object->config('extension.php');
        $test = [];
        $data = new Data($record);
        foreach($validation as $field => $list){
            $is_optional = false;
            if($field == 'test'){
                continue;
            }
            if(substr($field, 0, 1) === '?'){
                $field = substr($field, 1);
                $is_optional = true;
            }
            $test[$field] = [];
            if(is_object($list)){
                $validation->{$field} = Entity::validate($object, $list, $record, ['extra' => $field, 'function' => $options['function'] ?? false]);
                if(property_exists($validation->{$field}, 'test')){
                    $validation->test[$field] = $validation->{$field}->test;
                }
            }
            elseif(is_array($list)){
                $field_request = str_replace('[]', '', $field);
                if($extra !== false){
                    $field_request = $extra . '.' . $field_request;
                }
                $value = $data->get($field_request);
                if(
                    is_string($value) &&
                    substr($value, 0, 1) === '[' &&
                    substr($value, -1, 1) === ']'
                ){
                    $value = Core::object($value, Core::OBJECT_ARRAY);
                }
                if(
                    $is_optional &&
                    empty($value)
                ){
                    $function = 'optional';
                    if(empty($test[$field][$function])){
                        $test[$field][$function] = [];
                    }
                    $test[$field][$function][] = true;
                } else {
                    foreach($list as $nr => $record_list){
                        foreach($record_list as $key => $argument){
                            if(substr($key, 0, 1) === '#'){
                                continue;
                            }
                            $name = Controller::name($key);
                            $key = 'validate' . '.' . $key;
                            $function = str_replace('.', '_', $key);
                            if(function_exists($function)){
                                $test[$field][$function][] = $function($object, $data->data(), $value, $field, $argument, $method);
                            } else {
                                $url_list = (array) $object->config('validate.dir.validator');
                                if(empty($url_list)){
                                    $url_list = [];
                                } else {
                                    foreach($url_list as $url_nr => $url_value){
                                        $url_list[$url_nr] .= $name . $extension;
                                    }
                                }
                                $url_list[] = $object->config('controller.dir.validator') .
                                    $name .
                                    $extension
                                ;
                                $url_list[] = $object->config('project.dir.validator') .
                                    $name .
                                    $extension
                                ;
                                $url_list[] = $object->config('package.raxon/node.dir.validator') .
                                    $name .
                                    $extension
                                ;
                                $url_list[] = $object->config('project.dir.source') .
                                    'Validator' .
                                    $object->config('ds') .
                                    $name .
                                    $extension
                                ;
                                $url_list[] = $object->config('framework.dir.validator') .
                                    $name .
                                    $extension
                                ;
                                $url_list = Config::parameters($object, $url_list);
                                if(empty($test[$field][$function])){
                                    $test[$field][$function] = [];
                                }
                                $is_found = false;
                                foreach($url_list as $url){
                                    if(File::exist($url)){
                                        require_once $url;
                                        $test[$field][$function][] = $function($object, $data->data(), $value, $field, $argument, $method);
                                        $is_found = true;
                                        break;
                                    }
                                }
                                if($is_found === false){
                                    throw new LocateException('validator (' . $function . ') not found.', $url_list);
                                }
                            }
                        }
                    }
                }
            }
        }
        if(
            property_exists($validation, 'test') &&
            is_array($validation->test)
        ){
            $validation->test = array_merge($test, $validation->test);
        } else {
            $validation->test = $test;
        }
        foreach($validation as $field => $value) {
            if (
                is_object($value) &&
                property_exists($value, 'success') &&
                $value->success === false
            ) {
                $validation->success = $value->success;
            }
        }
        if(
            property_exists($validation, 'success') &&
            $validation->success === false
        ){
            return $validation;
        } else {
            $validation->success = true;
            foreach($test as $field => $list){
                foreach($list as $key => $subList){
                    foreach($subList as $nr => $status){
                        if(empty($status)){
                            $validation->success = false;
                        }
                    }
                }
            }
            return $validation;
        }
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws Exception
     */
    protected static function get_validation(App $object, $url, $type): object | false
    {
        $data = $object->data(sha1($url));
        if($data === null){
            $data = $object->parse_read($url, sha1($url));
        }
        if($data){
            $validation = $data->data($type . '.validate');
            if(empty($validation)){
                return false;
            }
            return $validation;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public static function get_validate_url(App $object, string $entity): string
    {
        return $object->config('project.dir.source') .
            'Validate' .
            $object->config('ds') .
            $entity .
            $object->config('extension.json');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function create(App $object, object $connection, object $role, string $entity, object $request, object &$error = null): ?object
    {
        $data = [];
        $data[] = $request;
        $error = [];
        $response = Entity::create_many($object, $connection, $role, $entity, $data, $error);
        $error = $error[0] ?? null;
        return $response[0] ?? null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function create_many(App $object, object $connection, object $role, string $entity, array $data, array &$error=[]): array
    {
        $function = 'create';
        $nodes = [];
        $validate_url = Entity::get_validate_url($object, $entity);
        $validation = Entity::get_validation($object, $validate_url, $entity . '.' . $function);
        $object->config('doctrine.entity.manager', $connection->manager);
        foreach ($data as $node) {
            if(File::exist($validate_url)) {
                $data_node = new Data($node);
                $validate = Entity::validate($object, $validation, $data_node->data());
                if ($validate) {
                    if ($validate->success === true) {
                        $className = $object->config('doctrine.entity.prefix') . $entity;
                        $class = new $className();
                        if(method_exists($class, 'setObject')){
                            $class->setObject($object);
                        }
                        if(method_exists($class, 'setEntityManager')){
                            $class->setEntityManager($connection->manager);
                        }
                        $node = Entity::import(
                            $class,
                            $node
                        );
                        $connection->manager->persist($node);
                        $connection->manager->flush();
                        $nodes[] = $node;
                    } else {
                        $error[] = $validate->test;
                    }
                } else {
                    $error[] = (object) [
                        'success' => false,
                    ];
                }
            }
        }
        return $nodes;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function patch(App $object, object $connection, object $role, string $entity, object $request, object &$error = null): ?object
    {
        $data = [];
        $data[] = $request;
        $error = [];
        $response = Entity::patch_many($object, $connection, $role, $entity, $data, $error);
        $error = $error[0] ?? null;
        return $response[0] ?? null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function patch_many(App $object, object $connection, object $role, string $entity, array $data, array &$error=[]): array
    {
        $function = 'patch';
        $nodes = [];
        $validate_url = Entity::get_validate_url($object, $entity);
        $validation = Entity::get_validation($object, $validate_url, $entity . '.' . $function);
        $object->config('doctrine.entity.manager', $connection->manager);
        foreach ($data as $node) {
            if(File::exist($validate_url)) {
                $data_node = new Data($node);
                $validate = Entity::validate($object, $validation, $data_node->data());
                if ($validate) {
                    if ($validate->success === true) {
                        $class = $connection->manager->find($object->config('doctrine.entity.prefix') . $entity, $node->id);
                        $node = Entity::import(
                            $class,
                            $node
                        );
                        $connection->manager->persist($node);
                        $connection->manager->flush();
                        $nodes[] = $node;
                    } else {
                        $error[] = $validate->test;
                    }
                } else {
                    $error[] = (object) [
                        'success' => false,
                    ];
                }
            }
        }
        return $nodes;
    }

    private static function import($node, $data=[]){
        foreach($data as $key => $value){
            if($key === 'request'){
                continue;
            }
            if(
                in_array(
                    substr($key, 0, 5),
                    [
                        'node_',
                        'node.'
                    ]
                )
            ){
                $explode = explode('_', substr($key, 5));
                foreach($explode as $nr => $part){
                    $explode[$nr] = ucfirst($part);
                }
                $method = 'set' . implode($explode);
                if(method_exists($node, $method)){
                    $node->$method($value);
                }
            } else {
                $explode = explode('_', $key);
                foreach($explode as $nr => $part){
                    $explode[$nr] = ucfirst($part);
                }
                $method = 'set' . implode($explode);
                if(method_exists($node, $method)){
                    $node->$method($value);
                }
            }
        }
        return $node;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function readById(App $object, object $connection, object $role, string $entity, int $id): mixed
    {
        $function = 'read';
        $node = $connection->manager->find($object->config('doctrine.entity.prefix') . $entity, $id);
        if($node) {
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.read.output'
            );
            $record = Entity::expose(
                $object,
                $role,
                $node,
                $toArray,
                $entity,
                $function,
            );
            $className = $object->config('doctrine.entity.prefix') . $entity;
            $class = new $className();
            if(method_exists($class, 'setObject')){
                $class->setObject($object);
            }
            if(method_exists($class, 'setEntityManager')){
                $class->setEntityManager($connection->manager);
            }
            ddd($record);
            $node = Entity::import(
                $class,
                $record->data()
            );
            return $node;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with id: ' . $id);
    }

    public static function readByUuid(App $object, object $connection, object $role, string $entity, string $uuid): array
    {
        $function = 'read';
        $repository = $connection->manager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'uuid' => $uuid
        ]);
        if($node) {
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.read.output'
            );
            $record = Entity::expose(
                $object,
                $role,
                $node,
                $toArray,
                $entity,
                $function,
            );
            $data['node'] = $record;
            return $data;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with uuid: ' . $uuid);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     * @throws AuthorizationException
     */
    public static function expose(App $object, $role, $node, $expose=[], $class='', $function=''): Data
    {
        if (!is_array($expose)) {
            return new Data();
        }
        $record = [];
        $is_expose = false;
        $permissions = $role->permission;
        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                foreach ($expose as $action) {
                    if (
                        (
                            $permission->name === str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function) &&
                            property_exists($action, 'role') &&
                            $action->role === $role->name
                        )
                        ||
                        (
                            in_array(
                                $function,
                                ['child', 'children'],
                                true
                            ) &&
                            property_exists($action, 'role') &&
                            $action->role === $role->name
                        )
                    ) {
                        $is_expose = true;
                        if (
                            property_exists($action, 'property') &&
                            is_array($action->property)
                        ) {

                            foreach ($action->property as $property) {
                                $is_optional = false;
                                if(substr($property, 0, 1) === '?'){
                                    $is_optional = true;
                                    $property = substr($property, 1);
                                }
                                $assertion = $property;
                                $explode = explode(':', $property, 2);
                                $compare = null;
                                $method = 'get' . ucfirst($explode[0]);
                                if (!method_exists($node, $method)) {
                                    continue;
                                }
                                $value = $node->{$method}();
                                if (array_key_exists(1, $explode)) {
                                    $record_property = $value;
                                    $compare = $explode[1];
                                    $attribute = $explode[0];
                                    if ($compare) {
                                        $parse = new Parse($object, $object->data());
                                        $compare = $parse->compile($compare, $object->data());
                                        if ($record_property !== $compare) {
                                            throw new Exception('Assertion failed: ' . $assertion . ' values [' . $record_property . ', ' . $compare . ']');
                                        }
                                    }
                                }
                                if (
                                    property_exists($action, 'object') &&
                                    property_exists($action->object, $property) &&
                                    property_exists($action->object->$property, 'expose')
                                ) {
                                    if (
                                        property_exists($action->object->$property, 'multiple') &&
                                        $action->object->$property->multiple === true &&
                                        $value !== null
                                    ) {
                                        $array = $value;

                                        if(is_array($array) || is_object($array)){
                                            $record[$property] = [];
                                            foreach ($array as $child) {
                                                $child = new Data($child);
                                                $child_expose =[];
                                                if(
                                                    property_exists($action->object->$property, 'object')
                                                ){
                                                    $child_expose[] = (object) [
                                                        'property' => $action->object->$property->expose,
                                                        'object' => $action->object->$property->object,
                                                        'role' => $action->role,
                                                    ];
                                                }  else {
                                                    $child_expose[] = (object) [
                                                        'property' => $action->object->$property->expose,
                                                        'role' => $action->role,
                                                    ];
                                                }
                                                $child = Entity::expose(
                                                    $object,
                                                    $role,
                                                    $child,
                                                    $child_expose,
                                                    $property,
                                                    'child'
                                                );
                                                $record[$property][] = $child->data();
                                            }
                                        } else {
                                            //leave intact for read without parse
                                            $record[$property] = $array;
                                        }
                                    } elseif ($value !== null) {
                                        $child = $value;
                                        if (!empty($child)) {
                                            $record[$property] = null;
                                            $child = new Data($child);
                                            $child_expose =[];
                                            if(
                                                property_exists($action->object->$property, 'objects')
                                            ){
                                                $child_expose[] = (object) [
                                                    'property' => $action->object->$property->expose,
                                                    'object' => $action->object->$property->objects,
                                                    'role' => $action->role,
                                                ];
                                            }  else {
                                                $child_expose[] = (object) [
                                                    'property' => $action->object->$property->expose,
                                                    'role' => $action->role,
                                                ];
                                            }
                                            $child = Entity::expose(
                                                $object,
                                                $role,
                                                $child,
                                                $child_expose,
                                                $property,
                                                'child',
                                            );
                                            $record[$property] = $child->data();
                                        }
                                        if (empty($record[$property])) {
                                            $record[$property] = null;
                                        }
                                    }
                                } else {
                                    if ($value !== null) {
                                        $record[$property] = $value;
                                    }
                                }
                            }
                            if(!empty($record)){
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        if($is_expose === false){
            throw new Exception('No permission found for ' . str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function));
        }
        return new Data((object) $record);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function expose_get(App $object, $entity='', $attribute=''){
        $dir = $object->config('project.dir.source') . 'Expose' . $object->config('ds');
        $url = $dir . $entity . $object->config('extension.json');
        if(!File::exist($url)){
            throw new Exception('Data url (' . $url . ') not found for entity: ' . $entity);
        }
        $data = $object->data_read($url);
        if($data){
            $get = $data->get($attribute);
            if(empty($get)){
                throw new Exception('Cannot find attribute (' . $attribute .') in entity: ' . $entity);
            }
            return $get;
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     * @throws AuthorizationException
     */
    public static function output(App $object, $node, $toArray=[], $entity='', $function='', $record=[], $internalRole=false): array
    {
        if(!is_array($toArray)){
            return $record;
        }
        if(
            method_exists($node, 'setObject') &&
            method_exists($node, 'getObject')
        ){
            $test = $node->getObject();
            if(empty($test)){
                $node->setObject($object);
            }
        }
        if($internalRole){
            $roles[] = $internalRole; //same as parent
        }
        if(empty($roles)){
            throw new Exception('Roles failed...');
        }
        $has_permission = false;
        foreach($roles as $role){
            if(
                property_exists($role, 'permission') &&
                property_exists($role, 'name') &&
                is_array($role->permission)
            ){
                $permissions = $role->permission;
                foreach ($permissions as $permission) {
                    if(property_exists($permission, 'name')){
                        foreach ($toArray as $action) {
                            if (
                                (
                                    $permission->name === $entity . ':' . $function &&
                                    property_exists($action, 'role') &&
                                    $action->role === $role->name
                                ) ||
                                (
                                    in_array(
                                        $function,
                                        ['child', 'children']
                                    ) &&
                                    property_exists($action, 'role') &&
                                    $action->role === $role->name
                                )
                            ) {
                                $has_permission = true;
                                if (
                                    property_exists($action, 'property') &&
                                    is_array($action->property)
                                ) {
                                    foreach ($action->property as $attribute) {
                                        $assertion = $attribute;
                                        $explode = explode(':', $attribute, 2);
                                        $compare = null;
                                        if (array_key_exists(1, $explode)) {
                                            $methods = explode('_', $explode[0]);
                                            foreach ($methods as $nr => $method) {
                                                $methods[$nr] = ucfirst($method);
                                            }
                                            $method = 'get' . implode('', $methods);
                                            $compare = $explode[1];
                                            $attribute = $explode[0];
                                            if ($compare) {
                                                $parse = new Parse($object, $object->data());
                                                $compare = $parse->compile($compare, $object->data());
                                                if ($node->$method() !== $compare) {
                                                    throw new Exception('Assertion failed: ' . $assertion . ' values [' . $node->$method() . ', ' . $compare . ']');
                                                }
                                            }
                                        } else {
                                            $methods = explode('_', $attribute);
                                            foreach ($methods as $nr => $method) {
                                                $methods[$nr] = ucfirst($method);
                                            }
                                            $method = 'get' . implode('', $methods);
                                        }
                                        if (
                                            property_exists($action, 'object') &&
                                            property_exists($action->object, $attribute) &&
                                            property_exists($action->object->$attribute, 'output')
                                        ) {
                                            if (
                                                property_exists($action->object->$attribute, 'multiple') &&
                                                $action->object->$attribute->multiple === true &&
                                                method_exists($node, $method)
                                            ) {
                                                $record[$attribute] = [];
                                                $array = $node->$method();
                                                $node_instance = false;
                                                $reflection = new \ReflectionClass($node);
                                                $methods_reflection = $reflection->getMethods();
                                                foreach($methods_reflection as $method_reflection){
                                                    if(
                                                        $method_reflection->name === $method
                                                    ) {
                                                        $attributes_reflection = $method_reflection->getAttributes();
                                                        foreach ($attributes_reflection as $attribute_reflection_nr => $attribute_reflection) {
                                                            $instance = $attribute_reflection->newInstance();
                                                            $instance->{"#class"} = get_class($instance);
                                                            $attributes_reflection[$attribute_reflection_nr] = $instance;
                                                            if($instance->{"#class"} === 'Raxon\Doctrine\Attribute\Node'){
                                                                $node_instance = $instance;
                                                            }
                                                        }
                                                    }
                                                }
                                                if($node_instance){
                                                    $item = new Node($object);
                                                    $response = $item->list($node_instance->class, $internalRole, [
                                                        'where' => [
                                                            [
                                                                'attribute' => 'uuid',
                                                                'operator' => 'in',
                                                                'value' => $array
                                                            ]
                                                        ],
                                                        'relation' => $node_instance->relation ?? false,
                                                    ]);
                                                    $array = $response['list'] ?? [];
                                                }
                                                foreach ($array as $child) {
                                                    if(property_exists($child, '#class')){
                                                        $child_record = $child;
                                                    } else {
                                                        //need to add the node output if its a node (has property #class)
                                                        //below needed for sql joins
                                                        $child_entity = explode('Entity\\', get_class($child));
                                                        $child_record = [];
                                                        $child_record = Entity::output(
                                                            $object,
                                                            $child,
                                                            $action->object->$attribute->output,
                                                            $child_entity[1],
                                                            'children',
                                                            $child_record,
                                                            $role,
                                                        );
                                                    }
                                                    $record[$attribute][] = $child_record;
                                                }
                                            } elseif (
                                                method_exists($node, $method)
                                            ) {
                                                $record[$attribute] = [];
                                                $child = $node->$method();
                                                $node_instance = false;
                                                $reflection = new \ReflectionClass($node);
                                                $methods_reflection = $reflection->getMethods();
                                                foreach($methods_reflection as $method_reflection){
                                                    if(
                                                        $method_reflection->name === $method
                                                    ) {
                                                        $attributes_reflection = $method_reflection->getAttributes();
                                                        foreach ($attributes_reflection as $attribute_reflection_nr => $attribute_reflection) {
                                                            $instance = $attribute_reflection->newInstance();
                                                            $instance->{"#class"} = get_class($instance);
                                                            $attributes_reflection[$attribute_reflection_nr] = $instance;
                                                            if($instance->{"#class"} === 'Raxon\Doctrine\Attribute\Node'){
                                                                $node_instance = $instance;
                                                            }
                                                        }
                                                    }
                                                }
                                                if (!empty($child)) {
                                                    if($node_instance){
                                                        $item = new Node($object);
                                                        $response = $item->record($node_instance->class, $internalRole, [
                                                            'where' => [
                                                                [
                                                                    'attribute' => 'uuid',
                                                                    'operator' => '===',
                                                                    'value' => $child
                                                                ]
                                                            ],
                                                            'relation' => $node_instance->relation ?? false,
                                                        ]);
                                                        $record[$attribute] = $response['node'] ?? null;
                                                    } else {
                                                        $child_entity = explode('Entity\\', get_class($child));
                                                        $record[$attribute] = Entity::output(
                                                            $object,
                                                            $child,
                                                            $action->object->$attribute->output,
                                                            $child_entity[1],
                                                            'child',
                                                            $record[$attribute],
                                                            $role,
                                                        );
                                                    }
                                                }
                                                if ($record[$attribute] === '') {
                                                    $record[$attribute] = null;
                                                }
                                            }
                                        } else {
                                            if (method_exists($node, $method)) {
                                                $record[$attribute] = $node->$method();
                                            }
                                        }
                                    }
                                }
                                break 3;
                            }
                        }
                    }
                }
            }
        }
        if($has_permission === false){
            throw new AuthorizationException('No permission found for ' . $entity . ':' . $function);
        }
        return $record;
    }
}
