<?php
    require '../pheanstalk.phar';

    function getServer()
    {
        $config = explode(':', '168.192.122.29:11300');        
           
        return new Pheanstalk_Pheanstalk($config[0], $config[1]);
    }

    /**
     * 添加任务
     *
     * <p>
     *   请先去Coolie里面注册工人以及行为
     *    Task会自动在生产资料里面添加key为time的值 表示添加此任务的时间
     * </p>
     *
     * @param string  $command  工人以及行为 请用.号分开
     * @param array   $production 生产资料
     * @param integer $priority   优先级 默认 1024 ?????
     * @param integer $delay      延迟多少秒去执行此任务 默认为 0
     * @param integer $ttr        任务执行时间 默认为2分钟
     * @param string  $tube       任务所在的管道 默认为 'task'
     * @return integer/FALSE
     */
    function add($command, array $production = array(), $priority = 1024,
        $delay = 0, $ttr = 120, $tube = 'test')
    {

        try {

            $server = getServer();
            
            $production = array_merge($production, array('time' => microtime(1)) );

            $data  = json_encode(array(
                'command'    => $command,
                'production' => $production
            ));

            return $server->useTube($tube)->put($data, $priority, $delay, $ttr);

        /**
         * 队列服务器挂了 得有个解决方法
         */
        } catch (\Exception $e) {

            print $e->getMessage();
        }
        
    }
    
    // add('TestWorker.testUndefinedFunction', array(), 1024, 0, 120, 'test_1');
    // add('TestWorker.testUndefinedVariable', array(), 1024, 0, 120, 'test_1');
    // add('TestWorker.testIncludeWrongFile', array(), 1024, 0, 120, 'test_2');
    // add('UndefinedWorker.undefinedMethod', array(), 1024, 0, 120, 'test_2');
    // add('TestWorker.undefinedMethod', array(), 1024, 0, 120, 'test_2');
    // add('', array(), 1024, 0, 120, 'test_2');
    add('TestWorker.testExec', array(), 1024, 0, 120, 'test_2');
    add('TestWorker.testDirectExit', array(), 1024, 0, 120, 'test_2');
     add('TestWorker.testException', array(), 1024, 0, 120, 'test_2');
    
