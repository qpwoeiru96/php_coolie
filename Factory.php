<?php
namespace Coolie;

class Factory 
{

    private static $_instance   = NULL;
    
    private $_workshopNumber    = 1;
    
    private $_workshops         = array();
    
    private $_watchInterval     = 10;

    private static $_lastWatch  = 0;
    
    //private $_workshopMaxMemory = 20971520;

    /**
     * 工厂状态 (1为运行 0为暂停)
     * @var integer
     */
    private $_status = 1;

    private function __construct() 
    {
        Coolie::setConsoleTitle('Coolie Task Factory');

        $this->_workshopNumber    = Coolie::config()->get('Factory.workshop_number');
        //$this->_workshopMaxMemory = Coolie::config()->get('Factory.workshop_max_memory');
        $this->_watchInterval     = Coolie::config()->get('Factory.watch_interval');

        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Factory Start.');

        /**
         * 注册子进程发给父进程的SIGCHLD信息处理器
         */
        pcntl_signal(SIGCHLD, array($this, "signalHandler"));
        pcntl_signal(SIGINT, array($this, "signalHandler"));
        pcntl_signal(SIGTERM, array($this, "signalHandler"));
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
            
            /**
             * 当某一子进程结束、中断或恢复执行时，内核会发送SIGCHLD信号予其父进程。
             * 在默认情况下，父进程会以SIG_IGN函数忽略之
             * 
             * @see  http://zh.wikipedia.org/wiki/%E5%AD%90%E8%BF%9B%E7%A8%8B
             */
            case SIGCHLD:

                /**
                 * @see  http://www.php.net/manual/zh/function.pcntl-waitpid.php
                 * @see  http://baike.baidu.com/view/2899885.htm
                 */
                
                $pid = pcntl_waitpid(-1, $status, WNOHANG /*|| WUNTRACED*/);

                while($pid > 0) {

                    //Coolie::printConsoleLog(posix_getpid(), __CLASS__, $pid . ' ' . $status);

                    if ($pid > 0 && $status) {
                        posix_kill($pid, SIGKILL);
                    }

                    if($pid > 0) {
                        for($i = 1; $i <= $this->_workshopNumber; $i++) {
                            $workshop = $this->getWorkshop($i);
                            if( $workshop->getPid() === $pid ) {
                                $this->buildWorkshop($i);
                            };
                            unset($workshop);
                        }
                    }

                    $pid = pcntl_waitpid(-1, $status, WNOHANG /*|| WUNTRACED*/);
                }
                break;

            case SIGINT:
            case SIGTERM:
                pcntl_signal(SIGCHLD, SIG_IGN);
                $this->close();
                exit(0);
        }

    }

    /**
     * 运行工厂
     * 
     * @return void
     */
    public function run()
    {

        $this->buildWorkshops();
        while($this->_status) {
            $this->watch();
            //var_dump($this->_watchInterval);
            sleep($this->_watchInterval);
        }
        $this->close();
    }

    public function __clone()
    {
        trigger_error('not allowed.', E_USER_ERROR);
    }

    public static function getInstance()
    {
        if(self::$_instance === NULL)
            self::$_instance = new self;

        return self::$_instance;
    }

    /**
     * 创建生产车间
     * 
     * @return void
     */
    public function buildWorkshops()
    {
        for($i = 1; $i <= $this->_workshopNumber; $i++) {
            $this->buildWorkshop($i);
        }
    }

    /**
     * 创建单个生成车间
     * 
     * @param  integer $index 车间的索引
     * @return \CWorkshop
     */
    public function buildWorkshop($index)
    {
        $workshop = new Workshop($index);
        $workshop->run();
        $this->_workshops[$index] = $workshop;
        return $workshop;
    }

    /**
     * 根据索引获取生产车间
     * 
     * @param  integer $index 车间的索引
     * @return Workshop
     */
    public function getWorkshop($index)
    {
        return isset($this->_workshops[$index]) ? $this->_workshops[$index] : FALSE;
    }

    /**
     * 关闭工厂
     * 
     * @return [type] [description]
     */
    public function close()
    {
        $this->_status = 0;

        for($i = 1; $i <= $this->_workshopNumber; $i++) {
            $workshop = $this->getWorkshop($i);
            if($workshop) $workshop->close();
        }
    }

    /**
     * 监视任务
     * 
     * @return void
     */
    public function watch() {

        if(time() - self::$_lastWatch < $this->_watchInterval) return FALSE;
        
        for($i = 1; $i <= $this->_workshopNumber; $i++) {

            $workshop = $this->getWorkshop($i);

            if(!$workshop) {
                $this->buildWorkshop($i);
                continue;
            }

            /**
             * 如果发现工作车间状态不正常 则手动销毁工作车间
             */
            if($workshop->getStatus() !== Workshop::STATUS_NORMAL) {
                $workshop->kill();
                unset($workshop);
                $this->buildWorkshop($i);
                continue;
            }

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, sprintf('Workshop %d Memory Usage: %.2fMB',
                    $i, $workshop->getMemoryUsage() / 1048576));

            /*if($workshop->getMemoryUsage() > $this->_workshopMaxMemory) {
                $workshop->close();
                unset($workshop);
                $this->buildWorkshop($i);
                continue;
            }*/

        }

        self::$_lastWatch = time();
    }
}