<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820051528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact ADD firstname VARCHAR(100) DEFAULT NULL, ADD lastname VARCHAR(100) DEFAULT NULL, ADD phone VARCHAR(50) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD last_database_sync DATETIME DEFAULT NULL, CHANGE name company VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4C62E638B9DD01AC ON contact (hubspot_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4C62E638E7927C74 ON contact (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_4C62E638B9DD01AC ON contact');
        $this->addSql('DROP INDEX UNIQ_4C62E638E7927C74 ON contact');
        $this->addSql('ALTER TABLE contact DROP firstname, DROP lastname, DROP phone, DROP created_at, DROP updated_at, DROP last_database_sync, CHANGE company name VARCHAR(255) DEFAULT NULL');
    }
}
