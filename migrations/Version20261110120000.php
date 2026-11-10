<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Production services, portfolio and production page texts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE production_service (id UUID NOT NULL, kind VARCHAR(20) NOT NULL, pricing VARCHAR(10) NOT NULL, delivery_days INT DEFAULT NULL, revisions INT DEFAULT NULL, position INT NOT NULL, title_fr TEXT NOT NULL, title_en TEXT NOT NULL, description_fr TEXT NOT NULL, description_en TEXT NOT NULL, price_cents INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE portfolio_item (id UUID NOT NULL, title VARCHAR(200) NOT NULL, artist VARCHAR(120) NOT NULL, roles JSON NOT NULL, year INT DEFAULT NULL, cover VARCHAR(26) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE production_page (id INT NOT NULL, intro_fr TEXT NOT NULL, intro_en TEXT NOT NULL, process_fr TEXT NOT NULL, process_en TEXT NOT NULL, faq_fr TEXT NOT NULL, faq_en TEXT NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE production_service');
        $this->addSql('DROP TABLE portfolio_item');
        $this->addSql('DROP TABLE production_page');
    }
}
