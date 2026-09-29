<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Images produits : chemin de l'illustration, relatif à public/images/.
 */
final class Version20260929080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute image_path à la table product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP image_path');
    }
}
