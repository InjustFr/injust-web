<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beat orders keep their licence snapshot an exclusive conflict date and when its notice went out';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order ADD licence JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_order ADD exclusive_conflict_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_order ADD conflict_notice_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order DROP licence');
        $this->addSql('ALTER TABLE sales_order DROP exclusive_conflict_at');
        $this->addSql('ALTER TABLE sales_order DROP conflict_notice_sent_at');
    }
}
