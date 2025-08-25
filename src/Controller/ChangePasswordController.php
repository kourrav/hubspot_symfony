<?php
// src/Controller/ChangePasswordController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

#[Route('/change-password')]
class ChangePasswordController extends AbstractController
{
    #[Route('/', name:'change_password')]
    public function changePassword(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('currentPassword', PasswordType::class)
            ->add('newPassword', PasswordType::class)
            ->add('confirmPassword', PasswordType::class)
            ->add('save', SubmitType::class, ['label'=>'Change Password'])
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $data = $form->getData();

            if (!$passwordEncoder->isPasswordValid($user, $data['currentPassword'])) {
                $this->addFlash('danger','Current password incorrect!');
            } elseif($data['newPassword'] !== $data['confirmPassword']) {
                $this->addFlash('danger','New password does not match!');
            } else {
                $user->setPassword($passwordEncoder->hashPassword($user, $data['newPassword']));
                $this->getDoctrine()->getManager()->flush();
                $this->addFlash('success','Password changed successfully!');
                return $this->redirectToRoute('profile');
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
