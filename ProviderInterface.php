<?php
namespace Coolie;

/**
 * Interface ProviderInterface
 *
 * @package Coolie
 */
interface ProviderInterface
{

    /**
     * 任务完成 会删除队列中的任务
     */
    const STATUS_COMPLETE = 1;

    /**
     * 任务重试(重做) 任务会重新回到队列等待读取
     */
    const STATUS_RETRY     = 2;

    /**
     * 任务失败 隐藏队列中的任务(人为控制)
     */
    const STATUS_FAILED    = 3;

    /**
     * 任务出错 隐藏队列中的任务(系统捕获)
     */
    const STATUS_ERROR     = 4;

    /**
     * 任务出错 处理之前 由系统捕获的任务(比如数据包格式不正确)
     */
    const STATUS_WRONG     = 5;

    /**
     * @return Task
     */
    public function getTask();

    public function reportTask($taskId, $status = self::STATUS_COMPLETE);

    public function isReported($id);

    public function setReported($id);

}