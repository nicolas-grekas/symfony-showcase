<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027150923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Embed the proposals into a pgvector column, for the similarity search of the review assistant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        // the size of the vectors of AI_EMBEDDING_MODEL
        $this->addSql('ALTER TABLE proposal ADD embedding vector(384) DEFAULT NULL');
        // the review assistant compares proposals by their cosine distance
        $this->addSql('CREATE INDEX proposal_embedding_idx ON proposal USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX proposal_embedding_idx');
        $this->addSql('ALTER TABLE proposal DROP embedding');
    }
}
