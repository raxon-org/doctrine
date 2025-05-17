<?php
namespace Raxon\Doctrine\Module;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;

use Raxon\App;
use Raxon\Config;

use Raxon\Doctrine\Service\Main;
use Raxon\Exception\AuthorizationException;
use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\Database;
use Raxon\Module\File;
use Raxon\Module\Limit;
use Raxon\Module\Parse;

use Raxon\Exception\FileWriteException;
use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Node\Module\Node;
use ReflectionObject;


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
    public static function get_validation(App $object, $url, $type): object | false
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
    public static function patch(App $object, object $connection, object $role, object $request, object &$error = null): ?object
    {
        $entity = $object->request('entity');
        $data = [];
        $data[] = $request;
        $error = [];
        $response = Entity::patch_many($object, $connection, $role, $data, $error);
        $error = $error[0] ?? null;
        return $response[0] ?? null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function patch_many(App $object, object $connection, object $role, array $data, array &$error=[]): array
    {
        $function = 'patch';
        $entity = $object->request('entity');
        $nodes = [];
        $validate_url = Entity::get_validate_url($object, $entity);
        d($entity);
        d($validate_url);
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
                        ddd($node);
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
        $role = false;
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
                                                        d($child);
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
            if($role){
                throw new AuthorizationException('No permission found or missing exposure for role: "' . $role->name . '", permission: "' . $entity . ':' . $function . '"');
            } else {
                throw new AuthorizationException('No permission found or missing exposure for role "ROLE_UNDEFINED"; permission: "' . $entity . ':' . $function . '"');
            }

        }
        return $record;
    }

    /**
     * @throws ObjectException
     * @throws QueryException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public static function record(App $object, EntityManager $entityManager, $role, $options=[]): array
    {
        $object->request('limit', 1);
        $list = Entity::list(
            $object,
            $entityManager,
            $role,
            $options
        );
        $record = $list;
        $record['node'] = $list['nodeList'][0] ?? null;
        unset($record['nodeList']);
        return $record;
    }

    /**
     * @throws ObjectException
     * @throws QueryException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public static function list(App $object, EntityManager $entityManager, $role, $options=[]): array
    {
        if(is_array($options)){
            $options = Core::object($options, Core::OBJECT);
        }
        if(!property_exists($options, 'function')){
            $options->function = __FUNCTION__;
        }
        if(!property_exists($options, 'fetchJoinCollection')){
            $options->fetchJoinCollection = true;
        }
        $entity = $object->request('entity');
        $pagination = $object->request('pagination');
        $filter = Entity::filter($object, $where, $parameters);
        $order = Core::object($object->request('order'), Core::OBJECT_ARRAY);
        $alias = lcfirst($entity);
        $data = [];
        if(
            $pagination === false ||
            $pagination === 'false'
        ){
            $data['nodeList'] = [];
            $qb = $entityManager->createQueryBuilder();
            $entityName = $object->config('doctrine.entity.prefix') . $entity;
            $joins = Entity::get_joins($object, $entity);
            $qb->select(['count(' . $alias . '.id)'])
                ->from($entityName, $alias);
            foreach($joins as $join){
                $qb->leftJoin($join['join'], $join['alias']);
                foreach($where as $nr => $is){
                    $where[$nr] = str_replace($join['join'], $join['alias'], $is);
                }
            }
            if(is_array($where)){
                $count_where = count($where);
                if($count_where >= 1){
                    $qb->where($where[0]);
                    if($count_where > 1){
                        for($i = 1; $i < $count_where; $i++){
                            $qb->andWhere($where[$i]);
                        }
                    }
                }
            }
            $count = $qb->setParameters($parameters)
                ->getQuery()
                ->getSingleScalarResult();
            $data['count'] = (int) $count;
            $qb = $entityManager->createQueryBuilder();
            $qb->select([$alias])
                ->from($entityName, $alias);
            foreach($joins as $join){
                $qb->leftJoin($join['join'], $join['alias']);
            }
            $count_where = count($where);
            if($count_where >= 1){
                $qb->where($where[0]);
                if($count_where > 1){
                    for($i = 1; $i < $count_where; $i++){
                        $qb->andWhere($where[$i]);
                    }
                }
            }
            foreach($order as $key => $value){
                $qb->orderBy($alias . '.' . $key, mb_strtoupper($value));
            }
            $qb->setParameters($parameters);
            $result = $qb->getQuery()->getResult();
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.' . $options->function .'.output'
            );
            foreach($result as $node){
                $record = [];
                $record = Entity::output(
                    $object,
                    $node,
                    $toArray,
                    $entity,
                    $options->function,
                    $record,
                    $role
                );
                $data['nodeList'][] = $record;
            }
            $data['filter'] = Entity::castValue($filter);
            $data['order'] = $object->request('order');
        } else {
            if($object->request('page')){
                $page = (int) $object->request('page');
            } else {
                $page = 1;
            }
            $limit = Limit::LIMIT;
            /*
            $settings_url = $object->config('controller.dir.data') . 'Settings' . $object->config('extension.json');
            $settings =  $object->data_read($settings_url);
            if($settings){
                if($settings->data('component.default.limit')){
                    $limit = $settings->data('component.default.limit');
                }
            }
            */
            if($object->request('limit')){
                $limit = (int) $object->request('limit');
                if($limit > Limit::MAX){
                    $limit = Limit::MAX;
                }
            }
            $firstResult = $page * $limit - $limit;
            $data['nodeList'] = [];
            $qb = $entityManager->createQueryBuilder();
            $entityName = $object->config('doctrine.entity.prefix') . $entity;
            $joins = Entity::get_joins($object, $entity);
            $qb->select(['count(' . $alias . '.id)'])
                ->from($entityName, $alias);
            foreach($joins as $join){
                $qb->leftJoin($join['join'], $join['alias']);
                foreach($where as $nr => $is){
                    $where[$nr] = str_replace($join['join'], $join['alias'], $is);
                }
            }
            if(is_array($where)){
                $count_where = count($where);
                if($count_where >= 1){
                    $qb->where($where[0]);
                    if($count_where > 1){
                        for($i = 1; $i < $count_where; $i++){
                            $qb->andWhere($where[$i]);
                        }
                    }
                }
            }
            $qb->setParameters($parameters);
            $count = $qb
                ->getQuery()
                ->getSingleScalarResult();
            $data['count'] = (int) $count;
            $data['page'] = $page;
            $data['limit'] = $limit;
            $qb = $entityManager->createQueryBuilder();
            $qb->select([$alias])
                ->from($entityName, $alias);
            foreach($joins as $join){
                $qb->leftJoin($join['join'], $join['alias']);
            }
            $count_where = count($where);
            if($count_where >= 1){
                $qb->where($where[0]);
                if($count_where > 1){
                    for($i = 1; $i < $count_where; $i++){
                        $qb->andWhere($where[$i]);
                    }
                }
            }
            foreach($order as $key => $value){
                $qb->orderBy($alias . '.' . $key, mb_strtoupper($value));
            }
            $qb->setParameters($parameters)
                ->setFirstResult($firstResult)
                ->setMaxResults($limit);
            $paginator = new Paginator($qb->getQuery(), $options->fetchJoinCollection);
            $expose = Entity::expose_get(
                $object,
                $entity,
                $entity . '.'. $options->function . '.output'
            );
            foreach ($paginator as $node) {
                $record = [];
                $record = Entity::output(
                    $object,
                    $node,
                    $expose,
                    $entity,
                    $options->function,
                    $record,
                    $role
                );
                $data['nodeList'][] = $record;
            }
            $data['max'] = (int) ceil($data['count'] / $data['limit']);
            $data['filter'] = Entity::castValue($filter);
            $data['order'] = $object->request('order');
        }
        return $data;
    }

    /**
     * @throws ObjectException
     * @throws QueryException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public static function page(App $object, $entity, $id): array
    {
        $request = Permission::request($object, $entity, 'page');
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $object->request('delete', 'id');
        $filter = Entity::filter($object, $where, $parameters);
        $order = Core::object($object->request('order'), Core::OBJECT_ARRAY);
        $alias = lcfirst($entity);
        $data = [];
        $limit = Limit::LIMIT;
        $settings_url = $object->config('controller.dir.data') . 'Settings' . $object->config('extension.json');
        $settings =  $object->data_read($settings_url);
        if($settings){
            if($settings->data('component.default.limit')){
                $limit = $settings->data('component.default.limit');
            }
        }
        if($object->request('limit')){
            $limit = (int) $object->request('limit');
            if($limit > Limit::MAX){
                $limit = Limit::MAX;
            }
        }
        $page = 1;
        $firstResult = $page * $limit - $limit;
        $qb = $entityManager->createQueryBuilder();
        $entityName = $object->config('doctrine.entity.prefix') . $entity;
        $joins = Entity::get_joins($object, $entity);
        $qb->select(['count(' . $alias . '.id)'])
            ->from($entityName, $alias);
        foreach($joins as $join){
            $qb->leftJoin($join['join'], $join['alias']);
            foreach($where as $nr => $is){
                $where[$nr] = str_replace($join['join'], $join['alias'], $is);
            }
        }
        if(is_array($where)){
            $count_where = count($where);
            if($count_where >= 1){
                $qb->where($where[0]);
                if($count_where > 1){
                    for($i = 1; $i < $count_where; $i++){
                        $qb->andWhere($where[$i]);
                    }
                }
            }
        }
        $qb->setParameters($parameters);
        $count = $qb
            ->getQuery()
            ->getSingleScalarResult();
        $data['count'] = $count;
        $data['limit'] = $limit;
        $data['max'] = ceil($data['count'] / $data['limit']);
        $qb = $entityManager->createQueryBuilder();
        $qb->select([$alias])
            ->from($entityName, $alias);
        foreach($joins as $join){
            $qb->leftJoin($join['join'], $join['alias']);
        }
        $count_where = count($where);
        if($count_where >= 1){
            $qb->where($where[0]);
            if($count_where > 1){
                for($i = 1; $i < $count_where; $i++){
                    $qb->andWhere($where[$i]);
                }
            }
        }
        foreach($order as $key => $value){
            $qb->orderBy($alias . '.' . $key, mb_strtoupper($value));
        }
        $qb->setParameters($parameters)
            ->setFirstResult($firstResult)
            ->setMaxResults($limit);

        $is_found = false;
        $fetchJoinCollection = false;

        for($page=1; $page <= $data['max']; $page++){
            $firstResult = $page * $limit - $limit;
            $qb->setFirstResult($firstResult)
                ->setMaxResults($limit);
            $paginator = new Paginator($qb->getQuery(), $fetchJoinCollection);
            foreach ($paginator as $entity) {
                if($entity->getId() === $id){
                    $is_found = true;
                    break 2;
                }
            }
        }
        if($is_found){
            $data['page'] = $page;
        } else {
            throw new Exception('Item not found with id: ' . $id);
        }
        $data['filter'] = Entity::castValue($filter);
        $data['order'] = $object->request('order');
        return $data;
    }

    /**
     * @throws ObjectException
     */
    protected static function castValue($array=[]): mixed
    {
        if(is_array($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array[$key] = Entity::castValue($value);
                } else {
                    if($value === 'null'){
                        $array[$key] = null;
                    }
                    elseif($value === 'true'){
                        $array[$key] = true;
                    }
                    elseif($value === 'false'){
                        $array[$key] = false;
                    }
                    elseif(is_numeric($value)){
                        $array[$key] = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array[$key] = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif(is_object($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array->$key = Entity::castValue($value);
                } else {
                    if($value === 'null'){
                        $array->$key = null;
                    }
                    elseif($value === 'true'){
                        $array->$key = true;
                    }
                    elseif($value === 'false'){
                        $array->$key = false;
                    }
                    elseif(is_numeric($value)){
                        $array->$key = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array->$key = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif($array === 'null'){
            return null;
        }
        elseif($array === 'true'){
            return true;
        }
        elseif($array === 'false'){
            return false;
        }
        elseif(is_numeric($array)){
            return $array + 0;
        }
        elseif(substr($array, 0, 1) === '[' && substr($array, -1, 1) === ']'){
            return Core::object($array, Core::OBJECT_ARRAY);
        }
        else {
            return $array;
        }
    }

    /**
     * @throws ObjectException
     * @throws \ReflectionException
     */
    private static function filter(App $object, &$where=[], ArrayCollection &$parameters=null){
        $request = $object->request('filter') ?? [];
        $alias = lcfirst($object->request('entity'));
        $filter = $request ?? [];
        $where = [];
        $parameters = [];
        foreach($request as $attribute => $array){
            if(substr($attribute, 0, 1) === '@'){
                $attribute = substr($attribute, 1);
            }
            $is_not = false;
            if(is_object($array)){
                $array = Core::object_array($array);
            }
            if(Core::is_array_nested($array)){
                if(array_key_exists('not', $array)){
                    $is_not = true;
                    $array = $array['not'];
                }
            }
            $array = Entity::castValue($array);
            if(is_array($array)){
                if(count($array) > 1){
                    foreach($array as $key => $value){
                        if($key === 'gte') {
                            $where[] = $alias . '.' . $attribute .' >= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                            unset($array[$key]);
                        }
                        elseif($key === 'lte') {
                            $where[] = $alias . '.' . $attribute .' <= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                            unset($array[$key]);
                        }
                        elseif($key === 'gt') {
                            $where[] = $alias . '.' . $attribute .' > :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                            unset($array[$key]);
                        }
                        elseif($key === 'lt') {
                            $where[] = $alias . '.' . $attribute .' < :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                            unset($array[$key]);
                        }
                    }
                    if(!empty($array)){
                        if($is_not){
                            $where[] = $alias . '.' . $attribute . ' NOT IN (:' . $attribute . ')';
                        } else {
                            $where[] = $alias . '.' . $attribute . ' IN (:' . $attribute . ')';
                        }
                        $parameters[$attribute] = $array;
                    }
                } else {
                    foreach($array as $key => $value){
                        if(is_numeric($key)){
                            if($value === null){
                                $where[] = $alias . '.' . $attribute . ' IS NULL';
                            }
                            elseif(is_array($value)){
                                $where[] = $alias . '.' . $attribute . ' IN (:' . $attribute . ')';
                                $parameters[$attribute] = $value;
                            } else {
                                $where[] = $alias . '.' . $attribute . ' = :' . $attribute;
                                $parameters[$attribute] = $value;
                            }
                        }
                        elseif($key === 'not'){
                            if($value === null) {
                                $where[] = $alias . '.' . $attribute . ' IS NOT NULL';
                            }
                            elseif(is_array($value)){
                                $where[] = $alias . '.' . $attribute . ' NOT IN (:' . $attribute . ')';
                                $parameters[$attribute] = $value;
                            } else {
                                $where[] = $alias . '.' . $attribute . ' != :' . $attribute;
                                $parameters[$attribute] = $value;
                            }
                        }
                        elseif($key === 'exact'){
                            if($is_not){
                                $where[] = $alias . '.' . $attribute . ' != :' . $attribute . '_' . $key;
                            } else {
                                $where[] = $alias . '.' . $attribute . ' = :' . $attribute . '_' . $key;
                            }
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'partial'){
                            if($is_not){
                                $where[] = $alias . '.' . $attribute .' NOT LIKE :' . $attribute . '_' . $key;
                            } else {
                                $where[] = $alias . '.' . $attribute .' LIKE :' . $attribute . '_' . $key;
                            }

                            $parameters[$attribute . '_' . $key] = '%' . $value . '%';
                        }
                        elseif($key === 'start'){
                            if($is_not){
                                $where[] = $alias . '.' . $attribute .' NOT LIKE :' . $attribute . '_' . $key;
                            } else {
                                $where[] = $alias . '.' . $attribute . ' LIKE :' . $attribute . '_' . $key;
                            }
                            $parameters[$attribute . '_' . $key] = $value . '%';
                        }
                        elseif($key === 'end'){
                            if($is_not){
                                $where[] = $alias . '.' . $attribute .' NOT LIKE :' . $attribute . '_' . $key;
                            } else {
                                $where[] = $alias . '.' . $attribute . ' LIKE :' . $attribute . '_' . $key;
                            }
                            $parameters[$attribute . '_' . $key] = '%' . $value;
                        }
                        elseif($key === 'gte') {
                            $where[] = $alias . '.' . $attribute .' >= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'lte') {
                            $where[] = $alias . '.' . $attribute .' <= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'gt') {
                            $where[] = $alias . '.' . $attribute .' > :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'lt') {
                            $where[] = $alias . '.' . $attribute .' < :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'after'){
                            $value = strtotime($value);
                            $value = date('Y-m-d H:i:s', $value);
                            $where[] = $alias . '.' . $attribute .' >= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'before'){
                            $value = strtotime($value);
                            $value = date('Y-m-d H:i:s', $value);
                            $where[] = $alias . '.' . $attribute .' <= :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'strictly_after'){
                            $value = strtotime($value);
                            $value = date('Y-m-d H:i:s', $value);
                            $where[] = $alias . '.' . $attribute .' > :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;

                        }
                        elseif($key === 'strictly_before'){
                            $value = strtotime($value);
                            $value = date('Y-m-d H:i:s', $value);
                            $where[] = $alias . '.' . $attribute .' < :' . $attribute . '_' . $key;
                            $parameters[$attribute . '_' . $key] = $value;
                        }
                        elseif($key === 'between'){
                            $value = explode('..', $value, 2);
                            if(array_key_exists(1, $value)){
                                if(is_numeric($value[0])){
                                    $value[0] += 0;
                                }
                                if(is_numeric($value[1])){
                                    $value[1] += 0;
                                }
                                $where[] = $alias . '.' . $attribute .' > :' . $attribute . '_' . $key . '_' . 'gt';
                                $parameters[$attribute . '_' . $key. '_' . 'gt'] = $value[0];
                                if(is_numeric($value)){
                                    $value += 0;
                                }
                                $where[] = $alias . '.' . $attribute .' < :' . $attribute . '_' . $key . '_' . 'lt';
                                $parameters[$attribute . '_' . $key . '_' . 'lt'] = $value[1];
                            }
                        }
                        elseif($key === 'between-equals'){
                            $value = explode('..', $value, 2);
                            if(array_key_exists(1, $value)){
                                if(is_numeric($value[0])){
                                    $value[0] += 0;
                                }
                                if(is_numeric($value[1])){
                                    $value[1] += 0;
                                }
                                $where[] = $alias . '.' . $attribute .' >= :' . $attribute . '_' . $key . '_' . 'gte';
                                $parameters[$attribute . '_' . $key. '_' . 'gte'] = $value[0];
                                if(is_numeric($value)){
                                    $value += 0;
                                }
                                $where[] = $alias . '.' . $attribute .' <= :' . $attribute . '_' . $key . '_' . 'lte';
                                $parameters[$attribute . '_' . $key . '_' . 'lte'] = $value[1];
                            }
                        }
                    }
                }
            } else {
                $value = $array;
                if($value === null){
                    $where[] = $alias . '.' . $attribute . ' IS NULL';
                }
                elseif(is_array($value)){
                    $where[] = $alias . '.' . $attribute . ' IN (:' . $attribute . ')';
                    $parameters[$attribute] = $value;
                }
                elseif($alias) {
                    $where[] = $alias . '.' . $attribute . ' = :' . $attribute;
                    $parameters[$attribute] = $value;
                }
            }
        }
        foreach($parameters as $key => $parameter){
            $parameters[$key] = new Parameter($key, $parameter);
        }
        $parameters = new ArrayCollection($parameters);
        return $filter;
    }

    /**
     * @throws Exception
     */
    private static function has_joins(App $object, $entity): array
    {
        $entityName = $object->config('doctrine.entity.prefix') . $entity;
        $reflection = new ReflectionObject(new $entityName());
        $properties = $reflection->getProperties();
        $reader = new AttributeReader();
        //must become attribute reader
        $has_join = [];
        foreach ($properties as $property) {
            $attributes = $reader->getPropertyAttributes($property);
            foreach ($attributes as $attribute) {
                if (in_array(get_class($attribute), [
                    OneToMany::class,
                    ManyToOne::class,
                    ManyToMany::class,
                    OneToOne::class
                ])) {
                    $has_join[] = $property->getName();
                }
            }
        }
        return $has_join;
    }

    private static function get_joins(App $object, $entity): array
    {
        $has_join = Entity::has_joins($object, $entity);
        $joins = [];
        $alias = lcfirst($entity);
        foreach($object->request() as $attribute => $value){
            if(in_array($attribute, $has_join, true)){
                $joins[] = [
                    'join' => $alias . '.' . $attribute,
                    'alias' => $attribute
                ];
            }
            elseif(substr($attribute, 0, 1) === '@'){
                $joins[] = [
                    'join' => $alias . '.' . substr($attribute, 1),
                    'alias' => substr($attribute, 1)
                ];
            }
        }
        return $joins;
    }
}
