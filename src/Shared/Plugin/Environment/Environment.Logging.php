<?php
namespace Plugin;
trait Environment_Logging {

    public function environment_logging($always=false): void
    {
        $object = $this->object();
        dd($object->config());
    }
}