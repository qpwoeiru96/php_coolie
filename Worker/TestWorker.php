<?php
namespace Coolie\Worker;

/**
 * 
 */
class TestWorker
{
    public function testUndefinedVariable()
    {

        print $undefined;
        return 1;
    }
	
	public function testUndefinedFunction()
    {
        undefinedFunction();
        return 1;
    }

    public function testIncludeWrongFile()
    {
        include __DIR__ . '/../Test/ParseError.php';
        return 1;
    }

    public function testExec()
    {
        system('exit');
        return 1;
    }

    public function testDirectExit()
    {
        exit;
    }

    public function testException() 
    {
        throw new \Exception('i am a Exception.', 1024);
    }

}