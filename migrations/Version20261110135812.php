<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110135812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Links page bio links, per-page SEO and the Search Console verification token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bio_link (id UUID NOT NULL, url VARCHAR(255) NOT NULL, icon VARCHAR(20) DEFAULT NULL, highlighted BOOLEAN NOT NULL, visible BOOLEAN NOT NULL, clicks INT NOT NULL, position INT NOT NULL, label_fr TEXT NOT NULL, label_en TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE page_seo (page_key VARCHAR(20) NOT NULL, image VARCHAR(26) DEFAULT NULL, title_fr TEXT NOT NULL, title_en TEXT NOT NULL, description_fr TEXT NOT NULL, description_en TEXT NOT NULL, PRIMARY KEY (page_key))');
        $this->addSql('ALTER TABLE settings ADD google_site_verification VARCHAR(100) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bio_link');
        $this->addSql('DROP TABLE page_seo');
        $this->addSql('ALTER TABLE settings DROP google_site_verification');
    }
}
