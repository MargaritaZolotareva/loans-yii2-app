<?php

namespace app\controllers;

use app\models\LoanRequest;
use Throwable;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

class LoansController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionCreate()
    {
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException();
        }

        $model = new LoanRequest();
        $data = Yii::$app->request->getBodyParams();

        if (!$model->load($data, '')) {
            Yii::error('Unable to parse request body' . json_encode($model->errors));
            return ['result' => false];
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $hasApproved = LoanRequest::find()
                ->where(['user_id' => $model->user_id, 'status' => LoanRequest::STATUS_APPROVED])
                ->exists();

            if ($hasApproved) {
                $transaction->rollBack();
                return ['result' => false];
            }

            $model->status = LoanRequest::STATUS_PENDING;

            if (!$model->save()) {
                Yii::error('Unable to save loan request: ' . json_encode($model->errors));
                $transaction->rollBack();
                return ['result' => false];
            }

            $transaction->commit();
            Yii::$app->response->statusCode = 201;
            return [
                'result' => true,
                'id' => $model->id,
            ];
        } catch (Throwable $e) {
            Yii::error('Transaction failed: ' . $e->getMessage());
            $transaction->rollBack();
            return ['result' => false];
        }
    }
}
