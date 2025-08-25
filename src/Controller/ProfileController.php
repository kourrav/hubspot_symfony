<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ProfileType;
use App\Form\ChangePasswordType;
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'user_profile')]
    public function profile(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
    
        // Profile form
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);
    
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $imageFile = $profileForm->get('profilePicture')->getData();
            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('profiles_directory'), $newFilename);
                    $user->setProfilePicture($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Image upload failed!');
                }
            }
    
            $em->persist($user);
            $em->flush();
    
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('user_profile');
        }
    
        // Password form
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);
    
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $plainPassword = $passwordForm->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $em->flush();
            $this->addFlash('success', 'Password updated successfully!');
            return $this->redirectToRoute('user_profile');
        }
    
        return $this->render('profile/profile.html.twig', [
            'form' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
        ]);
    }

}
