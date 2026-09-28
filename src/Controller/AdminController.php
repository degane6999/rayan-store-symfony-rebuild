<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(OrderRepository $orders): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $orders->stats(),
        ]);
    }

    #[Route('/utilisateurs', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', ['users' => $users->findAll()]);
    }
}
