<?php
namespace Coolie;
class Worker
{
    public function __construct()
    {
        define('YII_ENABLE_EXCEPTION_HANDLER', false);
        define('YII_ENABLE_ERROR_HANDLER', false);
        defined('YII_DEBUG') or define('YII_DEBUG', false);
        define('ENVIRONMENT', 'test');
        require_once(__DIR__ . '/../framework/yii.php');
        
        
        $config = \CMap::mergeArray(
            require(__DIR__.'/../common/config/base.php'),
            array(
                'basePath'          => __DIR__ ,
                'runtimePath'       => __DIR__ . '/runtime',
                'language' => 'zh_cn', //����
                'timezone' => 'Asia/Shanghai', //ʱ��
                'aliases' => array(
                    'common' => realpath(__DIR__ . '/../common/'),
                ),
            )
        );       
        //ע��һ��CFakeApplication
        eval('class CFakeApplication extends CApplication { public function processRequest() {} }');

        \Yii::createApplication('CFakeApplication', $config);
    }
}
