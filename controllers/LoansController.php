<?php

namespace app\controllers;

use app\services\LoansService;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

class LoansController extends Controller
{
    public $enableCsrfValidation = false;
    private LoansService $loansService;

    public function __construct($id, $module, LoansService $loansService, $config = [])
    {
        $this->loansService = $loansService;
        parent::__construct($id, $module, $config);
    }

    /**
     * @return array
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionCreate(): array
    {
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException();
        }

        $result = $this->loansService->handleCreate();

        Yii::$app->response->statusCode = $result['statusCode'] ?? 200;
        unset($result['statusCode']);

        return $result;
    }
}
