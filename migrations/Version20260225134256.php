<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225134256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_export_upload_link ADD bot_id INT NOT NULL');
        $this->addSql('ALTER TABLE chat_export_upload_link ADD CONSTRAINT FK_36B8933192C1C487 FOREIGN KEY (bot_id) REFERENCES bot (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_36B8933192C1C487 ON chat_export_upload_link (bot_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_export_upload_link DROP CONSTRAINT FK_36B8933192C1C487');
        $this->addSql('DROP INDEX IDX_36B8933192C1C487');
        $this->addSql('ALTER TABLE chat_export_upload_link DROP bot_id');
    }
}
