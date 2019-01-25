# php-timing-task

> 1.启动进程
```
php ./TaskProcess.php 
```
> 2.示例

```

$task             = new Task($task_id);
$task->url  = 'https://www.baidu.com';  //支持url回调
$task->interval   = 10; //延迟多久执行
$task->exec_count = 1; //0 无限循环，1最起码执行一次
$task->start();
```
