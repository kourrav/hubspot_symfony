<?php
// src/Controller/Admin/UserController.php
namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    #[Route('/', name:'admin_users')]
    public function list(Request $request, PaginatorInterface $paginator, EntityManagerInterface $em)
    {
        $repo = $em->getRepository(User::class);
        $query = $repo->createQueryBuilder('u')
            // Filter users who do NOT have ROLE_ADMIN
            ->where('u.roles NOT LIKE :roleAdmin')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->orderBy('u.id','DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page',1),
            10
        );

        return $this->render('admin/users/list.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/add', name:'admin_user_add')]
    #[Route('/edit/{id}', name:'admin_user_edit')]

    public function addEdit(User $user = null, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $isEdit = $user !== null;
        if (!$isEdit) {
            $user = new User();
        }

        $form = $this->createFormBuilder($user)
            ->add('firstName')
            ->add('lastName')
            ->add('email', EmailType::class)
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'User' => 'ROLE_USER'
                ],
                'expanded' => true,
                'multiple' => true
            ])
            ->add('password', PasswordType::class, [
                'required' => !$isEdit,
                'mapped' => false
            ])
            //->add('save', SubmitType::class, ['label' => $isEdit ? 'Update User' : 'Add User'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password only if new or changed
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $isEdit ? 'User updated!' : 'User added!');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => $isEdit
        ]);
    }

    #[Route('/delete/{id}', name:'admin_user_delete')]
    public function delete(User $user, EntityManagerInterface $em)
    {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success','User deleted!');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/change-password/{id}', name:'admin_user_change_password')]
    public function changePassword(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, ['mapped' => false])
            //->add('save', SubmitType::class, ['label' => 'Update Password'])
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $em->persist($user);
                $em->flush();
                $this->addFlash('success','Password updated!');
            }
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/change_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }
}
