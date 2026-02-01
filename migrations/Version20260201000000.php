<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create contents table with indexes for search and scoring';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE contents (
                id SERIAL PRIMARY KEY,
                provider_id VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(500) NOT NULL,
                type VARCHAR(20) NOT NULL,
                metrics JSON NOT NULL,
                published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                score DOUBLE PRECISION DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        ');

        $this->addSql('CREATE INDEX idx_content_type ON contents (type)');
        $this->addSql('CREATE INDEX idx_content_score ON contents (score)');
        $this->addSql('CREATE INDEX idx_published_at ON contents (published_at)');
        $this->addSql('COMMENT ON COLUMN contents.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contents.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contents.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contents');
    }
}
