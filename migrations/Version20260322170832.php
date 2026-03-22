<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322170832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add match_limit to bot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD match_limit INT DEFAULT 5 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP match_limit');
    }
}
