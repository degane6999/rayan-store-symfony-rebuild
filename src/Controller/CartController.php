<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    use AuthenticatedUserTrait;

    #[Route('/panier', name: 'app_cart', methods: ['GET'])]
    public function index(CartService $cartService): Response
    {
        $cart = $cartService->getOrCreateCartForUser($this->getAppUser());

        return $this->render('cart/index.html.twig', ['cart' => $cart]);
    }

    #[Route('/panier/ajouter/{slug}', name: 'app_cart_add', methods: ['POST'])]
    public function add(string $slug, Request $request, ProductRepository $products, CartService $cartService): Response
    {
        if (!$this->isCsrfTokenValid('cart-add', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $product = $products->findOneBySlug($slug);
        if (!$product) {
            throw $this->createNotFoundException();
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));

        try {
            $cartService->addToCart($this->getAppUser(), $product, $quantity);
            $this->addFlash('success', 'Produit ajouté au panier.');
        } catch (\App\Exception\OutOfStockException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/retirer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(int $id, Request $request, CartService $cartService, CartRepository $carts): Response
    {
        if (!$this->isCsrfTokenValid('cart-remove-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $cart = $carts->findOneByUser($this->getAppUser());
        if ($cart) {
            $cartService->removeFromCart($cart, $id);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/quantite/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $request, CartService $cartService, CartRepository $carts): Response
    {
        if (!$this->isCsrfTokenValid('cart-update-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $cart = $carts->findOneByUser($this->getAppUser());
        if ($cart) {
            $cartService->updateQuantity($cart, $id, (int) $request->request->get('quantity', 1));
        }

        return $this->redirectToRoute('app_cart');
    }
}
