<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322165015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index for sequential pair lookup';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_sequential_lookup ON chat_message (chat_export_file_id, from_id, sent_at, telegram_message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sequential_lookup');
    }
}
