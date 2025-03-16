<?php
namespace Raxon\Doctrine\Module;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

use Exception;

use Raxon\App;

use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\File;
use Raxon\Module\Parse;

use Raxon\Exception\ObjectException;

class Entity {

    public static function create(App $object, EntityManager $em, object $role, string $entity, array $request): ?object
    {
        $data = [];
        $data[] = $request;
        $response = Entity::create_many($object, $em, $role, $entity, $data);
        return $response[0] ?? null;
    }

    public static function create_many(App $object, EntityManager $em, object $role, string $entity, array $data): array
    {
ddd($data);
        $function = 'create';
        $nodes = [];
        foreach ($data as $record) {
            $node = new $entity();
            $node = Entity::expose(
                $object,
                $role,
                $node,
                $record,
                $entity,
                $function,
            );
            $em->persist($node);
            $em->flush();
            $nodes[] = $node;
        }
        return $nodes;
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
