<?php
namespace Coolie;

/**
 * Class Config
 * @package Coolie
 * @property string $filePath
 */
class Config
{

    /**
     * store config
     *
     * @var array
     */
    private $_config;

    /**
     * config file
     *
     * @var string
     */
    private $_filePath;

    /**
     *
     * @param string $filePath
     * @return Config
     */
    public function __construct($filePath)
    {
        $this->_filePath = $filePath;

        $this->_parseConfig();
    }

    /**
     * parse config file
     *
     * @return void
     */
    private function _parseConfig()
    {
        if(!is_file($this->_filePath) || !is_readable($this->_filePath))
            trigger_error( $this->_filePath . ' is not a valid config file.');

        $this->_config = @parse_ini_file($this->_filePath, TRUE);
    }

    /**
     * reload config file
     *
     * @return void
     */
    public function reload()
    {
        $this->_parseConfig();
    }

    /**
     * get config item by name
     *
     * @param  string $name
     * @return null|string
     */
    public function get($name) {

        $c = $this->_config;

        if(strpos($name, '.') === FALSE)
            return isset($c[$name]) ? $c[$name] : null;

        $arr = explode('.', $name);

        foreach($arr as $val) {
            if(isset($c[$val])) $c = $c[$val];
            else return null;
        }

        return $c;
    }

    /**
     * @see Config::get
     *
     * @param  string $name
     * @return null|string
     */
    public function __get($name) {

        if($name === 'filePath') return $this->_filePath;

        return $this->get($name);
    }

}
