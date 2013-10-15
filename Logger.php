<?php
namespace Coolie;
class Logger
{

    /**
     * 日志级别:跟踪
     */
    const LEVEL_TRACE   = 'trace';
    
    /**
     * 日志级别:警告
     */
    const LEVEL_WARNING = 'warning';
    
    /**
     * 日志级别:错误
     */
    const LEVEL_ERROR   = 'error';
    
    /**
     * 日志级别:信息
     */
    const LEVEL_INFO    = 'info';

    /**
     * 日志来源
     * @var string
     */
    private $_source = 'Coolie';

    /**
     * 日志缓冲
     * @var array
     */
    private $_buffer = array();

    /**
     * 日志容量达到多少时进行输出
     * @var integer
     */
    public $flushNumber = 1;

    /**
     * 日志存储者
     * @var object
     */
    public $_storage = NULL;

    public function __construct($source)
    {
        $this->_source = $source;
    }

    private function _append($message, $level, $category)
    {
        $this->_buffer[] = array($message, $level, $category, microtime(true), $this->_source);
    }

    public function flush()
    {
        $config = Coolie::config()->get('Logger');

        if($this->_storage === NULL)
            $this->_storage = new $config['class'];

        $this->_storage->init($config);

        $this->_storage->save($this->_buffer);

        $this->_buffer = array();
    }

    public function log($message, $level = self::LEVEL_INFO, $category = 'coolie')
    {
        $this->_append($message, $level, $category);
        if(count($this->_buffer) >= $this->flushNumber) $this->flush();
    }

    public function __destruct()
    {
        $this->flush();
    }

}

abstract class LogStorageBase
{
    public function init($params) {

        foreach($params as $key => $val) {
            isset($this->$key) ? $this->$key = $val : null; 
        }
    }

    abstract public function save(array $logs);
}

/**
 * PDO 数据库 日志存储类
 * 
 */
class DBLogStorage extends LogStorageBase
{
    /**
     * CREATE TABLE `coolie_logs` (
     *     `id` int(11) NOT NULL AUTO_INCREMENT,
     *     `level` varchar(128) DEFAULT NULL,
     *     `category` varchar(128) DEFAULT NULL,
     *     `logtime` int(11) DEFAULT NULL,     
     *     `source` varchar(128) DEFAULT NULL,
     *     `message` text,
     *     PRIMARY KEY (`id`)
     * ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
     */

    public $dsn      = 'mysql:host=localhost;dbname=coolie';
    
    /**
     * DSN密码
     * @var string
     */
    public $password = 'coolie';
    
    /**
     * DSN用户名
     * @var string
     */
    public $username = 'coolie';
    
    /**
     * DSN表名称
     * @var string
     */
    public $table    = 'coolie_logs';
    
    /**
     * PDO实例
     * @var \PDO
     */
    private $_pdo    = NULL;

    /**
     * 连接数据库
     * 
     * @return void
     */
    public function connect()
    {
        try {

            $this->_pdo = new \PDO($this->dsn, $this->username, $this->password);
            $this->_pdo->exec("SET NAMES utf8");

        } catch (\PDOException $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'exception_message'  => $e->getMessage(),
                'exception_number'  => $e->getCode()
            )));

            $this->_pdo = NULL;
        }
    }

    /**
     * 保存日志
     *
     * <p>为什么这里要每次都重新连接,因为多进程如果长连接对于服务器是个非常大的损耗。</p>
     * 
     * @param  array  日志信息数组
     * @return void
     */
    public function save(array $logs)
    {

        $this->connect();
        
        if($this->_pdo) {

            foreach($logs as $val) {

                list($message, $level, $category, $logtime, $source) = $val;

                $stmt = $this->_pdo->prepare("INSERT INTO {$this->table} (level, category, logtime, source, message) VALUES (:level, :category, :logtime, :source, :message)");
                $stmt->bindParam(':level', $level, \PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, \PDO::PARAM_STR);
                $stmt->bindParam(':message', $message, \PDO::PARAM_STR);
                $stmt->bindParam(':source', $source, \PDO::PARAM_STR);
                $stmt->bindParam(':logtime', $logtime, \PDO::PARAM_INT);
                $stmt->execute();

                unset($stmt);
            }

            $this->_pdo = NULL;
        }
    }
}
