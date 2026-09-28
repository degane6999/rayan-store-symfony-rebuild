<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Review;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProductController extends AbstractController
{
    use AuthenticatedUserTrait;

    #[Route('/catalogue', name: 'app_catalog', methods: ['GET'])]
    public function catalog(Request $request, ProductRepository $products, CategoryRepository $categories): Response
    {
        $q = $request->query->get('q', '');
        $categorySlug = $request->query->get('category');

        if ('' !== $q) {
            $items = $products->search($q);
        } elseif ($categorySlug && $category = $categories->findOneBySlug($categorySlug)) {
            $items = $products->findByCategory($category);
        } else {
            $items = $products->findLatest(100);
        }

        return $this->render('product/catalog.html.twig', [
            'products' => $items,
            'categories' => $categories->findAll(),
            'q' => $q,
        ]);
    }

    #[Route('/produit/{slug}', name: 'app_product_show', methods: ['GET'])]
    public function show(string $slug, ProductRepository $products): Response
    {
        $product = $products->findOneBySlug($slug);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('product/show.html.twig', ['product' => $product]);
    }

    #[Route('/produit/{slug}/avis', name: 'app_product_review', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addReview(
        string $slug,
        Request $request,
        ProductRepository $products,
        OrderRepository $orders,
        EntityManagerInterface $em,
    ): Response {
        $product = $products->findOneBySlug($slug);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('review-'.$product->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->getAppUser();
        if (!$orders->hasPurchased($user, $product)) {
            $this->addFlash('error', 'Vous devez avoir acheté ce produit pour laisser un avis.');

            return $this->redirectToRoute('app_product_show', ['slug' => $slug]);
        }

        $rating = (int) $request->request->get('rating', 0);
        $comment = (string) $request->request->get('comment', '');

        $review = new Review($product, $user, $rating, $comment);
        $em->persist($review);
        $em->flush();

        $this->addFlash('success', 'Merci pour votre avis !');

        return $this->redirectToRoute('app_product_show', ['slug' => $slug]);
    }
}
