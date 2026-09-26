<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema, plus the starting rows for the lookup tables so a fresh
 * database has ratings, types and terpenes to pick from straight away.
 */
final class Version20260926072038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the strain tracker schema and seed ratings, strain types and terpenes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(80) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE brand (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_1C52F9585E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(40) NOT NULL, score SMALLINT NOT NULL, colour VARCHAR(7) DEFAULT \'#10b981\' NOT NULL, UNIQUE INDEX UNIQ_D8892622EA750E8 (label), UNIQUE INDEX UNIQ_D889262232993751 (score), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strain (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, thc_percent NUMERIC(4, 1) DEFAULT NULL, genetics VARCHAR(255) DEFAULT NULL, price NUMERIC(7, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, brand_id INT NOT NULL, type_id INT DEFAULT NULL, a_rating_id INT DEFAULT NULL, m_rating_id INT DEFAULT NULL, UNIQUE INDEX uniq_strain_brand_name (brand_id, name), INDEX IDX_A630CD7244F5D008 (brand_id), INDEX IDX_A630CD72C54C8C93 (type_id), INDEX IDX_A630CD725EF9DEB9 (a_rating_id), INDEX IDX_A630CD7254E34C7E (m_rating_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strain_terpene (strain_id INT NOT NULL, terpene_id INT NOT NULL, INDEX IDX_710F98E969B9E007 (strain_id), INDEX IDX_710F98E9E302836F (terpene_id), PRIMARY KEY (strain_id, terpene_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strain_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_AF85CB95E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE terpene (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, aroma VARCHAR(160) DEFAULT NULL, colour VARCHAR(7) DEFAULT \'#10b981\' NOT NULL, UNIQUE INDEX UNIQ_BDE57CA95E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE strain ADD CONSTRAINT FK_A630CD7244F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addSql('ALTER TABLE strain ADD CONSTRAINT FK_A630CD72C54C8C93 FOREIGN KEY (type_id) REFERENCES strain_type (id)');
        $this->addSql('ALTER TABLE strain ADD CONSTRAINT FK_A630CD725EF9DEB9 FOREIGN KEY (a_rating_id) REFERENCES rating (id)');
        $this->addSql('ALTER TABLE strain ADD CONSTRAINT FK_A630CD7254E34C7E FOREIGN KEY (m_rating_id) REFERENCES rating (id)');
        $this->addSql('ALTER TABLE strain_terpene ADD CONSTRAINT FK_710F98E969B9E007 FOREIGN KEY (strain_id) REFERENCES strain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE strain_terpene ADD CONSTRAINT FK_710F98E9E302836F FOREIGN KEY (terpene_id) REFERENCES terpene (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO rating (label, score, colour) VALUES
            ('Fantastic', 4, '#10b981'),
            ('Nice', 3, '#0ea5e9'),
            ('Mids', 2, '#f59e0b'),
            ('Terrible', 1, '#ef4444')");

        $this->addSql("INSERT INTO strain_type (name, position) VALUES
            ('Indica', 10),
            ('Indica Dominant', 20),
            ('Indica Hybrid', 30),
            ('Hybrid', 40),
            ('Balanced Hybrid', 50),
            ('Sativa Hybrid', 60),
            ('Sativa Dominant', 70),
            ('Sativa', 80)");

        $this->addSql("INSERT INTO terpene (name, aroma, colour) VALUES
            ('Bergamotene', 'Woody, citrus, tea-like', '#ca8a04'),
            ('Bisabolol', 'Floral, sweet, chamomile', '#db2777'),
            ('Caryophyllene', 'Peppery, spicy, woody', '#c2410c'),
            ('Eucalyptol', 'Minty, cooling, eucalyptus', '#0891b2'),
            ('Farnesene', 'Green apple, fruity', '#65a30d'),
            ('Geraniol', 'Rose, floral', '#e11d48'),
            ('Humulene', 'Hoppy, earthy, woody', '#a16207'),
            ('Limonene', 'Citrus, lemon, orange', '#eab308'),
            ('Linalool', 'Floral, lavender', '#9333ea'),
            ('Myrcene', 'Earthy, musky, herbal', '#16a34a'),
            ('Nerolidol', 'Woody, floral, citrus peel', '#7c3aed'),
            ('Ocimene', 'Sweet, herbaceous, tropical', '#059669'),
            ('Pinene', 'Pine, fresh, sharp', '#15803d'),
            ('Selinadiene', 'Woody, herbal, celery', '#4d7c0f'),
            ('Terpinolene', 'Fresh, floral, piney', '#0d9488'),
            ('Valencene', 'Sweet orange, citrus', '#ea580c')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE strain DROP FOREIGN KEY FK_A630CD7244F5D008');
        $this->addSql('ALTER TABLE strain DROP FOREIGN KEY FK_A630CD72C54C8C93');
        $this->addSql('ALTER TABLE strain DROP FOREIGN KEY FK_A630CD725EF9DEB9');
        $this->addSql('ALTER TABLE strain DROP FOREIGN KEY FK_A630CD7254E34C7E');
        $this->addSql('ALTER TABLE strain_terpene DROP FOREIGN KEY FK_710F98E969B9E007');
        $this->addSql('ALTER TABLE strain_terpene DROP FOREIGN KEY FK_710F98E9E302836F');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE rating');
        $this->addSql('DROP TABLE strain');
        $this->addSql('DROP TABLE strain_terpene');
        $this->addSql('DROP TABLE strain_type');
        $this->addSql('DROP TABLE terpene');
    }
}
