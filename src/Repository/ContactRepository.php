<?php
// src/Repository/ContactRepository.php
namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    // Example custom method: recent contacts
    public function findRecentContacts(int $days = 7): array
    {
        $qb = $this->createQueryBuilder('c')
                   ->where('c.createdAt >= :date')
                   ->setParameter('date', new \DateTime("-$days days"))
                   ->orderBy('c.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
