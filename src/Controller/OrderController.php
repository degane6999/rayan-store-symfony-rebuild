<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Security\Voter\OrderVoter;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    use AuthenticatedUserTrait;

    #[Route('/mes-commandes', name: 'app_orders', methods: ['GET'])]
    public function index(OrderRepository $orders): Response
    {
        return $this->render('order/index.html.twig', [
            'orders' => $orders->findByUser($this->getAppUser()),
        ]);
    }

    #[Route('/commande/{orderNumber}', name: 'app_order_show', methods: ['GET'])]
    public function show(string $orderNumber, OrderRepository $orders): Response
    {
        $order = $orders->findOneByOrderNumber($orderNumber);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(OrderVoter::VIEW, $order);

        return $this->render('order/show.html.twig', ['order' => $order]);
    }

    #[Route('/commande/{orderNumber}/annuler', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(string $orderNumber, Request $request, OrderRepository $orders, OrderService $orderService): Response
    {
        $order = $orders->findOneByOrderNumber($orderNumber);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(OrderVoter::CANCEL, $order);

        if (!$this->isCsrfTokenValid('order-cancel-'.$order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $orderService->cancel($order);
        $this->addFlash('success', 'Commande annulée.');

        return $this->redirectToRoute('app_orders');
    }
}
