<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009212555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encrypt the phone numbers of speakers';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE data_keys (id BYTEA NOT NULL, scope VARCHAR(100) NOT NULL, wrapped_key BYTEA NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX data_keys_scope_idx ON data_keys (scope, id)');
        $this->addSql('ALTER TABLE speaker ADD phone BYTEA DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE data_keys');
        $this->addSql('ALTER TABLE speaker DROP phone');
    }
}
