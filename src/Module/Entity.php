<?php
namespace Raxon\Doctrine\Module;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

use Raxon\App;

class Entity {

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public static function readById(App $object, EntityManager $em, string $entity, int $id){

        return $em->find($entity, $id);
    }
}
