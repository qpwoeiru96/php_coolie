<?php
namespace Coolie;

class Dispatcher
{
    const DIR = 'Worker';

    private static $_instance = NULL;

    private static $_workerMap = array();

    private function __construct() 
    {
        
    }

    public function __clone()
    {
        trigger_error('clone is not allowed.', E_USER_ERROR);
    }

    public static function getInstance()
    {
        if(self::$_instance === NULL)
            self::$_instance = new self;

        return self::$_instance;
    }

    public static function dispatch(Task $task)
    {
        $instance = self::getInstance();

        $worker = $instance->loadWorker($task->worker);

        if(!$worker) return Provider::STATUS_WRONG;

        if(!method_exists($worker, $task->action) || !is_callable(array($worker, $task->action))) {
            return Provider::STATUS_WRONG;
        }

        //try {
            return call_user_func(array($worker, $task->action), $task->production); 
        //} catch (\Exception $e) {
        //    return Provider::STATUS_ERROR;
        //}

    }

    /**
     * 
     */
    private function loadWorker($workerName)
    {
        if(isset(self::$_workerMap[$workerName])) return self::$_workerMap[$workerName];

        $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'Worker' . DIRECTORY_SEPARATOR . $workerName . '.php';

        if( !file_exists($filePath) ) {
            return FALSE;
        }

        include($filePath);

        $className = __NAMESPACE__ . '\\' . self::DIR . '\\' . $workerName;

        if(!class_exists($className)) {
            return FALSE;
        }

        self::$_workerMap[$workerName] = new $className;

        return self::$_workerMap[$workerName];
    }
}