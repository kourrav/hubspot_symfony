<?php
// src/Controller/AuthController.php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        if ($request->isMethod('POST')) {
            $user = new User();

            // Split full name into firstName and lastName
            $fullName = $request->request->get('name');
            $names = explode(' ', $fullName, 2);
            $user->setFirstName($names[0] ?? '');
            $user->setLastName($names[1] ?? '');

            $user->setEmail($request->request->get('email'));
            $user->setPassword(
                $passwordEncoder->hashPassword($user, $request->request->get('password'))
            );

            // Assign default role
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('login');
        }

        return $this->render('auth/register.html.twig');
    }


    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('auth/login.html.twig', [
            'error' => $authUtils->getLastAuthenticationError(),
            'last_email' => $authUtils->getLastUsername()
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void {}

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            // Redirect admin to admin panel
            return $this->redirectToRoute('admin_dashboard');
        }
        return $this->render('dashboard.html.twig', [
            'user' => $this->getUser()
        ]);
    }
}
