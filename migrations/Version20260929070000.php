<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paiement simulé : date de paiement et référence de transaction sur la commande.
 */
final class Version20260929070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute paid_at et payment_reference à la table order (paiement simulé).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD payment_reference VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP paid_at, DROP payment_reference');
    }
}
