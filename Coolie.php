<?php
/**
 * Coolie Task Factory
 *
 * <pre>苦力(coolie)是一个Beanstalk为后端的任务工厂</pre>
 *
 * @package Coolie
 * @author  qpwoeiru96 <qpwoeiru96@gmail.com>
 * @date 2013-07-18 16:25:42
 * @copyright WWW.DFWSGROUP.COM
 * @version 0.3.0 <beta>
 */
namespace Coolie;

class Coolie {
    
    const PACKAGE  = 'Coolie';
    
    const VERSION  = '0.3.0';
    
    const BASEPATH = __DIR__;
    
    public $configFile = '';

    private static $_configHandler = NULL;
    
    private static $_instance      = NULL;

    private static $_factoryPID    = -1;

    /**
     * 日志记录者的映射(因为多进程 有多个日志记录者)
     * 
     * @var array
     */
    private static $_loggerMap     = array();

    private function __construct()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));

        define('COOLIE_DEBUG', !!self::config()->get('Coolie.debug'));

        if(COOLIE_DEBUG) error_reporting(E_ALL);
        else error_reporting(0);

        self::setConsoleTitle('Coolie');

        //pcntl_signal(SIGTERM, array($this, "signalHandler"));
        //pcntl_signal(SIGINT, array($this, "signalHandler"));
        //pcntl_signal(SIGQUIT, array($this, "signalHandler"));
    }

    /**
     * 信号处理
     *
     * 关于如何关闭僵尸子进程的问题
     * 
     * @see http://stackoverflow.com/questions/9976441/terminating-zombie-child-processes-forked-from-socket-server
     * @param  integer $signo 信号量
     * @return void
     */
    public function signalHandler($signo)
    {

        //必须的
        declare(ticks = 1);

        switch ($signo) {
            
            case SIGQUIT:
            case SIGINT:
            case SIGTERM:
                posix_kill(self::$_factoryPID, $signo);
                exit(0);

        }

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


    /**
     * 获取配置操作器
     * 
     * @return Coolie\Config
     */
    public static function config()
    {
        if(self::$_configHandler === NULL)
            self::$_configHandler = new Config(__DIR__ . '/config.ini');

        return self::$_configHandler;
    }

    /**
     * 获取到Logger
     * 
     * @return Coolie\Logger
     */
    public static function logger($source)
    {
        if( !isset($_loggerMap[$source]) )
            $_loggerMap[$source] = new Logger($source);

        return $_loggerMap[$source];
    }

    /**
     * 运行
     * 
     * @return void
     */
    public static function run()
    {

        /**
         * 首先初始化
         */
        self::getInstance();

        $pid = pcntl_fork();
        //父进程和子进程都会执行下面代码

        if ($pid == -1) {
            //错误处理：创建子进程失败时返回-1.
            die('could not fork');
        } else if ($pid) {

            //父进程会得到子进程号，所以这里是父进程执行的逻辑
            
            self::$_factoryPID = $pid;

            //等待子进程中断，防止子进程成为僵尸进程。        
            pcntl_wait($status);

            //如果子进程中断 那么重新执行子进程
            if($status) self::run();
            else exit(0);         

        } else {

            /**
             * 设置子进程的UID 跟 GID 加强安全
             */
            self::setUid();
            self::setGid();

            //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
            Factory::getInstance()->run();
        }

    }

    /**
     * 设置子进程的GID
     *
     * @return  void
     */
    public static function setGid()
    {
        $groupName = self::config()->get('Coolie.group');

        if($groupName) {

            $groupInfo = posix_getgrnam($groupName);
            if(is_array($groupInfo) && isset($groupInfo['gid'])) {
                posix_setgid($groupInfo['gid']);
            }
        }
    }

    /**
     * 设置子进程的UID(防止任务提权)
     *
     * @return  void
     */
    public static function setUid()
    {
        $userName = self::config()->get('Coolie.user');

        if($userName) {

            $userInfo = posix_getpwnam($userName);
            if(is_array($userInfo) && isset($userInfo['uid'])) {
                posix_setuid($userInfo['uid']);
            }
        }
    }

    /**
     * 获取依赖数组
     * 
     * @return array
     */
    private static function getRequirements()
    {
        return array(
            array(
                'name'    => 'PHP SAPI',
                'result'  => (bool)stristr(PHP_SAPI, 'CLI'),
                'message' => '',
                'value'   => PHP_SAPI,
                'level'   => 'error'
            ),
            array(
                'name'    => 'Linux Operating System',
                'result'  => (bool)stristr(PHP_OS, 'LINUX'),
                'message' => '',
                'value'   => php_uname(),
                'level'   => 'error'
            ),
            array(
                'name'    => 'PHP Version >= 5.3.0',
                'result'  =>  version_compare(PHP_VERSION, '5.3.0') >= 0,
                'message' => 'php version at least PHP 5.3.0',
                'value'   => PHP_VERSION,
                'level'   => 'error'
            ),
            array(
                'name'    => 'extension pcntl',
                'result'  => extension_loaded('pcntl'),
                'message' => '',
                'value'   => extension_loaded('pcntl'),
                'level'   => 'error'
            ),
            array(
                'name'    => 'extension posix',
                'result'  => extension_loaded('posix'),
                'message' => '',
                'value'   => extension_loaded('posix'),
                'level'   => 'error'
            ),
            array(
                'name'    => 'extension sockets',
                'result'  => extension_loaded('sockets'),
                'message' => '',
                'value'   => extension_loaded('sockets'),
                'level'   => 'error'
            ),
            array(
                'name'    => 'function cli_set_process_title',
                'result'  => function_exists('cli_set_process_title'),
                'message' => '',
                'value'   =>  function_exists('cli_set_process_title'),
                'level'   => 'warnging'
            )
        );
    }

    /**
     * 分析依赖是否满足
     *
     * @todo 
     * @return void
     */
    public static function checkRequirement() {

        $data = self::getRequirements();
        foreach($data as $val) {}
    }

    /**
     * 输出依赖信息
     * 
     * @todo
     * @return [type] [description]
     */
    public static function printMessage()
    {
    }

    /**
     * 输出在控制台的LOG
     * 
     * @return void
     */
    public static function printConsoleLog()
    {        
        if(COOLIE_DEBUG) {
            $args = func_get_args();
            array_unshift($args, "[" . time() . "][%d] %s: %s \n");
            print call_user_func_array('sprintf', $args);
        }
    }

    /**
     * 设置控制台标题
     * 
     * @param string $title
     * @return  void
     */
    public static function setConsoleTitle($title) 
    {
        if(function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        }
    }



    /**
     * Class AutoLoad
     * 
     * 此加载器只是负责Coolie的相关模块的加载
     * 
     * @param  string $className
     * @return boolean
     */
    public static function autoload($className)
    {
        
        if( strpos($className, '\\') === FALSE || strrpos($className, self::PACKAGE) === FALSE) return FALSE;

        $className = ltrim(ltrim($className, self::PACKAGE), '\\');
        $path      = implode(DIRECTORY_SEPARATOR, explode('\\', $className)) . '.php';
        $path      = self::BASEPATH . DIRECTORY_SEPARATOR . $path;
        if(file_exists($path) && is_readable($path)) include($path);
        return class_exists($className, false) || interface_exists($className, false);
    }
}