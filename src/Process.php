<?php
namespace timeingTask;

/**
 * @小智
 * crontab主进程
 * 目前已知问题：当进程处于框架中执行时，被执行的文件在启动时就已经包含了进来，所以如果进程已经启动，那么代码修改将无效。
 */
class Process
{
    // 进程休眠时间
    private $sleep = 1;
    // 上一份任务列表
    private $last_task_list = [];
    // 文件中的任务列表
    private $file_task_list = [];
    // 当前等待执行的任务列表
    private $wait_task_list = [];
    // 当前准备执行的任务列表
    private $exec_task_list = [];
    // 当前时间戳
    private $time;
    // 进程锁文件
    private $lock_path = __DIR__ . '/LOCK.php';
    // 任务列表文件
    private $task_list_path = __DIR__ . '/TASKLIST';

    /**
     * [checkLock 检查运行锁]
     */
    private function checkLock()
    {
        if (!require ($this->lock_path)) {
            $this->log('进程退出');
            exit();
        }
    }

    /**
     * [refreshTaskList 从文件刷新任务列表]
     */
    private function refreshTaskList()
    {
        $this->file_task_list = unserialize(file_get_contents($this->task_list_path));

        if (empty($this->file_task_list)) {
            return;
        }

        // 新增的任务
        $new_task = array_diff_key($this->file_task_list, $this->last_task_list);
        // 被删除的任务
        $del_task = array_diff_key($this->last_task_list, $this->file_task_list);

        foreach ($del_task as $task_id => $task) {
            unset($this->wait_task_list[$task_id]);
            $this->log("删除任务:任务ID：{$task_id}，任务名：{$task['task_name']}");
        }

        foreach ($new_task as $task_id => $task) {
            $this->wait_task_list[$task_id] = [
                'exec_time'  => $this->time + $task['interval'],
                'exec_count' => 0,
            ];

            $this->log("新任务载入:任务ID：{$task_id}，任务名：{$task['task_name']}");
        }

        // 刷新上一份数据
        $this->last_task_list = $this->file_task_list;
    }

    /**
     * [refreshRunTasks 从任务列表刷新可执行任务列表]
     */
    private function refreshWaitTasks()
    {
        foreach ($this->wait_task_list as $task_id => $task) {
            if ($this->time >= $task['exec_time']) {
                $this->exec_task_list[] = $task_id;
            }
        }
    }

    /**
     * [execAllTask 执行列表中所有任务]
     */
    private function execAllTask()
    {
        foreach ($this->exec_task_list as $task_id) {

            $this->execTask($task_id);
            $this->execDone($task_id);

        }

        $this->exec_task_list = [];
    }

    /**
     * [execTask 执行某个任务]
     * @param  string $task_name [任务id]
     */
    private function execTask(string $task_id)
    {
        $namespace = $this->file_task_list[$task_id]['namespace'];
        $params    = $this->file_task_list[$task_id]['params'];
        $task_name = $this->file_task_list[$task_id]['task_name'];

        ob_start();

        try {

            $class = $namespace . "\\" . $task_name;
            $class = new $class($params);
            $class->run();

        } catch (\Exception $e) {
            dump($e->getMessage());
        } finally {
            if (ob_get_length() > 0) {
                $this->log(ob_get_contents(), $task_id, 'task_run_print');
            }

        }

        ob_end_clean();
    }

    /**
     * [execDone 某个任务执行完毕]
     * @param  string $task_name [任务名字]
     */
    private function execDone(string $task_id)
    {
        $wait = &$this->wait_task_list[$task_id];
        $task = &$this->file_task_list[$task_id];

        $wait['exec_count']++;
        $wait['exec_time'] = $this->time + $task['interval'];

        $this->log("{$task['task_name']}第{$wait['exec_count']}次执行成功。", $task_id);

        if (!empty($task['exec_count']) && $task['exec_count'] == $wait['exec_count']) {

            $this->log("{$task['task_name']}任务执行完毕。");

            unset($this->wait_task_list[$task_id]);
            unset($this->file_task_list[$task_id]);

            file_put_contents($this->task_list_path, serialize($this->file_task_list));

        }

    }

    /**
     * [log 日志记录器]
     * @param  string $log [日志内容]
     */
    private function log(string $log, string $task_id = null, string $type = 'info')
    {
        $content = "[{$type}] " . date('Y-m-d H:i:s', $this->time) . ' ';
        $content .= $log;
        $content .= "\r\n";

        echo $content;

        if (empty($task_id)) {
            file_put_contents(__DIR__ . "/runtime.log", $content, FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . "/logs/{$this->file_task_list[$task_id]['task_name']}_{$task_id}.log", $content, FILE_APPEND);
        }

    }

    /**
     * [main 进程]
     */
    public function start()
    {
        $this->time = time();
        $this->log('进程启动');

        file_put_contents($this->task_list_path, serialize([]));

        do {
            // 时间是基础
            $this->time = time();
            // 检查锁
            $this->checkLock();
            // 从文件刷新任务列表
            $this->refreshTaskList();
            // 从文件任务列表刷新等待执行列表
            $this->refreshWaitTasks();
            // 执行列表中的所有任务
            $this->execAllTask();
            // 休息一秒，系统继续运行
            sleep($this->sleep);
        } while (true);
    }
}
