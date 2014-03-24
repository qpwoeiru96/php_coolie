<?php
namespace Coolie;

/**
 * @package Coolie
 */
class Task
{
    /**
     * @var int
     */
    private $_id;

    /**
     * @var string
     */
    private $_worker;

    /**
     * @var string
     */
    private $_action;

    /**
     * @var string
     */
    private $_production;

    private function __construct()
    {

    }

    /**
     * @param int $id
     * @param string $worker
     * @param string $action
     * @param array $production
     */
    public function set($id, $worker, $action, array $production)
    {
        $this->_id         = $id;
        $this->_worker     = $worker;
        $this->_action     = $action;
        $this->_production = $production;
    }

    /**
     *
     *
     * @param int $id
     * @param string $worker
     * @param string $action
     * @param array $production
     * @return Task
     */
    public static function create($id, $worker, $action, array $production = array())
    {
        $task = new self;

        $task->set($id, $worker, $action, $production);

        return $task;
    }

    /**
     * get property
     *
     * @param string $name
     * @return null|mixed
     */
    public function __get($name)
    {
        $name = '_' . $name;
        return property_exists($this, $name) ? $this->$name : null;
    }

    public function __toString()
    {
        return json_encode([
            'id'         => $this->_id,
            'worker'     => $this->_worker,
            'action'     => $this->_action,
            'production' => $this->_production
        ]);
    }

}
