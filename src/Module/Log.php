<?php
namespace Raxon\Doctrine\Module;


use Raxon\App;

class Log
{
    protected ?App $object = null;

    use \Plugin\Environment_Logging;

    public function __construct(App $object){
        $this->object($object);
    }

    public function object(null|App $object=null): null|App
    {
        if($object !== null){
            $this->object = $object;
        }
        return $this->object;
    }
}
