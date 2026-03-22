<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322164313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add response_mode (direct|hybrid) and sequential_weight to bot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE bot ADD response_mode VARCHAR(255) DEFAULT 'direct' NOT NULL");
        $this->addSql('ALTER TABLE bot ADD sequential_weight DOUBLE PRECISION DEFAULT 0.3 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP response_mode');
        $this->addSql('ALTER TABLE bot DROP sequential_weight');
    }
}
