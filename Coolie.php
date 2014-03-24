<?php
/**
 * Coolie Task Factory
 *
 * <pre>苦力(coolie)是一个Beanstalk为后端的任务工厂</pre>
 *
 * @package Coolie
 * @author  qpwoeiru96 <qpwoeiru96@gmail.com>
 * @version 0.4.0
 */

namespace Coolie;

/**
 * Class Coolie
 *
 * @package Coolie
 * @property Config $config
 */
class Coolie
{
    /**
     * @var string
     */
    const PACKAGE = 'Coolie';

    /**
     * @var int
     */
    const VERSION_MAJOR = 0;

    /**
     * @var int
     */
    const VERSION_MINOR = 4;

    /**
     * @var int
     */
    const VERSION_MICRO = 0;

    /**
     * @var bool
     */
    public static $loaded = false;

    /**
     * @var null|Coolie
     */
    private static $_instance;

    /**
     * @var bool
     */
    private $_isRunning = false;

    /**
     * @var Config
     */
    private $_config;

    /**
     * @var int
     */
    private $_childProcessId;

    /**
     * @var Logger
     */
    private $_logger;

    /**
     * @return Coolie
     */
    private function __construct()
    {
        self::setConsoleTitle((string)$this);
    }

    /**
     * 
     * @return Coolie
     */
    public static function getInstance()
    {
        if(self::$_instance === null)
            self::$_instance = new self;

        return self::$_instance;
    }

    /**
     * initial environment
     *
     * @param string $configFile
     * @return Coolie
     */
    public static function load($configFile)
    {
        if(self::$loaded)
            trigger_error('coolie already loaded.', E_USER_ERROR);

        
        error_reporting(0);
        ini_set('display_errors', 'Off');
        date_default_timezone_set("Asia/Shanghai");

        spl_autoload_register([__CLASS__, 'autoload']);

        $instance          = self::getInstance();
        $instance->_config = new Config($configFile);

        define('COOLIE_DEBUG', (boolean)$instance->config->get('Coolie.debug'));

        if($instance->config->get('Coolie.check_requirement'))
            Requirement::check();


        $logStorager = $instance->config->get('Coolie.log_storager');

        if($logStorager)
            $instance->_logger = new Logger($logStorager);

        

        $instance->preload();

        self::$loaded = true;

        return $instance;
    }



    /**
     * preload some php file
     *
     * @return void
     */
    public function preload()
    {
        $fileList = (string)$this->config->get('Coolie.preload');

        if($fileList === '') return ;

        $configDir = dirname(realpath($this->config->filePath));
        $fileList  = explode('|', str_replace('__DIR__', $configDir, $fileList));

        foreach($fileList as $file) {

            if(!file_exists($file))
                trigger_error('preload file ' . $file . ' is not exists,', E_USER_ERROR);
            include $file;
        }
    }

    /**
     * 
     * @param  string|mixed $message
     * @param  string $level
     * @param  string $category
     * @param  int $task
     * @return void
     */
    public function log($message, $level = Logger::LEVEL_INFO, $category = 'Coolie', $task = 0)
    {

        if($this->_logger)
            $this->_logger->log(is_string($message) ? $message : json_encode($message), $level, $category, $task);
    }


    /**
     * @return void
     */
    public function run()
    {
        if($this->_isRunning)
            trigger_error('coolie is already running.', E_USER_ERROR);

        $pid = pcntl_fork();

        if ($pid == -1) {
            exit('could not fork child process.');

        } else if ($pid) {
            
            $this->_childProcessId = $pid;

            pcntl_wait($status);

            $this->_isRunning = false;
            if($status) self::run();

        } else {

            $this->setUID();
            $this->setGID();
            Factory::getInstance()->run();
        }

        $this->_isRunning = true;
    }


    /**
     * set child process group id
     *
     * @return  void
     */
    public function setGID()
    {
        $groupName = $this->config->get('Coolie.group');

        if($groupName) {
            $groupInfo = posix_getgrnam($groupName);
            if(is_array($groupInfo) && isset($groupInfo['gid'])) {
                posix_setgid($groupInfo['gid']);
            }
        }
    }

    /**
     * set child process user id
     *
     * @return  void
     */
    public function setUID()
    {
        $userName = $this->config->get('Coolie.user');

        if($userName) {
            $userInfo = posix_getpwnam($userName);
            if(is_array($userInfo) && isset($userInfo['uid'])) {
                posix_setuid($userInfo['uid']);
            }
        }
    }

    /**
     * get coolie version string
     *
     * @return string
     */
    public static function getVersion()
    {
        return implode('.', [self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_MICRO]);
    }

    /**
     * print console log
     * 
     * @return void
     */
    public static function printConsoleLog()
    {        
        if(defined('COOLIE_DEBUG') && COOLIE_DEBUG) {
            $args = func_get_args();
            array_unshift($args, "[" . date('Y-m-d H:i:s.') . substr((microtime(1) * 10000), -4, 4) . "][%d] %s: %s \n");
            print call_user_func_array('sprintf', $args);
        }
    }

    /**
     * set console title
     * 
     * @param string $title
     * @return void
     */
    public static function setConsoleTitle($title) 
    {
        if(function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        }
    }

    /**
     * class loader of coolie
     * 
     * @param  string $className
     * @return boolean
     */
    public static function autoload($className)
    {
        
        if( strpos($className, '\\') === FALSE || strrpos($className, self::PACKAGE) === FALSE) return FALSE;

        $className = ltrim(ltrim($className, self::PACKAGE), '\\');
        $path      = implode(DIRECTORY_SEPARATOR, explode('\\', $className)) . '.php';
        $path      = __DIR__ . DIRECTORY_SEPARATOR . $path;

        if(file_exists($path) && is_readable($path)) include($path);

        return class_exists($className, false) || interface_exists($className, false);
    }

    /**
     *
     * 
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        if($name === 'config')
            return $this->_config;

        return null;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        trigger_error('clone is not allowed.', E_USER_ERROR);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "Coolie " . self::getVersion();
    }
}

Coolie::load(__DIR__ . '/config.ini')->run();
