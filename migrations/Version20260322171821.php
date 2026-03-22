<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322171821 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add fts_weight to bot; add GIN index on chat_message tsvector for FTS';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD fts_weight DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql("CREATE INDEX CONCURRENTLY idx_chat_message_fts ON chat_message USING GIN (to_tsvector('simple', coalesce(text, '')))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP fts_weight');
        $this->addSql('DROP INDEX idx_chat_message_fts');
    }
}
