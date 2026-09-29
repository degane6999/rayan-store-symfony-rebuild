<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogue : marque du produit et prix de référence (prix barré).
 */
final class Version20260929090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute brand et compare_at_price à la table product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD brand VARCHAR(80) DEFAULT '' NOT NULL, ADD compare_at_price NUMERIC(10, 2) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP brand, DROP compare_at_price');
    }
}
