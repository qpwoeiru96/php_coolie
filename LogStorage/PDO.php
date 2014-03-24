<?php

namespace Coolie\LogStorage;

/**
 * Class PDO
 * @package Coolie\LogStorage
 */
class PDO implements BaseInterface
{

    /**
     * @var string
     */
    private $_dsn;

    /**
     * @var string
     */
    private $_username;

    /**
     * @var string
     */
    private $_password;

    /**
     * @var string
     */
    private $_table;

    public function init(array $config)
    {
        foreach($config as $k => $v) {
            $k = '_' . $k;
            $this->$k = $v;
        }
    }

    /**
     * @return \PDO
     */
    private function _conn()
    {
        try {

            $pdo = new \PDO($this->_dsn, $this->_username, $this->_password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        } catch (\PDOException $e) {

            \Coolie\Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Failed to connection database. message: ' . $e->getMessage());

            return false;
        }

        return $pdo;
    }

    /**
     * store logs
     * 
     * @param  array  $logs
     * @return void
     */
    public function store(array $logs)
    {

        if(empty($logs)) return;

        $pdo = $this->_conn();

        if(!$pdo) return;

        $values = [];

        foreach($logs as $k => $v) {
            $values[] = str_replace('KEY', $k, '(:task_KEY, :level_KEY, :category_KEY, :message_KEY, :time_KEY)');
        }

        $sql = "INSERT INTO {$this->_table} (task, level, category, message, time) VALUES " . implode(' ', $values);

        try {

            $stmt = $pdo->prepare($sql);

            foreach($logs as $k => $v) {
                $stmt->bindParam(':task_' . $k,     $v['task'],     \PDO::PARAM_INT);
                $stmt->bindParam(':level_' . $k,    $v['level'],    \PDO::PARAM_STR);            
                $stmt->bindParam(':message_' . $k,  $v['message'],  \PDO::PARAM_STR);
                $stmt->bindParam(':category_' . $k, $v['category'], \PDO::PARAM_STR);
                $stmt->bindParam(':time_' . $k,     $v['time'],     \PDO::PARAM_INT);
            }
            
            $stmt->execute();            

        } catch (\Exception $e) {
            \Coolie\Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Failed to execute sql statement. message: ' . $e->getMessage());
        } finally {
            unset($stmt);
            unset($pdo);
        }
        
    }

}