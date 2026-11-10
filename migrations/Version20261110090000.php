<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Settings and legal documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id INT NOT NULL, lessons_enabled BOOLEAN NOT NULL, production_enabled BOOLEAN NOT NULL, beats_enabled BOOLEAN NOT NULL, online_checkout BOOLEAN NOT NULL, contact_email VARCHAR(255) NOT NULL, booking_url VARCHAR(255) DEFAULT NULL, logo_image VARCHAR(26) DEFAULT NULL, hero_image VARCHAR(26) DEFAULT NULL, search_indexing BOOLEAN NOT NULL, legal_name VARCHAR(255) NOT NULL, legal_business_form VARCHAR(20) NOT NULL, legal_siren VARCHAR(9) NOT NULL, legal_address TEXT NOT NULL, legal_email VARCHAR(255) NOT NULL, legal_phone VARCHAR(40) NOT NULL, legal_publication_director VARCHAR(255) NOT NULL, legal_mediator_name VARCHAR(255) NOT NULL, legal_mediator_url VARCHAR(255) NOT NULL, legal_host_name VARCHAR(255) NOT NULL, legal_host_address TEXT NOT NULL, vat_consumer VARCHAR(255) NOT NULL, vat_eu_business VARCHAR(255) NOT NULL, vat_non_eu_business VARCHAR(255) NOT NULL, seo_title_fr TEXT NOT NULL, seo_title_en TEXT NOT NULL, seo_description_fr TEXT NOT NULL, seo_description_en TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE legal_document (kind VARCHAR(20) NOT NULL, version INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, body_fr TEXT NOT NULL, body_en TEXT NOT NULL, PRIMARY KEY (kind))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE legal_document');
    }
}
