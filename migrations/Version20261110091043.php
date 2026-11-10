<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110091043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Music releases, videos, texts and social links; host phone in the legal identity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE music_release (id UUID NOT NULL, title VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL, type VARCHAR(10) NOT NULL, release_date DATE NOT NULL, cover VARCHAR(26) DEFAULT NULL, tracks JSON NOT NULL, links JSON NOT NULL, smart_link_url VARCHAR(255) DEFAULT NULL, spotify_embed VARCHAR(60) DEFAULT NULL, featured BOOLEAN NOT NULL, position INT NOT NULL, description_fr TEXT NOT NULL, description_en TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5AB39166989D9B62 ON music_release (slug)');
        $this->addSql('CREATE TABLE music_social_link (id UUID NOT NULL, network VARCHAR(20) NOT NULL, url VARCHAR(255) NOT NULL, label VARCHAR(60) DEFAULT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE music_text (id INT NOT NULL, hooks JSON NOT NULL, portrait VARCHAR(26) DEFAULT NULL, tagline_fr TEXT NOT NULL, tagline_en TEXT NOT NULL, manifesto_fr TEXT NOT NULL, manifesto_en TEXT NOT NULL, bio_fr TEXT NOT NULL, bio_en TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE music_video (id UUID NOT NULL, youtube_id VARCHAR(11) NOT NULL, title VARCHAR(200) NOT NULL, thumbnail VARCHAR(26) DEFAULT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql("ALTER TABLE settings ADD legal_host_phone VARCHAR(40) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE settings ALTER legal_host_phone DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE music_release');
        $this->addSql('DROP TABLE music_social_link');
        $this->addSql('DROP TABLE music_text');
        $this->addSql('DROP TABLE music_video');
        $this->addSql('ALTER TABLE settings DROP legal_host_phone');
    }
}
