<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110101633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lesson offers, lessons page texts and contact messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (id UUID NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, topic VARCHAR(20) NOT NULL, offer_ref VARCHAR(60) DEFAULT NULL, offer_label VARCHAR(200) DEFAULT NULL, body TEXT NOT NULL, locale VARCHAR(2) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, handled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX contact_message_received_at ON contact_message (received_at)');
        $this->addSql('CREATE TABLE lesson_offer (id UUID NOT NULL, kind VARCHAR(10) NOT NULL, sessions INT NOT NULL, format VARCHAR(20) NOT NULL, minutes INT NOT NULL, position INT NOT NULL, title_fr TEXT NOT NULL, title_en TEXT NOT NULL, description_fr TEXT NOT NULL, description_en TEXT NOT NULL, price_cents INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE lessons_page (id INT NOT NULL, intro_fr TEXT NOT NULL, intro_en TEXT NOT NULL, faq_fr TEXT NOT NULL, faq_en TEXT NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE lesson_offer');
        $this->addSql('DROP TABLE lessons_page');
    }
}
