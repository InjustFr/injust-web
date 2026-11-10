<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the SEO of the removed links page, the links now live on the home page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM page_seo WHERE page_key = 'links'");
    }

    public function down(Schema $schema): void
    {
    }
}
