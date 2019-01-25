<?php
namespace app\task;

/**
 * @小智
 * 任务控制文件
 */
class Task
{
    public $task_id;

    public $url;
    public $content;
    public $interval   = 0;
    public $exec_count = 1;

    private $is_run = false;

    private $task_list = [];
    private $task_path = __DIR__ . '/task_list';

    /**
     * [__construct 从文件初始化任务对象]
     * @param int|integer $task_id [任务id]
     */
    public function __construct(string $task_id)
    {
        $this->task_id = $task_id;

        // 读取已经在队列中的任务列表
        $this->task_list = unserialize(file_get_contents($this->task_path));

        !$this->task_list && $this->task_list = [];

        // 如果当前初始化的任务存在任务列表中，则读取任务列表中的数据
        if (array_key_exists($task_id, $this->task_list)) {
            $this->url        = $this->task_list[$task_id]['url'];
            $this->content    = $this->task_list[$task_id]['content'];
            $this->interval   = $this->task_list[$task_id]['interval'];
            $this->exec_count = $this->task_list[$task_id]['exec_count'];

            $this->is_run = true;
        }
    }

    /**
     * [isRun 任务是否在队列中/在文件中]
     * @return boolean 
     */
    public function isRun()
    {
        return $this->is_run;
    }

    /**
     * [start 启动任务]
     * @return null
     */
    public function start()
    {
        $this->stop();

        $this->task_list[$this->task_id] = [
            'url'        => $this->url,
            'interval'   => $this->interval,
            'exec_count' => $this->exec_count,
            'content'    => $this->content,
        ];
        
        file_put_contents($this->task_path, serialize($this->task_list));

        return $task_id;
    }

    /**
     * [stop 停止任务，重启的话需要一秒钟，因为要等待列表载入，时钟周期1秒]
     * @return null
     */
    public function stop()
    {
        if ($this->is_run) {
            unset($this->task_list[$this->task_id]);
            file_put_contents($this->task_path, serialize($this->task_list));
            sleep(1);
        }
    }
}
