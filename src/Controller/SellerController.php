<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/seller')]
#[IsGranted('ROLE_SELLER')]
class SellerController extends AbstractController
{
    #[Route('', name: 'app_seller_dashboard', methods: ['GET'])]
    public function dashboard(ProductRepository $products): Response
    {
        return $this->render('seller/dashboard.html.twig', [
            'products' => $products->findLatest(50),
        ]);
    }

    #[Route('/produits/nouveau', name: 'app_seller_product_new', methods: ['GET', 'POST'])]
    public function newProduct(
        Request $request,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $em,
        SluggerService $slugger,
    ): Response {
        $firstCategory = $em->getRepository(Category::class)->findOneBy([]);
        if (null === $firstCategory) {
            throw new \LogicException('Cannot create a product: no category exists yet. Seed at least one category first.');
        }
        $product = new Product('', 'temp-'.bin2hex(random_bytes(4)), '', '0.00', 0, $firstCategory);

        $form = $formFactory->create(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slugify($product->getName()).'-'.substr(bin2hex(random_bytes(3)), 0, 6);
            $product->setSlug($slug);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Produit créé.');

            return $this->redirectToRoute('app_seller_dashboard');
        }

        return $this->render('seller/product_form.html.twig', ['form' => $form->createView()]);
    }
}
