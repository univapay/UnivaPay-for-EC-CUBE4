<?php

namespace Plugin\UnivaPay\Repository;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\AbstractRepository;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;

class SubscriptionOrderRepository extends AbstractRepository
{
    public const SORTS = [
        'next' => 'o.univa_pay_next_charge_date',
        'start' => 'o.order_date',
        'number' => 'o.order_no',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function getQueryBuilderBySearchData(array $searchData): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.univa_pay_parent_subscription_id IS NOT NULL')
            ->andWhere('o.univa_pay_charge_id IS NULL')
            ->andWhere('IDENTITY(o.OrderStatus) IN (:statuses)')
            ->setParameter('statuses', $searchData['status'] ?: UnivaPayOrderStatus::SUBSCRIPTION_STATUSES);

        if ($searchData['period'] >= 1 && $searchData['period'] <= 8) {
            $qb->andWhere('o.univa_pay_subscription_period = :period')
                ->setParameter('period', $searchData['period']);
        }

        $keyword = $searchData['keyword'];
        if ($keyword !== '') {
            $parts = [
                'o.order_no LIKE :keyword',
                'o.email LIKE :keyword',
                'o.univa_pay_parent_subscription_id LIKE :keyword',
            ];

            if (ctype_digit($keyword)) {
                $qb->leftJoin('o.Customer', 'c');
                $parts[] = 'c.id = :customerId';
                $qb->setParameter('customerId', (int) $keyword);
            }

            $qb->andWhere('('.implode(' OR ', $parts).')')
                ->setParameter('keyword', '%'.addcslashes($keyword, '%_').'%');
        }

        if ($searchData['from']) {
            $qb->andWhere('o.univa_pay_next_charge_date >= :from')
                ->setParameter('from', $searchData['from'], 'date');
        }

        if ($searchData['to']) {
            $qb->andWhere('o.univa_pay_next_charge_date < :until')
                ->setParameter('until', (clone $searchData['to'])->modify('+1 day'), 'date');
        }

        if ($searchData['sort'] === 'next') {
            // Orders without a next charge date go last regardless of direction.
            $qb->addSelect('CASE WHEN o.univa_pay_next_charge_date IS NULL THEN 1 ELSE 0 END AS HIDDEN missing_date')
                ->addOrderBy('missing_date', 'ASC');
        }

        return $qb
            ->addOrderBy(self::SORTS[$searchData['sort']], strtoupper($searchData['direction']))
            ->addOrderBy('o.id', 'DESC');
    }

    public function findLatestChildStatuses(array $parentSubscriptionIds): array
    {
        if (!$parentSubscriptionIds) {
            return [];
        }

        $children = $this->createQueryBuilder('child')
            ->where('child.univa_pay_parent_subscription_id IN (:ids)')
            ->andWhere('child.univa_pay_charge_id IS NOT NULL')
            ->andWhere(
                'child.id = (
                    SELECT MAX(other.id)
                    FROM Eccube\Entity\Order other
                    WHERE other.univa_pay_parent_subscription_id = child.univa_pay_parent_subscription_id
                    AND other.univa_pay_charge_id IS NOT NULL
                )'
            )
            ->setParameter('ids', array_unique($parentSubscriptionIds))
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($children as $child) {
            $latest[$child->getUnivapayParentSubscriptionId()] = $child->getOrderStatus();
        }

        return $latest;
    }
}
