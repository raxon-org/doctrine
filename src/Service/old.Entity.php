<?php
namespace R3m\Io\Doctrine\Service;

use R3m\Io\App;

use R3m\Io\Module\Core;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\File;
use R3m\Io\Module\Parse;
use R3m\Io\Module\Controller;

use Exception;

use R3m\Io\Exception\AuthorizationException;
use R3m\Io\Exception\ObjectException;

class Entity extends Main
{

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
    /*
    public static function expose(App $object, $node, $toArray=[], $entity='', $function='', $record=[], $internalRole=false, $parentScope=false): array
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
        } else {
//            $roles = Permission::getAccessControl($object, $entity, $function);
            try {
                $user = User::getByAuthorization($object);
                if($user){
                    $roles = $user->getRolesByRank('asc');
                }
            } catch (Exception $exception){

            }
        }
        if(empty($roles)){
            throw new Exception('Roles failed...');
        }
        if(is_array($entity)){
            ddd($entity);
        }
        if(is_array($function)){
            $debug = debug_backtrace(true);
            ddd($debug[0]);
            ddd($function);

        }
        foreach($roles as $role){
            $permissions = $role->getPermissions();
            foreach ($permissions as $permission){
                if(is_array($permission)){
                    ddd($permission);
                }
                foreach($toArray as $action) {
                    if(
                        (
                            $permission->getName() === $entity . '.' . $function &&
                            property_exists($action, 'scope') &&
                            $action->scope === $permission->getScope()
                        ) ||
                        (
                            in_array(
                                $function,
                                ['child', 'children']
                            ) &&
                               property_exists($action, 'scope') &&
                            $action->scope === $parentScope
                        )
                    ) {
                        if (
                            property_exists($action, 'attributes') &&
                            is_array($action->attributes)
                        ) {
                            foreach ($action->attributes as $attribute) {
                                $assertion = $attribute;
                                $explode = explode(':', $attribute, 2);
                                $compare = null;
                                if (array_key_exists(1, $explode)) {
                                    $methods = explode('_', $explode[0]);
                                    foreach ($methods as $nr => $method) {
                                        $methods[$nr] = ucfirst($method);
                                    }
                                    $method = 'get' . implode($methods);
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
                                    $method = 'get' . implode($methods);
                                }
                                if (
                                    property_exists($action, 'objects') &&
                                    property_exists($action->objects, $attribute) &&
                                    property_exists($action->objects->$attribute, 'toArray')
                                ) {
                                    if (
                                        property_exists($action->objects->$attribute, 'multiple') &&
                                        $action->objects->$attribute->multiple === true &&
                                        method_exists($node, $method)
                                    ) {
                                        $record[$attribute] = [];
                                        $array = $node->$method();
                                        foreach ($array as $child) {
                                            $child_entity = explode('Entity\\', get_class($child));
                                            $child_record = [];
                                            $child_record = \Host\Api\Workandtravel\World\Service\Entity::toArray(
                                                $object,
                                                $child,
                                                $action->objects->$attribute->toArray,
                                                $child_entity[1],
                                                'children',
                                                $child_record,
                                                $role,
                                                $action->scope
                                            );
                                            $record[$attribute][] = $child_record;
                                        }
                                    } elseif (
                                        method_exists($node, $method)
                                    ) {
                                        $record[$attribute] = [];
                                        $child = $node->$method();
                                        if (!empty($child)) {
                                            $child_entity = explode('Entity\\', get_class($child));
                                            $record[$attribute] = Entity::toArray(
                                                $object,
                                                $child,
                                                $action->objects->$attribute->toArray,
                                                $child_entity[1],
                                                'child',
                                                $record[$attribute],
                                                $role,
                                                $action->scope
                                            );
                                        }
                                        if (empty($record[$attribute])) {
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
        return $record;
    }
    */

    /**
     * @throws ObjectException
     * @throws Exception
     * @throws AuthorizationException
     */
    public static function expose(App $object, $node, $expose=[], $class='', $function='', $internalRole=false, $parentRole=false): Storage
    {
        if (!is_array($expose)) {
            return new Storage();
        }
        $roles = [];
        if ($internalRole) {
            $roles[] = $internalRole; //same as parent
        } else {
//            $roles = Permission::getAccessControl($object, $class, $function);
            try {
                /*
                $user = User::getByAuthorization($object);
                if ($user) {
                    $roles = $user->getRolesByRank('asc');
                }
                */
            } catch (Exception $exception) {

            }
        }
        if (empty($roles)) {
            throw new Exception('Roles failed...');
        }
        $record = [];
        $is_expose = false;
        foreach ($roles as $role) {
            $permissions = $role->getPermissions();
            if (is_array($permissions)) {
                foreach ($permissions as $permission) {
                    if (is_array($permission)) {
                        ddd($permission);
                    }
                    echo $permission->getName() . PHP_EOL;
                    foreach ($expose as $action) {
                        if (
                            (
                                $permission->getName() === str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function) &&
                                property_exists($action, 'role') &&
                                $action->role === $role->getName()
                            )
                            ||
                            (
                                in_array(
                                    $function,
                                    ['child', 'children'],
                                    true
                                ) &&
                                property_exists($action, 'role') &&
                                $action->role === $parentRole
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
                                                    $child = new Storage($child);
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
                                                        $child,
                                                        $child_expose,
                                                        $property,
                                                        'child',
                                                        $role,
                                                        $action->role
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
                                                $child = new Storage($child);
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
                                                    $child,
                                                    $child_expose,
                                                    $property,
                                                    'child',
                                                    $role,
                                                    $action->role
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
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }
        if($is_expose === false){
            throw new Exception('No permission found for ' . str_replace('.', ':', Controller::name($class)) . ':' . str_replace('_', '.', $function));
        }
        return new Storage((object) $record);
    }


    /**
     * @throws Exception
     */
    /*
    public static function is_granted($class, $role, $options): bool
    {
        if(!array_key_exists('function', $options)){
            throw new Exception('Function is missing in options');
        }
        $name = Controller::name($class);
        $name_permission = str_replace('.', ':', $name);
        $function_permission = str_replace('_', '.', $options['function']);
        $role = new Data($role);
        $is_permission = false;
        $is_permission_relation = false;
        $is_permission_parse = false;
        $permissions = [];
        $permissions[] = $name_permission . ':' . $function_permission;
        if(
            array_key_exists('relation', $options) &&
            $options['relation'] === true
        ){
            $permissions[] = $name_permission . ':' . $function_permission . '.' . 'relation';
        }
        if(
            array_key_exists('parse', $options) &&
            $options['parse'] === true
        ){
            $permissions[] = $name_permission . ':' . $function_permission . '.' . 'parse';
        }
        $role_permissions = $role->get('permission');
        if(is_array($role_permissions)){
            foreach($role->get('permission') as $permission){
                $permission = new Data($permission);
                if($permission->get('name') === $name_permission . ':' .$function_permission){
                    $is_permission = true;
                }
                if(
                    array_key_exists('relation', $options) &&
                    $options['relation'] === true
                ){
                    if($permission->get('name') === $name_permission . ':' .$function_permission . '.' . 'relation'){
                        $is_permission_relation = true;
                    }
                } else {
                    $is_permission_relation = true;
                }
                if(
                    array_key_exists('parse', $options) &&
                    $options['parse'] === true
                ) {
                    if($permission->get('name') === $name_permission . ':' . $function_permission . '.' . 'parse'){
                        $is_permission_parse = true;
                    }
                } else {
                    $is_permission_parse = true;
                }
                if(
                    $is_permission === true &&
                    $is_permission_parse === true &&
                    $is_permission_relation === true
                ){
                    return true;
                }
            }
        }
        throw new Exception('Security: permission denied... (' . implode(', ', $permissions) . ')');
    }
    */
}