<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uploaded images get an optional focus point';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image_focus (image VARCHAR(26) NOT NULL, x SMALLINT NOT NULL, y SMALLINT NOT NULL, PRIMARY KEY (image))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE image_focus');
    }
}
