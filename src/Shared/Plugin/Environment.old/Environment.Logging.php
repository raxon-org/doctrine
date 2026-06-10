<?php
namespace Plugin;
trait Environment_Logging {

    public function environment_logging($always=false): bool
    {
        $object = $this->object();
        if($always){
            return true;
        }
        $environment = strtolower($object->config('framework.environment'));
        switch($environment){
            case 'development':
            case 'staging':
            case 'test':
                return true;
            case 'production':
            case 'replica':
                return false;
        }
        return false;
    }
}