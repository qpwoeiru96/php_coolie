<?php
namespace Coolie\Worker;
class Test extends \Coolie\Worker
{

    public function test()
    {
        usleep(10);
        file_put_contents(__DIR__ . '/temp.log', json_encode(func_get_args()) . PHP_EOL, FILE_APPEND);
        return 1;
    }
}