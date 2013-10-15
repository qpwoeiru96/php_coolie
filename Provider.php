<?php
namespace Coolie;

include "pheanstalk.phar";

class Provider
{

    const RECONNECT_INTERVAL = 60;
    
    /**
     * 任务完成 会删除队列中的任务
     */
    const STATUS_COMPELETE = 1;
    
    /**
     * 任务重试(重做) 任务会重新回到队列等待读取
     */
    const STATUS_RETRY     = 2;
    
    /**
     * 任务失败 隐藏队列中的任务(人为控制)
     */
    const STATUS_FAILED    = 3;
    
    /**
     * 任务出错 隐藏队列中的任务(系统捕获)
     */
    const STATUS_ERROR     = 4;

    /**
     * 任务出错 处理之前 由系统捕获的任务(比如数据包格式不正确)
     */
    const STATUS_WRONG     = 5;

    private $_transporter;
    
    private $_host;
    
    private $_port;
    
    private $_tube;

    public function __construct($host = 'localhost', $port = '11300', $tube = 'task')
    {
        $this->_host        = $host;
        $this->_port        = $port;            
        $this->_tube        = $tube;
        $this->_transporter = new \Pheanstalk_Pheanstalk($this->_host, $this->_port);
    }

    public function getTask()
    {
        $task = NULL;        

        do {

            try {
                $job = $this->_transporter->watch($this->_tube)->reserve();
            } catch (\Exception $e) { //include Pheanstalk_Exception_ConnectionException
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'exception_message'  => $e->getMessage(),
                    'exception_number'  => $e->getCode()
                )));
                sleep(self::RECONNECT_INTERVAL);
                continue;
            }

            if( !($job instanceOf \Pheanstalk_Job) ) {
                if(COOLIE_DEBUG) {
                    print '[' . posix_getpid() . '] '. __CLASS__ . ': Not A Valid Pheanstalk Job' . PHP_EOL;
                }
                continue;
            }


            try {
                $task = new Task($job);
            } catch (TaskFormatException $e) {
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'exception_message'  => $e->getMessage(),
                    'exception_number'  => $e->getCode()
                )));
                $this->reportTask($job->getId(), self::STATUS_WRONG);
            } catch (\Exception $e) {
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'exception_message'  => $e->getMessage(),
                    'exception_number'  => $e->getCode()
                )));
                $this->reportTask($job->getId(), self::STATUS_WRONG);
            }

        } while( !($task instanceOf Task) );

        Coolie::logger('Provider')->log($task, 'trace', 'Coolie.Provider.FetchTask');
        
        return $task;
        
    }
    
    public static function printStatus($status)
    {
        $list = array('undefined', 'compelete', 'need retry', 'failed', 'error', 'wrong');
        return (1 <= $status && 5 >= $status) ? $list[$status] : $list[0];
    }

    /**
     *  反馈任务执行信息
     *
     * @param  [type] $jobId  [description]
     * @param  [type] $status [description]
     * @return [type]         [description]
     */
    public function reportTask($taskId, $status = self::STATUS_COMPELETE)
    {

        try {

            $job = $this->_transporter->watch($this->_tube)->peek($taskId);
            
        } catch (\Exception $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'exception_message'  => $e->getMessage(),
                'exception_number'  => $e->getCode()
            )));
            return FALSE;
        }

        $reportData = array(
            'id'     => $taskId,
            'status' => self::printStatus($status),
            'memory' => memory_get_peak_usage(1),
            'time'   => defined('COOLIE_TASK_TIME_START') 
                ? (microtime(1) - COOLIE_TASK_TIME_START) * 1000
                : 0
        );
        
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Task ' .  $taskId . ' Status is ' . self::printStatus($status));
        Coolie::logger('provider')->log(json_encode($reportData), 'trace', 'Coolie.Provider.Info');

        try {
            
            switch($status) {
                                
                case self::STATUS_FAILED:
                case self::STATUS_ERROR:
                    $this->_transporter->watch($this->_tube)->bury($job);
                    return TRUE;
                    
                case self::STATUS_RETRY:
                    return TRUE;
                  
                case self::STATUS_WRONG:
                case self::STATUS_COMPELETE:
                default:
                    $this->_transporter->watch($this->_tube)->delete($job);
                    return TRUE;
            }

        } catch (\Exception $e) {
            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'exception_message'  => $e->getMessage(),
                'exception_number'  => $e->getCode()
            )));
            //if($job instanceOf Pheanstalk_Job) $this->_transporter->watch($this->_tube)->delete($job);
            return FALSE; 
        }        
    }
}

class Task 
{
    private $_id;
    
    private $_worker;
    
    private $_action;
    
    private $_production;

    public function __construct(\Pheanstalk_Job $job)
    {
        $this->_id = $job->getId();
        $data = json_decode($job->getData(), 1);

        if(!is_array($data))
            throw new TaskFormatException('wrong task format', 0);

        $command = isset($data['command']) ? $data['command'] : '';

        list($this->_worker, $this->_action) = array_map('trim', explode('.', $command));

        if($this->_worker === '' || $this->_action === '')
            throw new TaskFormatException('wrong task format', 0);

        $this->_production = isset( $data['production'] ) ? $data['production'] : array();

    }

    public function __toString()
    {
        return json_encode(array(
            'id'         => $this->_id,
            'worker'     => $this->_worker,
            'action'     => $this->_action,
            'production' => $this->_production
        ));
    }

    public function __get($name)
    {
        $name = '_' . $name;
        return isset($this->$name) ? $this->$name : NULL; 
    }

}

class TaskFormatException extends \Exception {}