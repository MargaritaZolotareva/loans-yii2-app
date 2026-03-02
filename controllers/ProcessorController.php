<?php

namespace app\controllers;

use app\services\ProcessorService;
use InvalidArgumentException;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\Controller;

class ProcessorController extends Controller
{
    const DELAY_DEFAULT_VALUE = 5;

    public $enableCsrfValidation = false;
    private ProcessorService $processorService;

    public function __construct($id, $module, ProcessorService $procService, $config = [])
    {
        $this->processorService = $procService;
        parent::__construct($id, $module, $config);
    }

    /**
     * @return array{result: bool, message?: string}
     * @throws InvalidConfigException
     */
    public function actionIndex(): array
    {
        try {
            $delay = $this->getDelay();
            $this->processorService->launchLoansJob($delay);

            return ['result' => true];
        } catch (InvalidArgumentException $e) {
            return ['result' => false, 'message' => 'Invalid delay value.'];
        }
    }

    private function getDelay(): int
    {
        $delay = Yii::$app->request->get('delay', self::DELAY_DEFAULT_VALUE);

        if (!is_numeric($delay) || (int)$delay < 0) {
            throw new InvalidArgumentException('Invalid delay value');
        }

        return $delay;
    }
}