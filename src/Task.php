<?php
namespace timeingTask;

/**
 * @小智
 * 任务控制文件
 */
class Task
{
    public $task_name;
    public $interval   = 1;
    public $exec_count = 1;
    public $namespace;
    public $params;

    private $wait_for_exec = false;

    private $task_list      = [];
    private $task_list_path = __DIR__ . '/TASKLIST';

    public $task_id;

    public function __construct(int $task_id = 0)
    {

        // 读取运行中的任务列表
        $this->task_list = unserialize(file_get_contents($this->task_list_path));

        !$this->task_list && $this->task_list = [];

        if (!empty($task_id)) {
            $this->task_id = $task_id;

            // 如果当前初始化的任务存在任务列表中，则读取任务列表中的数据
            if (array_key_exists($task_id, $this->task_list)) {
                $this->task_name  = $this->task_list[$task_id]['task_name'];
                $this->interval   = $this->task_list[$task_id]['interval'];
                $this->exec_count = $this->task_list[$task_id]['exec_count'];
                $this->namespace  = $this->task_list[$task_id]['namespace'];
                $this->params     = unserialize($this->task_list[$task_id]['params']);

                $this->wait_for_exec = true;
            }
        }

    }

    /**
     * [waitForExec 任务是否在等待执行列表中/任务是否已经添加]
     * @return bool
     */
    public function waitForExec(): bool
    {
        return $this->wait_for_exec;
    }

    /**
     * [start 启动任务]
     * @return null
     */
    public function start()
    {
        if (empty($this->namespace)) {
            throw new \Exception("任务命名空间为空");
        }

        $this->stop();

        $task_id = uniqid();

        $this->task_list[$task_id] = [
            'task_name'  => $this->task_name,
            'interval'   => $this->interval,
            'exec_count' => $this->exec_count,
            'namespace'  => $this->namespace,
            'params'     => serialize($this->params),
        ];

        file_put_contents($this->task_list_path, serialize($this->task_list));

        return $task_id;
    }

    /**
     * [stop 停止任务]
     * @return null
     */
    public function stop()
    {
        if ($this->wait_for_exec) {
            unset($this->task_list[$this->task_id]);
            file_put_contents($this->task_list_path, serialize($this->task_list));
        }
    }
}
