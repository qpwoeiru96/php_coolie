<?php
namespace Coolie\Worker;
class Stat extends \Coolie\Worker
{
    /**
     * 
     */
    public function applyJob($data)
    {

        extract($data);
        return \Coolie\Provider::STATUS_COMPELETE;
    }

    public function testUndefinedFunction()
    {
        undefined_function();
    }
}