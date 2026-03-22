<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322150059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bot name cache, response probability, and min similarity config fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD telegram_name VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE bot ADD telegram_name_cached_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE bot ADD response_probability DOUBLE PRECISION DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE bot ADD min_similarity DOUBLE PRECISION DEFAULT 0.1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP telegram_name');
        $this->addSql('ALTER TABLE bot DROP telegram_name_cached_at');
        $this->addSql('ALTER TABLE bot DROP response_probability');
        $this->addSql('ALTER TABLE bot DROP min_similarity');
    }
}
