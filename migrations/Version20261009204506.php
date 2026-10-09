<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009204506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sell tickets';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket (id UUID NOT NULL, status VARCHAR(255) NOT NULL, price INT NOT NULL, held_until TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payment_reference VARCHAR(255) DEFAULT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, conference_id UUID NOT NULL, buyer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_97A0ADA3604B8382 ON ticket (conference_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA36C755722 ON ticket (buyer_id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3604B8382 FOREIGN KEY (conference_id) REFERENCES conference (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA36C755722 FOREIGN KEY (buyer_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conference ADD ticket_capacity INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE conference ADD ticket_price INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE conference ALTER ticket_capacity DROP DEFAULT');
        $this->addSql('ALTER TABLE conference ALTER ticket_price DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3604B8382');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA36C755722');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('ALTER TABLE conference DROP ticket_capacity');
        $this->addSql('ALTER TABLE conference DROP ticket_price');
    }
}
