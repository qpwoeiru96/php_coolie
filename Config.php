<?php
namespace Coolie;

class Config
{
    /**
     * 配置存放容器
     * @var array
     */
    private $_config;

    /**
     * 配置文件路径
     * @var string
     */
    private $_configFile;

    public function __construct($configFile)
    {
        if(!is_file($configFile) || !is_readable($configFile))
            trigger_error('config file not valid', E_USER_ERROR);

        $this->_configFile = $configFile;
        $this->_parseConfig();
    }

    /**
     * 分析配置文件
     * 
     * @return void
     */
    private function _parseConfig() 
    {
        $this->_config = parse_ini_file($this->_configFile, TRUE);
    }
    
    public function get($name) {

        $c = $this->_config;

        if(strpos($name, '.') === FALSE)
            return isset($c[$name]) ? $c[$name] : NULL;

        $arr = explode('.', $name);        

        foreach($arr as $val) {
            if( isset($c[$val]) ) $c = $c[$val];
            else return NULL;
        }

        return $c;
    }

    public function __get($name) {
        
        return $this->get($name);
    }

    public function getBeanstalkConfigByIndex($index)
    {
        $config = $this->get('Workshop');

        $singleConfig = isset($config['index_' . $index]) ? $config['index_' . $index]
            : (isset($config['default']) ? $config['default'] : trigger_error("config not valid", E_USER_ERROR));

       return self::parseBeanstalkConfig($singleConfig);

    }

    public static function parseBeanstalkConfig($str)
    {
        preg_match('/^([0-9\.]+):(\d+)\|(\w+)$/', $str, $matches);
        list(, $host, $port, $tube) = $matches;
        return compact('host', 'port', 'tube');        
    }

}