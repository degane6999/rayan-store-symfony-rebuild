<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    use AuthenticatedUserTrait;

    #[Route('/mon-compte', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('account/index.html.twig', ['user' => $this->getAppUser()]);
    }

    /**
     * RGPD: data portability - export everything we hold about the user as JSON.
     */
    #[Route('/mon-compte/export', name: 'app_account_export', methods: ['GET'])]
    public function export(\App\Repository\OrderRepository $orders): JsonResponse
    {
        $user = $this->getAppUser();
        $data = [
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'orders' => array_map(static function ($order) {
                return [
                    'orderNumber' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'total' => $order->getTotalAmount(),
                    'createdAt' => $order->getCreatedAt()->format(DATE_ATOM),
                ];
            }, $orders->findByUser($user)),
        ];

        return new JsonResponse($data);
    }

    /**
     * RGPD: right to erasure. We anonymize rather than hard-delete so that
     * Order rows (needed for accounting-law retention) survive with PII scrubbed.
     */
    #[Route('/mon-compte/supprimer', name: 'app_account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        \Symfony\Bundle\SecurityBundle\Security $security,
    ): Response {
        if (!$this->isCsrfTokenValid('account-delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->getAppUser();
        $user->anonymize();
        $em->flush();

        $security->logout(false);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_home');
    }
}
