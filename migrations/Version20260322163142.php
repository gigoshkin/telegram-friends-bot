<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322163142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add direct_response_probability to bot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot ADD direct_response_probability DOUBLE PRECISION DEFAULT 0.5 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bot DROP direct_response_probability');
    }
}
