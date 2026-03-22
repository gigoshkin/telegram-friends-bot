<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322162315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add debug_mode flag to bot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD debug_mode BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP debug_mode');
    }
}
