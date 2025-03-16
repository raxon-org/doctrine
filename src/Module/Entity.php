<?php
namespace Raxon\Doctrine\Module;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

use Exception;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\File;
use Raxon\Module\Parse;

use Raxon\Exception\FileWriteException;
use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;


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

    public static function create(App $object, EntityManager $em, object $role, string $entity, object $request): ?object
    {
        $data = [];
        $data[] = $request;
        $response = Entity::create_many($object, $em, $role, $entity, $data);
        return $response[0] ?? null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function create_many(App $object, EntityManager $em, object $role, string $entity, array $data): array
    {
        $function = 'create';
        $nodes = [];
        $validate_url = Entity::get_validate_url($object, $entity);
        $validation = Entity::get_validation($object, $validate_url, $entity . '.create');
        $object->config('doctrine.entity.manager', $em);
        foreach ($data as $node) {
            if(File::exist($validate_url)) {
                $data = new Data($node);
                $validate = Entity::validate($object, $validation, $data->data());
                if ($validate) {
                    if ($validate->success === true) {
                        $className = $object->config('doctrine.entity.prefix') . $entity;
                        $class = new $className();
                        if(method_exists($class, 'setObject')){
                            $class->setObject($object);
                        }
                        if(method_exists($class, 'setEntityManager')){
                            $class->setEntityManager($em);
                        }
                        $node = Entity::import(
                            $class,
                            $node
                        );
                        ddd($node);
                        $em->persist($record);
                        $em->flush();
                        $nodes[] = $node;
                    }
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
    public static function readById(App $object, EntityManager $em, object $role, string $entity, int $id){
        $function = 'read';
        $node = $em->find($object->config('doctrine.entity.prefix') . $entity, $id);
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
        throw new Exception('Cannot find entity: ' . $entity .', with id: ' . $id);
    }

    public static function readByUuid(App $object, EntityManager $em, object $role, string $entity, string $uuid): array
    {
        $function = 'read';
        $repository = $em->getRepository($object->config('doctrine.entity.prefix') . $entity);
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
        ddd($node);
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
}
