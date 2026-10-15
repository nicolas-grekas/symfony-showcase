<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015082037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the events that the inbox of the events transport handled';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_inbox (consumer VARCHAR(190) NOT NULL, message_id VARCHAR(190) NOT NULL, handled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (consumer, message_id))');
        $this->addSql('CREATE INDEX IDX_45CFA50D705B37276F4B26C ON messenger_inbox (consumer, handled_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_inbox');
    }
}
