<?php

namespace app\services;

use app\models\LoanRequest;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;

class LoansService
{
    /**
     * @return array|false[]
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function handleCreate(): array
    {
        $model = new LoanRequest();
        $data = Yii::$app->request->getBodyParams();
        if (!$model->load($data, '')) {
            Yii::error('Unable to parse request body' . json_encode($model->errors));
            return ['result' => false];
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $hasApproved = $this->hasApprovedRequest($model->user_id);

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

            return [
                'result' => true,
                'id' => $model->id,
                'statusCode' => 201,
            ];
        } catch (Throwable $e) {
            Yii::error('Transaction failed: ' . $e->getMessage());
            $transaction->rollBack();
            return ['result' => false];
        }
    }

    /**
     * @param int $userId
     * @return bool
     */
    private function hasApprovedRequest(int $userId): bool
    {
        return LoanRequest::find()
            ->where(['user_id' => $userId, 'status' => LoanRequest::STATUS_APPROVED])
            ->exists();
    }
}