<?php
namespace Coolie;

/**
 * Class Workshop
 *
 * @package Coolie
 * @property int $index
 * @property ProviderInterface $provider
 * @property int $processId
 * @property int $currentTaskId
 */
class Workshop
{

    /**
     * @var int
     */
    const STATUS_NORMAL = 1;

    /**
     * @var int
     */
    const STATUS_EXIT = 2;

    /**
     * @var int
     */
    const STATUS_ZOMBIE = 3;

    /**
     * @var int
     */
    private $_index;

    /**
     * @var null|ProviderInterface
     */
    private $_provider;

    /**
     * @var int
     */
    private $_processId;

    /**
     * @var int
     */
    private $_currentTaskId;

    /**
     * @var array
     */
    private static $_timerPool = [];


    public function __construct($index)
    {

        $this->_index = $index;
    }

    /**
     * get provider connection setting
     *
     * @return string
     */
    public function getConnection()
    {
        $key = 'index_' . $this->index;

        $conn = (string)Coolie::getInstance()->config->get('Workshop.' . $key);

        if(!$conn)
            $conn = (string)Coolie::getInstance()->config->get('Workshop.default');

        return $conn;
    }


    public function signalHandler($signo)
    {
        declare(ticks = 1);

        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'System send signal ' . Factory::getInstance()->signoList[$signo]);

        exit;
    }
    /**
     *
     */
    public function run()
    {
        $pid = pcntl_fork();

        if ($pid === -1)
            trigger_error('can not fork a child process', E_USER_ERROR);

        if ($pid) {
            $this->_processId = $pid;

        } else {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Workshop ' . $this->index . ' Start');
            Coolie::setConsoleTitle('Coolie Workshop ' . $this->index);

            $this->_provider = new Provider($this->getConnection());

            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
            pcntl_signal(SIGCHLD, SIG_IGN);

            $this->start();
            exit;

        }
    }

    /**
     * error handler
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $errcontext
     * @return void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode([
            'message' => $errstr,
            'code'    => $errno,
            'file'    => $errfile,
            'line'    => $errline,
            'context' => $errcontext
        ]));

        Coolie::getInstance()->log([
            'message' => $errstr,
            'code'    => $errno,
            'file'    => $errfile,
            'line'    => $errline,
            'context' => $errcontext
        ], 'error', 'Coolie.Workshop.ErrorHandler', (int)$this->currentTaskId);

        #if($this->currentTaskId)
        #    $this->provider->reportTask($this->currentTaskId, ProviderInterface::STATUS_ERROR);
    }

    public function registerErrorHandler()
    {
        set_error_handler([$this, 'errorHandler']);
        //set_exception_handler(array($this, 'exceptionHandler'));
        
        $workshop = $this;
        register_shutdown_function(function() use($workshop) {

            $error = error_get_last();

            if($error) {

                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode($error));
                Coolie::getInstance()->log(json_encode($error), 'error', 'Coolie.Workshop.Error', (int)$workshop->currentTaskId);

                switch ($error['type']) {

                    case E_ERROR:
                    case E_PARSE:
                    case E_USER_ERROR:
                    case E_COMPILE_ERROR:
                        if($workshop->currentTaskId) {
                            $workshop->provider->reportTask($workshop->currentTaskId,
                                $error['type'] === E_ERROR ? ProviderInterface::STATUS_ERROR : ProviderInterface::STATUS_WRONG);  
                            $workshop->setReported($workshop->currentTaskId);                          
                        }
                        break;
                    //非致命性错误 errorHandler 能获取到 这里就不判断了
                    case E_WARNING:
                    case E_NOTICE:
                    default:
                        break;
                }
                
            }

            if(!$workshop->provider->isReported((int)$workshop->currentTaskId)) {
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Task ' . $workshop->currentTaskId . ' does not report the result.');
                Coolie::getInstance()->log('Task ' . $workshop->currentTaskId . ' does not report the result.', 'error', 'Coolie.Workshop.Error', (int)$workshop->currentTaskId);
                $workshop->provider->reportTask($workshop->currentTaskId, ProviderInterface::STATUS_ERROR);                
            }

            $workshop->_currentTaskId  = null;
        });
    }

    public function start()
    {
        $this->registerErrorHandler();

        try {

            $task = $this->provider->getTask();

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Task info: ' . $task);
            Coolie::getInstance()->log((string)$task, 'info', 'Coolie.Workshop.Task.Info', $task->id);

            $this->_currentTaskId = $task->id;

            self::setTimer($task->id);

            $this->provider->reportTask($task->id, Dispatcher::dispatch($task));

            $this->_currentTaskId = null;

        } catch (\Exception $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'message' => $e->getMessage(),
                'code'    => $e->getCode()
            )));

            if($this->currentTaskId)
                $this->provider->reportTask($this->currentTaskId, ProviderInterface::STATUS_ERROR);

            Coolie::getInstance()->log($e->getMessage(), 'error', 'Coolie.Workshop.TaskException', $this->currentTaskId);
        }
    }

    /**
     * get the workshop process status
     *
     * @return boolean
     */
    public function getStatus()
    {
        $command = "ps -p " . $this->processId;
        exec($command, $output);

        if( isset($output[1]) && ( strpos($output[1], 'defunct') === FALSE ) ) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Workshop ' . $this->index . ' \'s status is well.');
            return self::STATUS_NORMAL;
        } elseif(!isset($output[1])) {
            Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Workshop ' . $this->index . ' \'s status is exit.');
            return self::STATUS_EXIT;
        } else {
            Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Workshop ' . $this->index . ' \'s status is wrong.');
            return self::STATUS_ZOMBIE;
        }
    }

    /**
     * @param  int $id
     * @return float
     */
    public static function getTimer($id)
    {
        return isset(self::$_timerPool[$id]) ? self::$_timerPool[$id] : 0;
    }

    /**
     *
     * @param int $id
     */
    public static function setTimer($id)
    {
        self::$_timerPool[$id] = microtime(true);
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

}