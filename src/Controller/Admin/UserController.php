<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    // the "Exit impersonation" button posts here, for the firewall to switch back to the admin
    #[Route('', name: 'admin_users', methods: ['GET', 'POST'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', ['users' => $users->findBy([], ['email' => 'ASC'])]);
    }

    #[Route('/{id}/toggle', name: 'admin_user_toggle', methods: ['POST'], requirements: ['id' => Requirement::UUID_V7])]
    #[IsCsrfTokenValid('user-toggle')]
    public function toggle(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->enabled = !$user->enabled;
        $entityManager->flush();

        $this->addFlash('success', \sprintf('%s is now %s.', $user->name, $user->enabled ? 'enabled' : 'disabled'));

        return $this->redirectToRoute('admin_users');
    }
}
