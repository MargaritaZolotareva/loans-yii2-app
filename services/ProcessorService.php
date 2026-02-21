<?php

namespace app\services;

use app\models\LoanRequest;
use Throwable;
use Yii;

class ProcessorService
{
    public function process($delay)
    {
        $userIds = LoanRequest::find()
            ->select('user_id')
            ->where(['status' => LoanRequest::STATUS_PENDING])
            ->distinct()
            ->column();

        foreach ($userIds as $userId) {
            Yii::info(
                'Processor started, PID=' . getmypid() . ", userId={$userId}",
                'processor'
            );
            $start = microtime(true);
            $this->processUser($userId, $delay);
            Yii::info(
                sprintf(
                    'Processor finished, PID=%d, time=%.2fs, userId=%d',
                    getmypid(),
                    microtime(true) - $start,
                    $userId
                ),
                'processor'
            );
        }
    }

    private function processUser($userId, $delay)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $hasApproved = LoanRequest::find()
                ->where(['user_id' => $userId, 'status' => LoanRequest::STATUS_APPROVED])
                ->exists();
            $command = Yii::$app->db->createCommand(
                '
                            SELECT * FROM {{%loan_requests}} 
                            WHERE user_id = :userId AND status = :status 
                            FOR UPDATE',
                [
                    ':userId' => $userId,
                    ':status' => LoanRequest::STATUS_PENDING,
                ]
            );
            $pendingRequests = $command->queryAll();
            $idsToDecline = [];
            $idsToApprove = [];

            foreach ($pendingRequests as $pendingRequest) {
                if ($hasApproved) {
                    $idsToDecline[] = $pendingRequest['id'];
                } else {
                    $status = (mt_rand(1, 100) <= 10)
                        ? LoanRequest::STATUS_APPROVED
                        : LoanRequest::STATUS_DECLINED;

                    if ($status === LoanRequest::STATUS_APPROVED) {
                        $hasApproved = true;
                    }

                    if ($status === LoanRequest::STATUS_APPROVED) {
                        $idsToApprove[] = $pendingRequest['id'];
                    } else {
                        $idsToDecline[] = $pendingRequest['id'];
                    }
                }

                sleep((int)$delay);
            }

            if (!empty($idsToDecline)) {
                Yii::$app->db->createCommand()
                    ->update('{{%loan_requests}}', ['status' => LoanRequest::STATUS_DECLINED], ['id' => $idsToDecline])
                    ->execute();
            }

            if (!empty($idsToApprove)) {
                Yii::$app->db->createCommand()
                    ->update('{{%loan_requests}}', ['status' => LoanRequest::STATUS_APPROVED], ['id' => $idsToApprove])
                    ->execute();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            Yii::error('Failed processing user ' . $userId . ': ' . $e->getMessage());
            $transaction->rollBack();
        }
    }
}