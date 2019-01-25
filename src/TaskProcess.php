<?php

/**
 * @小智
 * 自豪的采用CLI驱动
 */
class TaskProcess
{
    // 进程休眠时间
    private $sleep = 1;
    // 上一份任务列表
    private $last_list = [];
    // 文件中的任务列表
    private $task_list = [];
    // 当前等待执行的任务列表
    private $wait_list = [];
    // 当前准备执行的任务列表
    private $exec_list = [];
    // 当前时间戳
    private $time;
    // 任务列表文件
    private $task_path = __DIR__ . '/task_list';
    private $log_path  = __DIR__ . '/logs';

    /**
     * [__construct 启动进程]
     */
    public function __construct()
    {
        $this->start();
    }

    /**
     * [loadingTasks 从文件中载入任务和检查任务]
     */
    private function loadingTasks()
    {
        $this->task_list = unserialize(file_get_contents($this->task_path));

        // 新增的任务
        $new_task = array_diff_key($this->task_list, $this->last_list);
        // 被删除的任务
        $del_task = array_diff_key($this->last_list, $this->task_list);

        foreach ($del_task as $task_id => $task) {
            unset($this->wait_list[$task_id]);
            $this->log("任务删除:ID:{$task_id};描述:{$task['content']}");
        }

        foreach ($new_task as $task_id => $task) {
            $this->wait_list[$task_id] = [
                'exec_time'  => $this->time + $task['interval'],
                'exec_count' => 0,
            ];

            $this->log("任务载入:ID:{$task_id};描述:{$task['content']}");
        }

        // 刷新上一份数据
        $this->last_list = $this->task_list;
    }

    /**
     * [loadingWait 从任务列表刷新可执行任务列表]
     */
    private function loadingWait()
    {
        foreach ($this->wait_list as $task_id => $task) {
            if ($this->time >= $task['exec_time']) {
                $this->exec_list[] = $task_id;
            }
        }
    }

    /**
     * [execAllTask 执行列表中所有任务]
     */
    private function execAllTask()
    {
        foreach ($this->exec_list as $task_id) {
            $this->execTask($task_id);
        }

        $this->exec_list = [];
    }

    /**
     * [execTask 执行某个任务]
     * @param  string $task_id [任务id]
     */
    private function execTask(string $task_id)
    {
        $url = $this->task_list[$task_id]['url'];

        try {
            $result = $this->get($url);

            if ($result) {
                $this->execSuccess($task_id);
            }else{
                $this->execFail($task_id);

                throw new \Exception("失败日志:".serialize($this->task_list[$task_id]));
                echo '失败'.$task_id . PHP_EOL;
            }


        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * [execSuccess 某个任务执行完成]
     * @param  string $task_id [任务id]
     */
    private function execSuccess(string $task_id)
    {
        $wait = &$this->wait_list[$task_id];
        $task = &$this->task_list[$task_id];

        $wait['exec_count']++;
        $wait['exec_time'] = $this->time + $task['interval'];

        $this->log("执行{$wait['exec_count']}次:{$task_id}", $task_id);

        if (!empty($task['exec_count']) && $task['exec_count'] == $wait['exec_count']) {

            $this->log("执行完毕:ID:{$task_id};描述:{$task['content']} ");

            unset($this->wait_list[$task_id]);
            unset($this->task_list[$task_id]);

            file_put_contents($this->task_path, serialize($this->task_list));
        }

    }

    /**
     * [execFail 任务执行失败-一般情况是连接数达到上限get请求出错，将任务延迟一秒]
     * @param  string $task_id [任务id]
     */
    private function execFail(string $task_id)
    {
        $this->wait_list[$task_id]['exec_time']++;
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

        // 当日志超过100M时，清空日志文件
        if (filesize($this->log_path) >= 104857600) {
            file_put_contents($this->log_path, serialize([]));
        }

        file_put_contents($this->log_path, $content, FILE_APPEND);
    }

    /**
     * [main 进程]
     */
    private function start()
    {
        $this->time = time();
        $this->log('进程启动，自豪的采用CLI驱动 —— 小智');

        file_put_contents($this->task_path, serialize([]));

        do {
            // 时间是基础
            $this->time = time();
            // 从文件刷新任务列表
            $this->loadingTasks();
            // 从文件任务列表刷新等待执行列表
            $this->loadingWait();
            // 执行列表中的所有任务
            $this->execAllTask();
            // 休息一秒，系统继续运行
            sleep($this->sleep);
        } while (true);
    }

    /**
     * [get 发送一个异步的get请求]
     * @param  string $url [地址]
     * @return boolean 执行成功/失败
     */
    private function get(string $url)
    {
        $urlinfo = parse_url($url);
        $host    = $urlinfo['host'];

        // 创建连接
        switch ($urlinfo['scheme']) {
            case 'http':
                $fp = fsockopen($host, 80);
                break;
            case 'https':
                $fp = fsockopen('ssl://' . $host, 443);
                break;
            default:
                throw new \Exception('协议错误');
                break;
        }

        // 组装请求数据
        $header = [
            "GET {$url} HTTP/1.1",
            "Host: {$host}",
            'Content-Length: 0',
            'Connection: close',
        ];

        $out = implode("\r\n", $header) . "\r\n\r\n";

        if (!is_resource($fp)) {
            return false;
        }

        // 发送数据
        fwrite($fp, $out);
        fclose($fp);

        return true;
    }
}
date_default_timezone_set('Asia/Shanghai');
new TaskProcess;
