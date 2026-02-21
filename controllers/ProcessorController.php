<?php

namespace app\controllers;

use app\jobs\ProcessLoansJob;
use Yii;
use yii\web\Controller;

class ProcessorController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        $delay = Yii::$app->request->get('delay', 5);

        if (!is_numeric($delay) || (int)$delay < 0) {
            return ['result' => false, 'message' => 'Invalid delay value.'];
        }
        Yii::$app->queue->push(new ProcessLoansJob([
            'delay' => (int)$delay,
        ]));

        return ['result' => true];
    }
}