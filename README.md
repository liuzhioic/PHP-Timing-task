# php-timing-task

> 1.Test 任务类
```
namespace app\crontab;


class Test
{
	private $params;

	/**
	 * [__construct 接收一个参数，序列化的]
	 * @param string $params 
	 */
	function __construct(string $params)
	{
		$this->params = unserialize($params);
	}

	/**
	 * [run 定时器主方法]
	 */
	public function run()
	{
        /* 做点什么……*/ 
        /* do something */
	}
}
```
> 2.示例

```
use timeingTask\Task;

$task             = new Task();
$task->task_name  = 'Test';  //任务名字
$task->interval   = 10; //延迟多久执行
$task->exec_count = 1; //0 无限循环，1最起码执行一次
$task->namespace  = '\\app\\crontab'; //任务命名空间
$task->params     = 'test';//任务参数
$task->start();
```
