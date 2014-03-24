<?php
namespace Coolie;

/**
 * Class Factory
 *
 * @property array $container
 * @property array $signoList
 * @package Coolie
 */
class Factory
{

    /**
     * @var Factory
     */
    private static $_instance;

    /**
     * @var array
     */
    private $_container = [];

    /**
     * @var int
     */
    private $_watchInterval = 30;


    /**
     * @var array
     */
    private $_signoList = [
        SIGHUP    => 'SIGHUP',
        SIGINT    => 'SIGINT',
        SIGQUIT   => 'SIGQUIT',
        SIGILL    => 'SIGILL',
        SIGTRAP   => 'SIGTRAP',
        SIGABRT   => 'SIGABRT',
        SIGIOT    => 'SIGIOT',
        SIGBUS    => 'SIGBUS',
        SIGFPE    => 'SIGFPE',
        SIGKILL   => 'SIGKILL',
        SIGUSR1   => 'SIGUSR1',
        SIGSEGV   => 'SIGSEGV',
        SIGUSR2   => 'SIGUSR2',
        SIGPIPE   => 'SIGPIPE',
        SIGALRM   => 'SIGALRM',
        SIGTERM   => 'SIGTERM',
        SIGSTKFLT => 'SIGSTKFLT',
        SIGCLD    => 'SIGCLD',
        SIGCHLD   => 'SIGCHLD',
        SIGCONT   => 'SIGCONT',
        SIGSTOP   => 'SIGSTOP',
        SIGTSTP   => 'SIGTSTP',
        SIGTTIN   => 'SIGTTIN',
        SIGTTOU   => 'SIGTTOU',
        SIGURG    => 'SIGURG',
        SIGXCPU   => 'SIGXCPU',
        SIGXFSZ   => 'SIGXFSZ',
        SIGVTALRM => 'SIGVTALRM',
        SIGPROF   => 'SIGPROF',
        SIGWINCH  => 'SIGWINCH',
        SIGPOLL   => 'SIGPOLL',
        SIGIO     => 'SIGIO',
        SIGPWR    => 'SIGPWR',
        SIGSYS    => 'SIGSYS',
        SIGBABY   => 'SIGBABY',
    ];


    /**
     * @return Factory
     */
    private function __construct()
    {
        Coolie::setConsoleTitle('Coolie Factory');
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Factory start.');

        $this->_watchInterval = Coolie::getInstance()->config->get('Factory.watch_interval');

        pcntl_signal(SIGCHLD, [$this, "signalHandler"]);
        pcntl_signal(SIGINT,  [$this, "signalHandler"]);
        pcntl_signal(SIGTERM, [$this, "signalHandler"]);
        pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
    }

    /**
     *
     * @see http://stackoverflow.com/questions/9976441/terminating-zombie-child-processes-forked-from-socket-server
     * @param  integer $signo
     * @return void
     */
    public function signalHandler($signo)
    {
        declare(ticks = 1);

        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'System send signal ' . $this->signoList[$signo]);

        switch ($signo) {

            /**
             * @see  http://zh.wikipedia.org/wiki/%E5%AD%90%E8%BF%9B%E7%A8%8B
             */
            case SIGCHLD:

                /**
                 * @see  http://www.php.net/manual/zh/function.pcntl-waitpid.php
                 * @see  http://baike.baidu.com/view/2899885.htm
                 */

                $pid = pcntl_waitpid(-1, $status, WNOHANG || WUNTRACED);

                while($pid > 0) {


                    Coolie::printConsoleLog(posix_getpid(), __CLASS__,
                        'Child process ' . $pid . ' send signal ' . $this->signoList[$signo] . ' and status is '. $status);

                    if ($pid > 0 && $status) {
                        posix_kill($pid, SIGKILL);
                    }

                    if($pid > 0) {


                        foreach($this->container as $index => $workshop) {

                            if( $workshop->processId == $pid ) {
                                $this->_container[$index] = $this->buildWorkshop($index);
                                break;
                            };

                            unset($workshop);
                        }
                    }

                    $pid = pcntl_waitpid(-1, $status, WNOHANG || WUNTRACED);
                }
                break;

            case SIGQUIT:
            case SIGINT:
            case SIGTERM:
                #pcntl_signal(SIGCHLD, SIG_IGN);
                exit(0);
        }

    }

    /**
     * 
     * @return Factory
     */
    public static function getInstance()
    {
        if(self::$_instance === null)
            self::$_instance = new self;

        return self::$_instance;
    }


    /**
     * 
     * @return void
     */
    public function run()
    {
        $this->buildWorkshops();

        while(1) {
            if(time() % $this->_watchInterval === 0) $this->watch();
            sleep($this->_watchInterval);
        }
    }

    /**
     *
     */
    public function watch()
    {
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Watch start.');

        $number = (int)Coolie::getInstance()->config->get('Factory.workshop_number');

        for($index = 1; $index <= $number; $index++) {

            $workshop = $this->_container[$index];

            if(empty($workshop)) {
                $this->_container[$index] = $this->buildWorkshop($index);
                continue;
            }

            $status = $workshop->getStatus();

            if($status === Workshop::STATUS_EXIT) {

                $this->_container[$index] = $this->buildWorkshop($index);
            } elseif( $status === Workshop::STATUS_ZOMBIE ) {

                @posix_kill($workshop->processId, SIGKILL);
                $this->_container[$index] = $this->buildWorkshop($index);
            }
        }
    }

    /**
     *
     */
    public function buildWorkshops()
    {
        $number = (int)Coolie::getInstance()->config->get('Factory.workshop_number');

        for($i = 1; $i <= $number; $i++) {
            $this->_container[$i] = $this->buildWorkshop($i);
        }

    }

    /**
     *
     * @param $index
     * @return Workshop
     */
    public function buildWorkshop($index)
    {
        $workshop = new Workshop($index);
        $workshop->run();
        return $workshop;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        trigger_error('clone is not allowed.', E_USER_ERROR);
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