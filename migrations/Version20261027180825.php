<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The initial schema for the SQLite deployment.
 */
final class Version20261027180825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial SQLite schema.';
    }

    public function up(Schema $schema): void
    {
        // The legacy MySQL schema is managed by Version20261028130000.
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('CREATE TABLE budget (budget_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, b_description VARCHAR(255) DEFAULT NULL, amount NUMERIC(15, 2) DEFAULT NULL, currency_type VARCHAR(3) DEFAULT NULL, create_at DATETIME NOT NULL, update_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE "transaction" (transac_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, t_description VARCHAR(255) DEFAULT NULL, amount NUMERIC(15, 2) DEFAULT NULL, create_at DATETIME NOT NULL, update_at DATETIME NOT NULL, budget_id INTEGER DEFAULT NULL, CONSTRAINT FK_723705D136ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (budget_id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_TRANSACTION_USER ON "transaction" (user_id)');
        $this->addSql('CREATE INDEX IDX_723705D136ABA6B8 ON "transaction" (budget_id)');
        $this->addSql('CREATE TABLE users (user_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('DROP TABLE budget');
        $this->addSql('DROP TABLE "transaction"');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
