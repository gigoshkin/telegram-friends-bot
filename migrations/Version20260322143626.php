<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322143626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pg_trgm extension and add GIN trigram index on chat_message.text for fast similarity search';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chat_message_text_trgm ON chat_message USING GIN (text gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_chat_message_text_trgm');
        $this->addSql('DROP EXTENSION IF EXISTS pg_trgm');
    }
}
