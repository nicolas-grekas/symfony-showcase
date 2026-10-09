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
        $this->addSql('CREATE TABLE key_management_data_keys (id BYTEA NOT NULL, scope VARCHAR(191) NOT NULL, key_material BYTEA NOT NULL, master_key_id VARCHAR(255) NOT NULL, client VARCHAR(64) NOT NULL, binding BYTEA NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX key_management_data_keys_scope_idx ON key_management_data_keys (scope, id)');
        $this->addSql('ALTER TABLE speaker ADD phone BYTEA DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE key_management_data_keys');
        $this->addSql('ALTER TABLE speaker DROP phone');
    }
}
