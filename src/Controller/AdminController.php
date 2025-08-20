<?php
namespace App\Controller;

use App\Repository\ContactRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(ContactRepository $contactRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $totalContacts = $contactRepository->count([]);

        return $this->render('admin/dashboard.html.twig', [
            'totalContacts' => $totalContacts,
        ]);
    }

    #[Route('/admin/contacts', name: 'admin_contacts')]
    public function contacts(Request $request, ContactRepository $contactRepository, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $qb = $contactRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($search = $request->query->get('search', '')) {
            $qb->where('c.firstname LIKE :search OR c.lastname LIKE :search OR c.email LIKE :search OR c.company LIKE :search')
            ->setParameter('search', "%$search%");
        }

        $pagination = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            10 // items per page
        );

        return $this->render('admin/contacts.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }
    // public function contacts(Request $request, ContactRepository $contactRepository): Response
    // {
    //     $this->denyAccessUnlessGranted('ROLE_ADMIN');

    //     $search = $request->query->get('search', '');

    //     if ($search) {
    //         $contacts = $contactRepository->createQueryBuilder('c')
    //             ->where('c.name LIKE :search OR c.email LIKE :search')
    //             ->setParameter('search', "%$search%")
    //             ->orderBy('c.createdAt', 'DESC')
    //             ->getQuery()
    //             ->getResult();
    //     } else {
    //         $contacts = $contactRepository->findBy([], ['createdAt' => 'DESC']);
    //     }

    //     return $this->render('admin/contacts.html.twig', [
    //         'contacts' => $contacts,
    //         'search' => $search,
    //     ]);
    // }
    

   
}
