<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322175122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add webhook_secret to bot for clone bot webhook validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD webhook_secret VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP webhook_secret');
    }
}
