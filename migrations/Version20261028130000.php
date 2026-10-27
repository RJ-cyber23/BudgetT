<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make usernames unique and add an owner to transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD UNIQUE INDEX UNIQ_USERS_USERNAME (username)');
        $this->addSql('ALTER TABLE `transaction` ADD user_id INT UNSIGNED DEFAULT NULL');
        $this->addSql('UPDATE `transaction` t INNER JOIN budget b ON t.budget_id = b.budget_id SET t.user_id = b.user_id WHERE t.user_id IS NULL');
        // Legacy data predates accounts. If there is exactly one account, it owns the
        // remaining uncategorized transactions too.
        $this->addSql('UPDATE `transaction` SET user_id = (SELECT MIN(user_id) FROM users) WHERE user_id IS NULL AND (SELECT COUNT(*) FROM users) = 1');
        $this->addSql('ALTER TABLE `transaction` ADD INDEX IDX_TRANSACTION_USER (user_id)');
        $this->addSql('ALTER TABLE `transaction` ADD CONSTRAINT FK_TRANSACTION_USER FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `transaction` DROP FOREIGN KEY FK_TRANSACTION_USER');
        $this->addSql('ALTER TABLE `transaction` DROP INDEX IDX_TRANSACTION_USER');
        $this->addSql('ALTER TABLE `transaction` DROP user_id');
        $this->addSql('ALTER TABLE users DROP INDEX UNIQ_USERS_USERNAME');
    }
}
