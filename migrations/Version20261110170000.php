<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Withdrawal requests, credit notes, refunded amounts and the seller VAT number';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_note (id UUID NOT NULL, number VARCHAR(9) NOT NULL, invoice_number VARCHAR(9) NOT NULL, order_id UUID NOT NULL, order_reference VARCHAR(10) NOT NULL, locale VARCHAR(2) NOT NULL, issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, seller JSON NOT NULL, buyer JSON NOT NULL, lines JSON NOT NULL, vat_mention VARCHAR(255) NOT NULL, reason TEXT DEFAULT NULL, stripe_refund_id VARCHAR(255) NOT NULL, mailed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, amount_cents INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C87F452996901F54 ON credit_note (number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C87F4529A715271F ON credit_note (stripe_refund_id)');
        $this->addSql('CREATE INDEX credit_note_order ON credit_note (order_id)');
        $this->addSql('CREATE INDEX credit_note_issued_at ON credit_note (issued_at)');
        $this->addSql('CREATE TABLE withdrawal_request (id UUID NOT NULL, order_id UUID NOT NULL, order_reference VARCHAR(10) NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(100) DEFAULT NULL, reason TEXT DEFAULT NULL, locale VARCHAR(2) NOT NULL, received_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, acknowledged_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E56F6D78D9F6D38 ON withdrawal_request (order_id)');
        $this->addSql('ALTER TABLE sales_order ADD refunded_cents INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE sales_order ADD withdrawn_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE sales_order SET refunded_cents = price_cents WHERE status = 'refunded'");
        $this->addSql('ALTER TABLE settings ADD legal_vat_number VARCHAR(14) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credit_note');
        $this->addSql('DROP TABLE withdrawal_request');
        $this->addSql('ALTER TABLE sales_order DROP refunded_cents');
        $this->addSql('ALTER TABLE sales_order DROP withdrawn_at');
        $this->addSql('ALTER TABLE settings DROP legal_vat_number');
    }
}
