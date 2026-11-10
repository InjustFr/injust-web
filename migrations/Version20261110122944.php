<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110122944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sales: orders, invoices and the yearly invoice sequence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice (id UUID NOT NULL, number VARCHAR(9) NOT NULL, order_id UUID NOT NULL, order_reference VARCHAR(10) NOT NULL, locale VARCHAR(2) NOT NULL, issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, seller JSON NOT NULL, buyer JSON NOT NULL, lines JSON NOT NULL, vat_case VARCHAR(20) NOT NULL, vat_mention VARCHAR(255) NOT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, payment_method VARCHAR(20) NOT NULL, credited_invoice_number VARCHAR(9) DEFAULT NULL, total_cents INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9065174496901F54 ON invoice (number)');
        $this->addSql('CREATE INDEX invoice_order ON invoice (order_id)');
        $this->addSql('CREATE TABLE invoice_sequence (year INT NOT NULL, last INT NOT NULL, PRIMARY KEY (year))');
        $this->addSql('CREATE TABLE sales_order (id UUID NOT NULL, reference VARCHAR(10) NOT NULL, product_type VARCHAR(20) NOT NULL, product_id VARCHAR(26) NOT NULL, product_label VARCHAR(255) NOT NULL, product_format VARCHAR(20) DEFAULT NULL, locale VARCHAR(2) NOT NULL, email VARCHAR(180) NOT NULL, consents JSON NOT NULL, cgv_version INT NOT NULL, cgv_body_fr TEXT NOT NULL, cgv_body_en TEXT NOT NULL, cgv_published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, checkout_session_id VARCHAR(255) DEFAULT NULL, payment_intent_id VARCHAR(255) DEFAULT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, customer JSON DEFAULT NULL, invoice_number VARCHAR(9) DEFAULT NULL, confirmation_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, price_cents INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_36D222EAEA34913 ON sales_order (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_36D222EDA6D31C4 ON sales_order (checkout_session_id)');
        $this->addSql('CREATE INDEX sales_order_created_at ON sales_order (created_at)');
        $this->addSql('CREATE INDEX sales_order_paid_at ON sales_order (paid_at)');
        $this->addSql('CREATE INDEX sales_order_payment_intent ON sales_order (payment_intent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_sequence');
        $this->addSql('DROP TABLE sales_order');
    }
}
