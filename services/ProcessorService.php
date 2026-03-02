<?php

namespace app\services;

use app\jobs\ProcessLoansJob;
use app\models\LoanRequest;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;

class ProcessorService
{
    const RANDOM_MAX = 100;
    const PROBABILITY_PERCENT = 10;
    const LOAN_TABLE = '{{%loan_requests}}';

    /**
     * Launches processing of loan requests with status 'pending'.
     * @param int $delay
     * @return void
     */
    public function processPendingLoans(int $delay): void
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

    /**
     * Saves user's loan request in db with new status.
     * @param int $userId
     * @param int $delay
     * @return void
     */
    private function processUser(int $userId, int $delay): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $hasApproved = LoanRequest::find()
                ->where(['user_id' => $userId, 'status' => LoanRequest::STATUS_APPROVED])
                ->exists();
            $pendingRequests = $this->getPendingLoanRequestsForUpdate($userId);
            $idsToDecline = [];
            $idsToApprove = [];

            foreach ($pendingRequests as $pendingRequest) {
                if ($hasApproved) {
                    $idsToDecline[] = $pendingRequest['id'];
                } else {
                    $status = (mt_rand(1, self::RANDOM_MAX) <= self::PROBABILITY_PERCENT)
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

                sleep($delay);
            }

            if (!empty($idsToDecline)) {
                Yii::$app->db->createCommand()
                    ->update(self::LOAN_TABLE, ['status' => LoanRequest::STATUS_DECLINED], ['id' => $idsToDecline])
                    ->execute();
            }

            if (!empty($idsToApprove)) {
                Yii::$app->db->createCommand()
                    ->update(self::LOAN_TABLE, ['status' => LoanRequest::STATUS_APPROVED], ['id' => $idsToApprove])
                    ->execute();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            Yii::error('Failed processing user ' . $userId . ': ' . $e->getMessage());
            $transaction->rollBack();
        }
    }

    /**
     * @throws Exception
     */
    private function getPendingLoanRequestsForUpdate(int $userId): array
    {
        return Yii::$app->db->createCommand(
            '
                    SELECT * FROM ' . self::LOAN_TABLE . '
                    WHERE user_id = :userId AND status = :status 
                    FOR UPDATE',
            [
                ':userId' => $userId,
                ':status' => LoanRequest::STATUS_PENDING,
            ]
        )->queryAll();
    }

    /**
     * @throws InvalidConfigException
     */
    public function launchLoansJob(int $delay): void
    {
        $job = Yii::createObject([
            'class' => ProcessLoansJob::class,
            'delay' => $delay,
        ]);
        Yii::$app->queue->push($job);
    }
}