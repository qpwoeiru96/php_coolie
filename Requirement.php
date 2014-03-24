<?php
namespace Coolie;

/**
 * Class Requirement
 * @package Coolie
 */
class Requirement
{

    /**
     * 
     * @return array
     */
    public static function getRequirementList()
    {
        return [
            [
                'name'    => 'PHP sapi is cli',
                'result'  => (bool)stristr(PHP_SAPI, 'CLI'),
                'message' => '',
                'value'   => PHP_SAPI,
                'level'   => 'error'
            ],
            [
                'name'    => 'Lunix operating system',
                'result'  => (bool)stristr(PHP_OS, 'LINUX'),
                'message' => '',
                'value'   => PHP_OS,
                'level'   => 'error'
            ],
            [
                'name'    => 'PHP version >= 5.5.0',
                'result'  =>  version_compare(PHP_VERSION, '5.5.0') >= 0,
                'message' => 'php version at least PHP 5.5.0',
                'value'   => PHP_VERSION,
                'level'   => 'error'
            ],
            [
                'name'    => 'Extension pcntl loaded',
                'result'  => extension_loaded('pcntl'),
                'message' => '',
                'value'   => extension_loaded('pcntl'),
                'level'   => 'error'
            ],
            [
                'name'    => 'Extension posix loaded',
                'result'  => extension_loaded('posix'),
                'message' => '',
                'value'   => extension_loaded('posix'),
                'level'   => 'error'
            ],
            [
                'name'    => 'Extension sockets loaded',
                'result'  => extension_loaded('sockets'),
                'message' => '',
                'value'   => extension_loaded('sockets'),
                'level'   => 'error'
            ]
        ];
    }
    /**
     * check coolie requirement
     * 
     * @return void
     */
    public static function check()
    {
        
        foreach(self::getRequirementList() as $val) {
            
            if($val['result'])
                Coolie::printConsoleLog(posix_getpid(), __CLASS__, $val['name'] . ' passed.');
            else
                trigger_error('check requirement:' . $val['name'] . ' failed !' . PHP_EOL, E_USER_ERROR);

        }

    }
}