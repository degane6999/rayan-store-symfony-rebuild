<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Security\Voter\OrderVoter;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Étape de paiement (simulé) entre la validation du panier et la confirmation.
 */
#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    #[Route('/commande/{orderNumber}/paiement', name: 'app_order_payment', methods: ['GET'])]
    public function show(string $orderNumber, OrderRepository $orders): Response
    {
        $order = $orders->findOneByOrderNumber($orderNumber);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(OrderVoter::VIEW, $order);
        if (!$order->isPayable()) {
            // Déjà payée ou annulée : rien à payer.
            return $this->redirectToRoute('app_order_show', ['orderNumber' => $orderNumber]);
        }

        return $this->render('payment/index.html.twig', ['order' => $order]);
    }

    #[Route('/commande/{orderNumber}/paiement', name: 'app_order_pay', methods: ['POST'])]
    public function pay(string $orderNumber, Request $request, OrderRepository $orders, PaymentService $payments): Response
    {
        $order = $orders->findOneByOrderNumber($orderNumber);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(OrderVoter::PAY, $order);
        if (!$this->isCsrfTokenValid('order-pay-'.$order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $payments->paySimulated($order);
        $this->addFlash('success', 'Paiement accepté.');

        return $this->redirectToRoute('app_order_confirmation', ['orderNumber' => $orderNumber]);
    }
}
