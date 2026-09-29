<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Paiement simulé, conformément au cahier des charges (fonctionnalité 4 :
 * « simulation ou intégration d'un service de paiement »).
 *
 * Aucune donnée bancaire n'est saisie, transmise ni stockée : l'application
 * reste hors du périmètre PCI-DSS. Une intégration réelle (Stripe) remplacerait
 * uniquement la génération de la référence par l'appel au prestataire.
 */
class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws \DomainException si la commande n'est pas payable (déjà payée, annulée…)
     */
    public function paySimulated(Order $order): string
    {
        $reference = 'SIM-'.strtoupper(bin2hex(random_bytes(6)));
        $order->markAsPaid($reference);
        $this->em->flush();

        return $reference;
    }
}
