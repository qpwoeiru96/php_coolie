<?php
namespace Coolie;

class Provider implements ProviderInterface
{

    /**
     * @var  int
     */
    const RECONNECT_INTERVAL = 60;

    /**
     * @var string
     */
    private $_conn;

    /**
     * @var null|mixed
     */
    private $_vender;

    /**
     * @var string
     */
    private $_host;

    /**
     * @var int
     */
    private $_port;

    /**
     * @var string
     */
    private $_tube;

    /**
     * @var array
     */
    private $_reportPool = [];

    /**
     * @param string $conn
     */
    public function __construct($conn)
    {

        $this->_conn = $conn;
        preg_match('#([\d\.]+):(\d+)\|([\w\d-_]+)#', $conn, $matches);
        list(, $host, $port, $tube) = $matches;

        $this->_port = $port;
        $this->_host = $host;
        $this->_tube = $tube;

        Coolie::printConsoleLog(posix_getpid(), __CLASS__, "Provider connection is: {$host}:{$port}|{$tube}");

        $this->_vender = new \Pheanstalk_Pheanstalk($this->_host, $this->_port);
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        $task = null;

        do {

            try {

                $job = $this->_vender
                    ->watch($this->_tube)
                    ->ignore('default')
                    ->reserve();

            } catch (\Pheanstalk_Exception_ConnectionException $e) {

                Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Connect beanstalkd failed. retry after ' . self::RECONNECT_INTERVAL . ' seconds');

                sleep(self::RECONNECT_INTERVAL);
                continue;

            } catch (\Exception $e) {

                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'message'  => $e->getMessage(),
                    'code'  => $e->getCode()
                )));

                sleep(self::RECONNECT_INTERVAL);
                continue;
            }

            if( !($job instanceOf \Pheanstalk_Job) ) {
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'not a valid pheanstalk job');
                continue;
            }

            try {

                $data = self::parseJobData($job);
                $task = Task::create($data['id'], $data['worker'], $data['action'], $data['production']);

            } catch (Exception\TaskFormat $e) {

                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'message'  => $e->getMessage(),
                    'code'  => $e->getCode()
                )));

                $this->reportTask($job->getId(), self::STATUS_WRONG);

            } catch (\Exception $e) {

                Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                    'message'  => $e->getMessage(),
                    'code'  => $e->getCode()
                )));

                $this->reportTask($job->getId(), self::STATUS_WRONG);
            }

        } while( !($task instanceOf Task) );

        return $task;
    }

    /**
     * parse job data
     *
     * @param \Pheanstalk_Job $job
     * @throws Exception\TaskFormat
     * @return array
     */
    public static function parseJobData(\Pheanstalk_Job $job)
    {

        $id   = $job->getId();
        $data = json_decode($job->getData(), 1);

        if(!is_array($data))
            throw new Exception\TaskFormat('data is not a valid json format.', 0);

        $command = isset($data['command']) ? $data['command'] : '';

        @list($worker, $action) = array_map('trim', explode('.', $command));

        if($worker === '' || $action === '')
            throw new Exception\TaskFormat('command is empty.', 0);

        $production = isset( $data['production'] ) ? $data['production'] : array();

        return compact('id', 'worker', 'action', 'production');
    }

    /**
     * print job status
     * 
     * @param  int $status
     * @return string
     */
    public static function printStatus($status)
    {
        $list = array('undefined', 'complete', 'need retry', 'failed', 'error', 'wrong');
        return (1 <= $status && 5 >= $status) ? $list[$status] : $list[0];
    }

    public function isReported($id)
    {
        return $id ? isset($this->_reportPool[$id]) : true;
    }
    
    public function setReported($id)
    {
        $this->_reportPool[$id] = true;
    }

    /**
     *
     */
    public function reportTask($id, $status = self::STATUS_COMPLETE)
    {

        try {

            $job = $this->_vender->watch($this->_tube)->peek($id);
            
        } catch (\Exception $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'message'  => $e->getMessage(),
                'code'  => $e->getCode()
            )));

            return false;
        }

        $reportData = [
            'id'     => $id,
            'status' => self::printStatus($status),
            'memory' => memory_get_peak_usage(1),
            'time'   => Workshop::getTimer($id) ? (microtime(1) - Workshop::getTimer($id)) * 1000 : 0

        ];
        
        Coolie::printConsoleLog(posix_getpid(), __CLASS__, 'Task ' .  $id . ' status is ' . self::printStatus($status));
        Coolie::getInstance()->log(json_encode($reportData), 'trace', 'Coolie.Provider.Report.Info', $id);

        try {
            
            switch($status) {
                                
                case self::STATUS_FAILED:
                case self::STATUS_ERROR:
                    $this->_vender->watch($this->_tube)->bury($job);
                    break;
                    
                case self::STATUS_RETRY:
                    break;
                  
                case self::STATUS_WRONG:
                case self::STATUS_COMPLETE:
                default:
                    $this->_vender->watch($this->_tube)->delete($job);
                    break;
            }

            $this->setReported($id);

            return true;

        } catch (\Exception $e) {

            Coolie::printConsoleLog(posix_getpid(), __CLASS__, json_encode(array(
                'message'  => $e->getMessage(),
                'code'  => $e->getCode()
            )));

            //if($job instanceOf Pheanstalk_Job) $this->_transporter->watch($this->_tube)->delete($job);
            return false; 
        }
    }
}
