<?php
namespace Raxon\Doctrine\Service;


use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use Doctrine\ORM\Query\Parameter;
use Entity\Role;
use ReflectionObject;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Tools\Pagination\Paginator;

use Host\Api\Workandtravel\World\Service\User as UserService;

use Raxon\App;
use Raxon\Module\Core;
use Raxon\Module\Database;
use Raxon\Module\File;
use Raxon\Module\Limit;
use Raxon\Module\Parse;

use Exception;

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query\QueryException;

use Raxon\Exception\AuthorizationException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\FileWriteException;
use Repository\PermissionRepository;


class Entity extends Main
{

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     * @throws ObjectException
     * @throws AuthorizationException
     * @throws FileWriteException
     */
    public static function create(App $object, EntityManager $entityManager, Role $role, $entity=null, $request=[]): array
    {
        $function = __FUNCTION__;
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        $validate_url = Entity::getValidatorUrl($object, $entity);
        if(File::exist($validate_url)){
            $validate = Main::validate($object, $validate_url,  $entity . '.create');
            if($validate) {
                $data = [];
                if($validate->success === true) {
                    $className = $object->config('doctrine.entity.prefix') . $entity;
                    $class = new $className();
                    if(method_exists($class, 'setObject')){
                        $class->setObject($object);
                    }
                    if(method_exists($class, 'setEntityManager')){
                        $class->setEntityManager($entityManager);
                    }
                    $node = Entity::import(
                        $class,
                        $request
                    );
                    try {
                        $entityManager->persist($node);
                        $entityManager->flush();
                    }
                    catch(Exception $exception){
                        throw new Exception('Cannot create entity: ' . $entity . ', error: ' . $exception->getMessage());
                    }
                    $data['node'] = $node;
                } else {
                    $data['error'] = $validate->test;
                }
                return $data;
            } else {
                throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
            }
        }
        throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
    }


    /**
     * @throws ObjectException
     * @throws ORMException
     * @throws \Doctrine\ORM\ORMException
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws \Doctrine\DBAL\Exception
     */
    public static function readByUuid(App $object, $entity, $uuid): array
    {
        $function = 'read';
        Permission::request($object, $entity, $function);
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'uuid' => $uuid
        ]);
        if($node) {
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.read.expose'
            );
            $record = Entity::expose(
                $object,
                $node,
                $toArray,
                $entity,
                $function,
                $record
            );
            $data['node'] = $record;
            return $data;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with uuid: ' . $uuid);
    }

    /**
     * @throws NotSupported
     * @throws Exception
     */
    public static function readById(App $object, EntityManager $entityManager, $entity, $id): array
    {
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'id' => $id
        ]);
        if($node) {
            $data = [];
            $data['node'] = $node;
            return $data;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with id: ' . $id);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function update(App $object, $entityManager, $node, $entity): array
    {
        $function = __FUNCTION__;
        $request = Permission::request($object, $entity, $function);
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        if(count($request) <= 1){
            throw new Exception('Request need more data...');
        }
        $validate_url = Entity::getValidatorUrl($object, $entity);
        if(File::exist($validate_url)) {
            $validate = Main::validate($object, $validate_url, $entity . '.update');
            if ($validate) {
                if (
                    property_exists($validate, 'success') &&
                    $validate->success === true
                ) {
                    if(method_exists($node, 'setObject')){
                        $node->setObject($object);
                    }
                    if(method_exists($node, 'setEntityManager')){
                        $node->setEntityManager($entityManager);
                    }
                    $node = Entity::import(
                        $node,
                        $request
                    );
                    $entityManager->persist($node);
                    $entityManager->flush();
                    $data = [];
                    $record = [];
                    $toArray = Entity::expose_get(
                        $object,
                        $entity,
                        $entity . '.update.expose'
                    );
                    $record = Entity::expose(
                        $object,
                        $node,
                        $toArray,
                        $entity,
                        $function,
                        $record
                    );
                    $data['node'] = $record;
                    return $data;
                }
                elseif(property_exists($validate, 'test')) {
                    $data = [];
                    $data['error'] = $validate->test;
                    return $data;
                } else {
                    $data = [];
                    $data['error'] = 'Invalid validate, url: ' . $validate_url;
                    return $data;
                }
            } else {
                $data = [];
                $data['error'] = 'Invalid validate, url: ' . $validate_url;
                return $data;
            }
        }
        throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));

    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function updateByUuid(App $object, $entity, $uuid): array
    {
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'uuid' => $uuid
        ]);
        if($node) {
            $update = Entity::update($object, $entityManager, $node, $entity);
            if($update){
                return $update;
            }
        }
        throw new Exception('Cannot find entity: ' . $entity .', with uuid: ' . $uuid);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function updateById(App $object, $entity, $id): array
    {
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'id' => $id
        ]);
        if($node) {
            $update = Entity::update($object, $entityManager, $node, $entity);
            if($update){
                return $update;
            }
        }
        throw new Exception('Cannot find entity: ' . $entity .', with id: ' . $id);
    }

    /**
     * @throws ObjectException
     * @throws Raxon\Exception\ObjectException
     * @throws Doctrine\ORM\Exception\ORMException
     * @throws Raxon\Exception\AuthorizationException
     * @throws Raxon\Exception\FileWriteException
     * @throws Exception
     */
    private static function delete(App $object, $entityManager, $node, $entity): array
    {
        $function = __FUNCTION__;
        $request = Permission::request($object, $entity, $function);
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        if(count($request) < 1){
            throw new Exception('Request need more data...');
        }
        if(method_exists($node, 'setIsDeleted')){
            $node->setIsDeleted(new DateTime());
            $entityManager->persist($node);
            $entityManager->flush();
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.delete.expose'
            );
            $record = Entity::expose(
                $object,
                $node,
                $toArray,
                $entity,
                $function,
                $record
            );
            $data['node'] = $record;
            return $data;
        } else {
            $entityManager->remove($node);
            $entityManager->flush();
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.delete.expose'
            );
            $record = Entity::expose(
                $object,
                $node,
                $toArray,
                $entity,
                $function,
                $record
            );
            $data['node'] = $record;
            $data['node']['isDeleted'] = new DateTime();
            return $data;
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function deleteByUuid(App $object, $entity, $uuid): array
    {
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'uuid' => $uuid
        ]);
        //add user id
        if($node) {
            $delete = Entity::delete($object, $entityManager, $node, $entity);
            return $delete;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with uuid: ' . $uuid);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function deleteById(App $object, $entity, $id): array
    {
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        $node = $repository->findOneBy([
            'id' => $id
        ]);
        if($node) {
            $delete = Entity::delete($object, $entityManager, $node, $entity);
            return $delete;
        }
        throw new Exception('Cannot find entity: ' . $entity .', with id: ' . $id);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function add(App $object, $entity1=null, $entity2=null): array
    {
        return Entity::add_or_set($object, $entity1, $entity2, 'add');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function set(App $object, $entity1=null, $entity2=null): array
    {
        return Entity::add_or_set($object, $entity1, $entity2, 'set');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function add_or_set(App $object, $entity1=null, $entity2=null, $function='add'): array
    {
        $request = Permission::request($object, $entity1 . '.' . $entity2, $function);
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $entity = $entity1 . '.' . $entity2;
        $type = $entity1 . '.' . $entity2;
        $validate_url = Entity::getValidatorUrl($object, $entity);
        if(!File::exist($validate_url)) {
            $entity = $entity2 . '.' . $entity1;
            $type = $entity2 . '.' . $entity1;
            $validate_url = Entity::getValidatorUrl($object, $entity);
            if(!File::exist($validate_url)) {
                throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
            }
        }
        $validate = Main::validate($object, $validate_url,  $type . '.' . $function);
        if($validate) {
            if(
                property_exists($validate, 'success') &&
                $validate->success === true
            ) {
                $entityName1 = $object->config('doctrine.entity.prefix') . $entity1;
                $repository = $entityManager->getRepository($entityName1);
                $id = lcfirst($entity1) . '_id';
                if(array_key_exists($id, $request)){
                    $node1 = $repository->findOneBy([
                        'id' => $request[$id]
                    ]);
                    if(empty($node1)){
                        throw new Exception('Could not find entity (' . $entity1 . ') with id: ' . $request[$id]);
                    }
                } else {
                    throw new Exception('Could not find request with id: ' . $id);
                }
                $entityName2 = $object->config('doctrine.entity.prefix') . $entity2;
                $repository = $entityManager->getRepository($entityName2);
                $id = lcfirst($entity2) . '_id';
                if(array_key_exists($id, $request)){
                    $node2 = $repository->findOneBy([
                        'id' => $request[$id]
                    ]);
                    if(empty($node2)){
                        throw new Exception('Could not find entity (' . $entity2 . ') with id: ' . $request[$id]);
                    }
                } else {
                    throw new Exception('Could not find request with id: ' . $id);
                }
                $method = $function . ucfirst($entity2);
                if(method_exists($node1, $method)){
                    $node1->$method($node2);
                    $entityManager->persist($node1);
                    $entityManager->flush();
                } else {
                    throw new Exception('Entity: ' . $entity1 . ' does not have this method: ' . $method);
                }
                $entity = $entity1 . '.' . $entity2;
                $data = [];
                $record = [];
                $toArray = Entity::expose_get(
                    $object,
                    $entity,
                    $entity . '.' . $function .'.expose'
                );
                $record = Entity::expose(
                    $object,
                    $node1,
                    $toArray,
                    $entity,
                    $function,
                    $record
                );
                $data['node'] = $record;
            }
            elseif(property_exists($validate, 'test')) {
                $data = [];
                $data['error'] = $validate->test;
            } else {
                throw new Exception('Invalid validate, url: ' . $validate_url);
            }
            return $data;
        }
        throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function get_relation(App $object, $entity1=null, $entity2=null, $single_or_multiple='single'): array
    {
        $function = 'get';
        $request = Permission::request($object, $entity1 . '.' . $entity2, $function);
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $entity = $entity1 . '.' . $entity2;
        $type = $entity1 . '.' . $entity2;

        $entityName1 = $object->config('doctrine.entity.prefix') . $entity1;
        $repository = $entityManager->getRepository($entityName1);
        $id = lcfirst($entity1) . '_id';
        if(array_key_exists($id, $request)) {
            $node1 = $repository->findOneBy([
                'id' => $request[$id]
            ]);
            if (empty($node1)) {
                throw new Exception('Could not find entity (' . $entity1 . ') with id: ' . $request[$id]);
            }
        } else {
            throw new Exception('Could not find request with id: ' . $id);
        }
        if($single_or_multiple === 'single'){
            $method = $function . ucfirst($entity2);
        } else {
            $method = $function . ucfirst($entity2) . 's';
        }
        if(method_exists($node1, $method)){
            $entity = $entity1 . '.' . $entity2;
            $data = [];
            $record = [];
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.' . $function .'.expose'
            );
            $record = Entity::expose(
                $object,
                $node1,
                $toArray,
                $entity,
                $function,
                $record
            );
            $data['node'] = $record;
            return $data;
        }
        throw new Exception('Cannot find method: ' . $method . ' at: ' . $entity1);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function delete_relation(App $object, $entity1=null, $entity2=null): array
    {
        $function = 'delete';
        $request = Permission::request($object, $entity1 . '.' . $entity2, $function);
        if(empty($request)){
            throw new Exception('Request is empty...');
        }
        if(count($request) < 2){
            throw new Exception('Request need more data...');
        }
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $entity = $entity1 . '.' . $entity2;
        $type = $entity1 . '.' . $entity2;
        $validate_url = Entity::getValidatorUrl($object, $entity);
        if(!File::exist($validate_url)) {
            $entity = $entity2 . '.' . $entity1;
            $type = $entity2 . '.' . $entity1;
            $validate_url = Entity::getValidatorUrl($object, $entity);
            if(!File::exist($validate_url)) {
                throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
            }
        }
        $validate = Main::validate($object, $validate_url,  $type . '.delete');
        if($validate) {
            if($validate->success === true) {
                $entityName1 = $object->config('doctrine.entity.prefix') . $entity1;
                $repository = $entityManager->getRepository($entityName1);
                $node1_id = strtolower($entity1) . '_id';
                if(array_key_exists($node1_id, $request)){
                    $node1 = $repository->findOneBy([
                        'id' => $request[$node1_id]
                    ]);
                    if(empty($node1)){
                        throw new Exception('Could not find entity (' . $entity1 . ') with id: ' . $request[$node1_id]);
                    }
                } else {
                    throw new Exception('Could not find request with id: ' . $node1_id);
                }
                $entityName2 = $object->config('doctrine.entity.prefix') . $entity2;
                $repository = $entityManager->getRepository($entityName2);
                $node2_id = strtolower($entity2) . '_id';
                if(array_key_exists($node2_id, $request)){
                    $node2 = $repository->findOneBy([
                        'id' => $request[$node2_id]
                    ]);
                    if(empty($node2)){
                        throw new Exception('Could not find entity (' . $entity2 . ') with id: ' . $request[$node2_id]);
                    }
                } else {
                    throw new Exception('Could not find request with id: ' . $node2_id);
                }
                $method = 'delete' . ucfirst($entity2);
                if(method_exists($node1, $method)){
                    $is_deleted = $node1->$method($node2);
                    if($is_deleted){
                        $entityManager->persist($node1);
                        $entityManager->flush();
                    } else {
                        throw new Exception('Could not delete entity (' . $entity2 . ') with id: ' .  $request[$node2_id] .' from entity (' . $entity1 .') with id: ' . $request[$node1_id]);
                    }
                } else {
                    throw new Exception('Method doesn\'t exist: ' . $method);
                }
                $entity = $entity1 . '.' . $entity2;
                $data = [];
                $record = [];
                $toArray = Entity::expose_get(
                    $object,
                    $entity,
                    $entity . '.' . $function .'.expose'
                );
                $record = Entity::expose(
                    $object,
                    $node1,
                    $toArray,
                    $entity,
                    $function,
                    $record
                );
                $data['node'] = $record;
            } else {
                $data = [];
                $data['error'] = $validate->test;
            }
            return $data;
        } else {
            throw new Exception('Cannot validate entity at: ' . Entity::getValidatorUrl($object, $entity));
        }
    }

    /**
     * @throws ObjectException
     * @throws QueryException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public static function list(App $object, EntityManager $entityManager, Role $role, $entity, $options=[]): array
    {
        if(!array_key_exists('function', $options)){
            $options['function'] = __FUNCTION__;
        }
        if(!array_key_exists('fetchJoinCollection', $options)){
            $options['fetchJoinCollection'] = true;
        }
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
                $qb->orderBy($alias . '.' . $key, strtoupper($value));
            }
            $qb->setParameters($parameters);
            $result = $qb->getQuery()->getResult();
            $toArray = Entity::expose_get(
                $object,
                $entity,
                $entity . '.' . $options['function'] .'.output'
            );
            foreach($result as $node){
                $record = [];
                $record = Entity::output(
                    $object,
                    $node,
                    $toArray,
                    $entity,
                    $options['function'],
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
                $qb->orderBy($alias . '.' . $key, strtoupper($value));
            }
            $qb->setParameters($parameters)
                ->setFirstResult($firstResult)
                ->setMaxResults($limit);
            $paginator = new Paginator($qb->getQuery(), $options['fetchJoinCollection']);
            $expose = Entity::expose_get(
                $object,
                $entity,
                $entity . '.'. $options['function'] . '.output'
            );
            foreach ($paginator as $node) {
                $record = [];
                $record = Entity::output(
                    $object,
                    $node,
                    $expose,
                    $entity,
                    $options['function'],
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
            $qb->orderBy($alias . '.' . $key, strtoupper($value));
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
        foreach($roles as $role){
            $permissions = $role->getPermissions();
            foreach ($permissions as $permission){
                foreach($toArray as $action) {
                    if(
                        (
                            $permission->getName() === $entity . ':' . $function &&
                            property_exists($action, 'role') &&
                            $action->role === $role->getName()
                        ) ||
                        (
                            in_array(
                                $function,
                                ['child', 'children']
                            ) &&
                            property_exists($action, 'role') &&
                            $action->role === $role->getName()
                        )
                    ) {
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
                                        foreach ($array as $child) {
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
                                            $record[$attribute][] = $child_record;
                                        }
                                    } elseif (
                                        method_exists($node, $method)
                                    ) {
                                        $record[$attribute] = [];
                                        $child = $node->$method();
                                        if (!empty($child)) {
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

    /**
     * @throws ObjectException
     * @throws \ReflectionException
     */
    private static function filter(App $object, &$where=[], ArrayCollection &$parameters=null){
        $request = clone $object->request();
        unset($request->limit);
        unset($request->pagination);
        unset($request->page);
        unset($request->order);
        unset($request->request);
        unset($request->entity);
        unset($request->authorization);
        $alias = lcfirst($object->request('entity'));
        $filter = $request;
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

    public static function has_set(App $object, $entity): array
    {
        $entityName = $object->config('doctrine.entity.prefix') . $entity;
        $reflection = new ReflectionObject(new $entityName());
        $properties = $reflection->getProperties();
        $reader = new AttributeReader();
        //must become attribute reader
        $has_set = [];
        foreach ($properties as $property) {
            $attributes = $reader->getPropertyAttributes($property);
            foreach ($attributes as $attribute) {
                if (in_array(get_class($attribute), [
                    OneToOne::class,
                    ManyToOne::class
                ])) {
                    $has_set[] = $property->getName();
                }
            }
        }
        return $has_set;
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
     * @throws Exception
     */
    public static function getValidatorUrl(App $object, $entity): string
    {
        return $object->config('project.dir.source') .
            'Validate' .
            $object->config('ds') .
            $entity .
            $object->config('extension.json');
    }
}