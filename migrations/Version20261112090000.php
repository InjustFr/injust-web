<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Releases get a UPC, auto platform links, hidden platforms and click counts per platform';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE music_release ADD upc VARCHAR(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE music_release ADD links_checked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql("ALTER TABLE music_release ADD hidden_platforms JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE music_release ALTER hidden_platforms DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5AB3916656FF3ABD ON music_release (upc)');
        $this->addSql("UPDATE music_release SET links = (SELECT COALESCE(json_agg(l.link::jsonb || '{\"source\": \"manual\"}'::jsonb ORDER BY l.n), '[]'::json) FROM json_array_elements(links) WITH ORDINALITY AS l(link, n))");
        $this->addSql('CREATE TABLE release_link_click (platform VARCHAR(20) NOT NULL, clicks INT NOT NULL, release_id UUID NOT NULL, PRIMARY KEY (release_id, platform))');
        $this->addSql('CREATE INDEX IDX_78AA9444B12A727D ON release_link_click (release_id)');
        $this->addSql('ALTER TABLE release_link_click ADD CONSTRAINT FK_78AA9444B12A727D FOREIGN KEY (release_id) REFERENCES music_release (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE release_link_click');
        $this->addSql('DROP INDEX UNIQ_5AB3916656FF3ABD');
        $this->addSql("UPDATE music_release SET links = (SELECT COALESCE(json_agg(l.link::jsonb - 'source' ORDER BY l.n), '[]'::json) FROM json_array_elements(links) WITH ORDINALITY AS l(link, n))");
        $this->addSql('ALTER TABLE music_release DROP upc');
        $this->addSql('ALTER TABLE music_release DROP links_checked_at');
        $this->addSql('ALTER TABLE music_release DROP hidden_platforms');
    }
}
