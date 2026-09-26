<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Each strain keeps a list of the batches bought: date and batch number.
 */
final class Version20260926074634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add batches (date + batch number) to strains';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE batch (id INT AUTO_INCREMENT NOT NULL, batch_number VARCHAR(60) NOT NULL, date DATE NOT NULL, strain_id INT NOT NULL, UNIQUE INDEX uniq_batch_strain_number (strain_id, batch_number), INDEX IDX_F80B52D469B9E007 (strain_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE batch ADD CONSTRAINT FK_F80B52D469B9E007 FOREIGN KEY (strain_id) REFERENCES strain (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE batch DROP FOREIGN KEY FK_F80B52D469B9E007');
        $this->addSql('DROP TABLE batch');
    }
}
