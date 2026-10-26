<?php



namespace Plugin\UnivaPay\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Eccube\Repository\AbstractRepository;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;

class SubscriptionPeriodRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPeriod::class);
    }
}
