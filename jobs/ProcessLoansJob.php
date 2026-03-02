<?php

namespace app\jobs;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;
use yii\queue\JobInterface;
use app\services\ProcessorService;
use yii\queue\Queue;

class ProcessLoansJob extends BaseObject implements JobInterface
{
    public int $delay;

    /**
     * @param $queue
     * @return void
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     */
    public function execute($queue): void
    {
        /** @var ProcessorService $service */
        $service = Yii::$container->get(ProcessorService::class);
        $service->processPendingLoans($this->delay);
    }
}