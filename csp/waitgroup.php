<?php
class waitgroup
{

    private $count = 0;
    private $chan;

    /**
     * waitgroup constructor.
     * @desc 初始化一个channel
     */
    public function __construct()
    {
        $this->chan = new \chan();
    }

    /**
     * @desc 计数+1
     * @调用时机：在起一个协程前
     */
    public function add()
    {
        $this->count++;
    }

    /**
     * @desc 协程处理完成时调用
     */
    public function done($response)
    {
        $this->chan->push($response);
    }

    /**
     * @desc 堵塞的等待所有的协程处理完成
     */
    public function wait()
    {
        for ($i = 0; $i < $this->count; $i++) {
            //调用pop方法时，如果没有数据，此协程会挂起
            //当往chan中push数据后，协程会被恢复
            echo $this->chan->pop()."\r\n";
        }
    }
}
