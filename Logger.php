<?php
namespace Coolie;

/**
 * Class Logger
 * @package Coolie
 */
class Logger
{
    /**
     * @var string
     */
    const LEVEL_TRACE   = 'trace';

    /**
     * @var string
     */
    const LEVEL_INFO    = 'info';

    /**
     * @var string
     */
    const LEVEL_WARNING = 'warning';

    /**
     * @var string
     */
    const LEVEL_ERROR   = 'error';


    /**
     * @var array
     */
    private $_buffer = [];


    /**
     * @var integer
     */
    public $flushNumber = 1;

    /**
     * @var LogStorage\BaseInterface
     */
    public $_storage;

    public function __construct($storageClass)
    {
        $this->_storage = new $storageClass();

        $config = (array)Coolie::getInstance()->config->get('LogStorage');

        $this->_storage->init($config);

    }

    private function _append($task, $level, $category, $message)
    {
        $this->_buffer[] = [
            'task'     => $task,
            'level'    => $level,
            'category' => $category,
            'message'  => $message, 
            'time'     => microtime(true)
        ];
    }

    public function flush()
    {
        $this->_storage->store($this->_buffer);

        $this->_buffer = array();
    }

    public function log($message, $level = self::LEVEL_INFO, $category = 'Coolie', $task = 0)
    {
        $this->_append($task, $level, $category, $message);
        
        if(count($this->_buffer) >= $this->flushNumber)
            $this->flush();
    }

    public function __destruct()
    {
        $this->flush();
    }
}
