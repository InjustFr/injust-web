<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110162935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beats catalogue: beats, licence tiers and the beats page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE beat (id UUID NOT NULL, title VARCHAR(120) NOT NULL, slug VARCHAR(200) NOT NULL, bpm INT NOT NULL, musical_key VARCHAR(20) NOT NULL, tags JSON NOT NULL, cover VARCHAR(26) DEFAULT NULL, preview JSON DEFAULT NULL, files JSON NOT NULL, visible BOOLEAN NOT NULL, sold_exclusively_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F9069C989D9B62 ON beat (slug)');
        $this->addSql('CREATE TABLE beats_page (id INT NOT NULL, intro_fr TEXT NOT NULL, intro_en TEXT NOT NULL, faq_fr TEXT NOT NULL, faq_en TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE licence_tier (id UUID NOT NULL, deliverables JSON NOT NULL, exclusive BOOLEAN NOT NULL, stream_cap INT DEFAULT NULL, copy_cap INT DEFAULT NULL, video_cap INT DEFAULT NULL, position INT NOT NULL, name_fr TEXT NOT NULL, name_en TEXT NOT NULL, price_cents INT NOT NULL, summary_fr TEXT NOT NULL, summary_en TEXT NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE beat');
        $this->addSql('DROP TABLE beats_page');
        $this->addSql('DROP TABLE licence_tier');
    }
}
