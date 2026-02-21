<?php

namespace app\jobs;

use yii\base\BaseObject;
use yii\queue\JobInterface;
use app\services\ProcessorService;

class ProcessLoansJob extends BaseObject implements JobInterface
{
    public int $delay;

    public function execute($queue)
    {
        $service = new ProcessorService();
        $service->process($this->delay);
    }
}