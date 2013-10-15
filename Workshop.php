<?php
namespace Coolie;
class Workshop
{
    /**
     * 工作车间的正常状态
     */
    const STATUS_NORMAL = 1;

    /**
     * 工作车间的退出状态(一般来说退出会发送SIGCHLD信号,这里以防万一)
     */
    const STATUS_EXIT = 2;

    /**
     * 工作车间的僵尸状态(虽然存在但是不干活)
     */
    const STATUS_ZOMBIE = 3;

    /**
     * 工作车间的索引ID
     *  
     * @var integer
     */
    private $_id;

    /**
     * 生产资料提供者
     * 
     * @var Coolie\Provider
     */
    private $_provider = NULL;

    /**
     * 当前运行的任务ID (父级进程永远返回0 只有子进程有效)
     * 
     * @var integer
     */
    private $_currentTaskId = 0;

    /**
     * 进程PID
     * 
     * @var integer
     */
    private $_pid = -1;

    public function __construct($index)
    {
        $config          = Coolie::config()->getBeanstalkConfigByIndex($index);
        $this->_id       = $index;
        $this->_provider = new Provider($config['host'], $config['port'], $config['tube']);
    }

    /**
     * 获取生产资料提供者
     * 
     * @return \Coolie\Provider
     */
    public function getProvider()
    {
        return $this->_provider;
    }


    /**
     * 开始运行正式的逻辑环节
     *
     * @todo  人物信息的详细追踪
     * @return void
     */
    public function run()
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            trigger_error('can not fork a child process', E_USER_ERROR);
            exit(0);
        }

        if ($pid) {

            $this->_pid = $pid;
        } else {

            Coolie::setConsoleTitle('Coolie Workshop ' . $this->_id);

            pcntl_signal(SIGINT, function(){exit(0);});
            pcntl_signal(SIGTERM, function(){exit(0);});

            $workshop = $this;
            register_shutdown_function(function() use ($workshop) {

                //如果想跟踪详细的错误 取消以下的注释
                // if(COOLIE_DEBUG)
                //     print_r(debug_backtrace());
                
                $error = error_get_last();

                if($error) {

                    switch ($error['type']) {

                        case E_ERROR: 
                            if($workshop->getTaskId()) {
                                $workshop->getProvider()->reportTask($workshop->getTaskId(), Provider::STATUS_ERROR);
                                $workshop->setTaskId(0);
                             }
                            break;

                        case E_PARSE: //我勒个去 连编译错误都能抓取 牛逼哄哄
                            if($workshop->getTaskId()) {                        
                                $workshop->getProvider()->reportTask($workshop->getTaskId(), Provider::STATUS_WRONG);
                                $workshop->setTaskId(0);
                            }
                        //非致命性错误 errorHandler 能获取到 这里就不判断了 
                        case E_WARNING:
                        case E_NOTICE:
                        default:
                            break;
                    }

                    Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode($error));
                    Coolie::logger('workshop')->log(json_encode($error), 'error', 'Coolie.Workshop.Error');
                    Coolie::logger('workshop')->flush();
                }
                
            });

            //开始执行任务
            $this->start();
            exit(0);
        }
    }

    public function registerErrorHandler()
    {
        set_error_handler(array($this, 'errorHandler'));
        set_exception_handler(array($this, 'exceptionHandler'));
    }

    /**
     * 车间开始工作
     * 
     * @return void
     */
    public function start()
    {

        $this->registerErrorHandler();
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, $this->_id . ' Start');

        try {

            $task = $this->getProvider()->getTask();            
            $this->setTaskId($task->id);
            define('COOLIE_TASK_TIME_START', microtime(1));
            $this->getProvider()->reportTask($task->id, Dispatcher::dispatch($task));
            $this->setTaskId(0);

        } catch (\Exception $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'exception_message'  => $e->getMessage(),
                'exception_number'  => $e->getCode()
            )));

            if($this->getTaskId()) $this->getProvider()->reportTask($this->getTaskId(), Provider::STATUS_ERROR);

            Coolie::logger('workshop')->log($e->getMessage(), 'error', 'Coolie.Workshop.TaskException');
            Coolie::logger('workshop')->flush();
        }
    }

    /**
     * 获取当前的任务ID(子进程有效)
     */
    public function getTaskId()
    {
        return $this->_currentTaskId;
    }
    
    public function setTaskId($id)
    {
        $this->_currentTaskId = $id;
    }

    /**
     * 子进程的错误处理
     * @return void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
            'error_number'  => $errno,
            'error_string'  => $errstr,
            'error_file'    => $errfile,
            'error_line'    => $errline,
            'error_context' => $errcontext
        )));
            
        //如果想跟踪详细的错误 取消以下的注释
        // if(COOLIE_DEBUG)
        //     print_r(debug_backtrace());
                
        if($this->getTaskId()) $this->getProvider()->reportTask($this->getTaskId(), Provider::STATUS_ERROR);
        Coolie::logger('workshop')->log(json_encode(func_get_args()), 'error', 'Coolie.Workshop.ErrorHandler');
        Coolie::logger('workshop')->flush();

    }

    /**
     * 子进程的异常处理
     * @return void
     */
    public function exceptionHandler($exception)
    {
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
            'exception_message'  => $exception->getMessage(),
            'exception_number'  => $exception->getCode()
        )));
            
        //如果想跟踪详细的错误 取消以下的注释
        // if(COOLIE_DEBUG)
        //     print_r(debug_backtrace());

        Coolie::logger('workshop')->log($exception->getMessage(), 'error', 'Coolie.Workshop.ExceptionHandler');
        Coolie::logger('workshop')->flush();
        if($this->getTaskId()) $this->getProvider()->reportTask($this->getTaskId(), Provider::STATUS_ERROR);

    }

    /**
     * 获取生产车间的工作状态
     *
     * @return boolean
     */
    public function getStatus()
    {
        $command = "ps -p " . $this->_pid;
        exec($command, $output);

        if( isset($output[1]) && ( strpos($output[1], 'defunct') === FALSE ) )
            return self::STATUS_NORMAL;
        
        if( isset($output[1]) && ( strpos($output[1], 'defunct') !== FALSE ) )
            return self::STATUS_ZOMBIE;

        if(!isset($output[1]))
            return self::STATUS_EXIT;
        
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getPid()
    {
        return $this->_pid;
    }

    public function close()
    {
        if($this->getPid() > 0) posix_kill($this->getPid(), SIGINT);
    }

    public function kill()
    {
        if($this->getPid() > 0) posix_kill($this->getPid(), SIGKILL);
    }

    /**
     * 获取进程内存使用情况
     * 
     * @return integer/bool
     */
    public function getMemoryUsage()
    {

        $command = "ps -eo%mem,rss,pid |grep \" {$this->_pid}$\"";
        exec($command, $output);
        if(!isset($output[0])) return FALSE;
        preg_match("/\s+[\d\.]+\s+(\d+)\s+\d+/i", $output[0], $output);
        return isset($output[1]) ? $output[1] * 1024 : FALSE;

    }
}