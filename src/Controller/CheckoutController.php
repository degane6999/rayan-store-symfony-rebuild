<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\EmptyCartException;
use App\Exception\OutOfStockException;
use App\Form\ShippingType;
use App\Repository\CartRepository;
use App\Service\OrderService;
use Doctrine\DBAL\Exception\DeadlockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    use AuthenticatedUserTrait;

    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Request $request,
        FormFactoryInterface $formFactory,
        CartRepository $carts,
        OrderService $orderService,
    ): Response {
        $cart = $carts->findOneByUser($this->getAppUser());
        if (!$cart || $cart->getItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('app_cart');
        }

        $form = $formFactory->create(ShippingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $order = $orderService->placeOrder($this->getAppUser(), $form->getData());

                // Étape suivante : le paiement (simulé), avant la confirmation.
                return $this->redirectToRoute('app_order_payment', ['orderNumber' => $order->getOrderNumber()]);
            } catch (OutOfStockException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (EmptyCartException $e) {
                $this->addFlash('error', 'Votre panier est vide.');

                return $this->redirectToRoute('app_cart');
            } catch (DeadlockException $e) {
                // Under very high concurrent load on the same product rows, the
                // database can detect and abort a deadlocked transaction. This is
                // a legitimate, expected outcome under contention, not a bug: the
                // customer is asked to simply retry, and no stock was corrupted
                // because the whole transaction was rolled back atomically.
                $this->addFlash('error', 'Le serveur est très sollicité, merci de réessayer votre commande.');
            }
        }

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/commande/confirmation/{orderNumber}', name: 'app_order_confirmation', methods: ['GET'])]
    public function confirmation(string $orderNumber, \App\Repository\OrderRepository $orders): Response
    {
        $order = $orders->findOneByOrderNumber($orderNumber);
        if (!$order || $order->getUser()->getId() !== $this->getAppUser()->getId()) {
            throw $this->createNotFoundException();
        }
        if ($order->isPayable()) {
            // Pas de confirmation tant que la commande n'est pas payée.
            return $this->redirectToRoute('app_order_payment', ['orderNumber' => $orderNumber]);
        }

        return $this->render('checkout/confirmation.html.twig', ['order' => $order]);
    }
}
