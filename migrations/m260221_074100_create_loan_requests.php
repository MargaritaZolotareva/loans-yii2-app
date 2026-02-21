<?php

use app\models\LoanRequest;
use yii\db\Migration;

class m260221_074100_create_loan_requests extends Migration
{
    const TABLE = '{{%loan_requests}}';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'amount' => $this->integer()->notNull(),
            'term' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue(LoanRequest::STATUS_PENDING),
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);

        $this->execute("
            CREATE UNIQUE INDEX uq_loan_requests_user_id_approved
            ON " . self::TABLE . " (user_id)
            WHERE status = 'approved';
        ");

        $this->createIndex(
            'idx_loan_requests_user_id',
            self::TABLE,
            'user_id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('uq_loan_requests_user_id_approved', self::TABLE);
        $this->dropTable(self::TABLE);
    }
}
