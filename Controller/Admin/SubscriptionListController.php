<?php



namespace Plugin\UnivaPay\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Repository\SubscriptionPeriodRepository;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\SubscriptionMetadata;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

// There would be an optional per-row refresh operation,
// with CSRF protection, to retrieve the latest subscription schedule from UnivaPay
class SubscriptionListController extends AbstractController
{
    private const PARENT_STATUSES = [
        UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
        UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
    ];

    private $orders;
    private $periods;
    private $configs;

    public function __construct(
        OrderRepository $orders,
        SubscriptionPeriodRepository $periods,
        ConfigRepository $configs,
    ) {
        $this->orders = $orders;
        $this->periods = $periods;
        $this->configs = $configs;
    }

    /** @Template("@UnivaPay/admin/subscription_list.twig") */
    #[Route(
        path: '/%eccube_admin_route%/order/univapay/subscriptions',
        name: 'univa_pay_admin_subscriptions',
        methods: ['GET']
    )]
    public function index(
        Request $request,
        PaginatorInterface $paginator,
    ) {
        $status = (int) $request->query->get('status', 0);
        $period = (int) $request->query->get('period', 0);

        $sort = $request->query->get('sort', 'next');
        $sort = is_string($sort) ? $sort : 'next';

        $direction = $request->query->get('direction', 'asc');
        $direction = is_string($direction)
            ? strtolower($direction)
            : 'asc';

        $keyword = $request->query->get('keyword', '');
        $keyword = is_string($keyword) ? trim($keyword) : '';

        $sorts = [
            'next' => 'o.univa_pay_next_charge_date',
            'start' => 'o.order_date',
            'number' => 'o.order_no',
        ];

        if (!isset($sorts[$sort])) {
            $sort = 'next';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $qb = $this->orders->createQueryBuilder('o')
            ->where('o.univa_pay_parent_subscription_id IS NOT NULL')
            ->andWhere('o.univa_pay_charge_id IS NULL')
            ->andWhere('IDENTITY(o.OrderStatus) IN (:statuses)')
            ->setParameter('statuses', self::PARENT_STATUSES);

        if (in_array($status, self::PARENT_STATUSES, true)) {
            $qb->andWhere('IDENTITY(o.OrderStatus) = :status')
                ->setParameter('status', $status);
        }

        if ($period >= 1 && $period <= 8) {
            $qb->andWhere('o.univa_pay_subscription_period = :period')
                ->setParameter('period', $period);
        }

        if ($keyword !== '') {
            $qb->leftJoin('o.Customer', 'c');

            $parts = [
                'o.order_no LIKE :keyword',
                'o.email LIKE :keyword',
                'o.univa_pay_parent_subscription_id LIKE :keyword',
            ];

            if (ctype_digit($keyword)) {
                $parts[] = 'c.id = :customerId';
                $qb->setParameter('customerId', (int) $keyword);
            }

            $qb->andWhere('('.implode(' OR ', $parts).')')
                ->setParameter(
                    'keyword',
                    '%'.str_replace(
                        ['%', '_'],
                        ['\\%', '\\_'],
                        $keyword
                    ).'%'
                );
        }

        $date = $this->date(
            (string) $request->query->get('date', '')
        );

        $from = $this->date(
            (string) $request->query->get('from', '')
        );

        $to = $this->date(
            (string) $request->query->get('to', '')
        );

        if ($date) {
            $from = $date;
            $to = $date;
        }

        if ($from) {
            $qb->andWhere('o.univa_pay_next_charge_date >= :from')
                ->setParameter(
                    'from',
                    new \DateTime($from->format('Y-m-d')),
                    'date'
                );
        }

        if ($to) {
            $qb->andWhere('o.univa_pay_next_charge_date < :until')
                ->setParameter(
                    'until',
                    new \DateTime(
                        $to->modify('+1 day')->format('Y-m-d')
                    ),
                    'date'
                );
        }

        if ($sort === 'next') {
            $qb->addSelect(
                'CASE WHEN o.univa_pay_next_charge_date IS NULL
                 THEN 1 ELSE 0 END AS HIDDEN missing_date'
            )->addOrderBy('missing_date', 'ASC');
        }

        $qb->addOrderBy(
            $sorts[$sort],
            strtoupper($direction)
        )->addOrderBy('o.id', 'DESC');

        $page = max(
            1,
            (int) $request->query->get('page_no', 1)
        );

        // KnpPaginator intercepted the custom sort parameter
        // so had to configure KnpPaginator's sorting parameter names for this page only
        $pagination = $paginator->paginate(
            $qb,
            $page,
            20,
            [
                'sortFieldParameterName' => '_univapay_knp_sort',
                'sortDirectionParameterName' => '_univapay_knp_direction',
            ]
        );

        // Read the latest recorded child order for displayed parents.
        $ids = [];

        foreach ($pagination as $parent) {
            $ids[] = $parent->getUnivapayParentSubscriptionId();
        }

        $latest = [];

        if ($ids) {
            $children = $this->orders
                ->createQueryBuilder('child')
                ->where(
                    'child.univa_pay_parent_subscription_id IN (:ids)'
                )
                ->andWhere('child.univa_pay_charge_id IS NOT NULL')
                ->andWhere(
                    'child.id = (
                        SELECT MAX(other.id)
                        FROM Eccube\Entity\Order other
                        WHERE other.univa_pay_parent_subscription_id =
                              child.univa_pay_parent_subscription_id
                        AND other.univa_pay_charge_id IS NOT NULL
                    )'
                )
                ->setParameter('ids', array_unique($ids))
                ->getQuery()
                ->getResult();

            foreach ($children as $child) {
                $latest[$child->getUnivapayParentSubscriptionId()] = $child->getOrderStatus();
            }
        }

        return [
            'pagination' => $pagination,
            'latest' => $latest,
            'statuses' => $this->entityManager
                ->getRepository(OrderStatus::class)
                ->findBy([
                    'id' => self::PARENT_STATUSES,
                ]),
            'periods' => $this->periods->findBy(
                [],
                ['id' => 'ASC']
            ),
            'filters' => [
                'status' => $status,
                'period' => $period,
                'sort' => $sort,
                'direction' => $direction,
                'keyword' => $keyword,
                'date' => $request->query->get('date', ''),
                'from' => $request->query->get('from', ''),
                'to' => $request->query->get('to', ''),
            ],
        ];
    }

    #[Route(
        path: '/%eccube_admin_route%/order/univapay/subscriptions/{id}/refresh',
        name: 'univa_pay_admin_subscription_refresh',
        requirements: ['id' => '\\d+'],
        methods: ['POST']
    )]
    public function refresh(Request $request, int $id)
    {
        $this->isTokenValid();

        $order = $this->orders->find($id);

        if (
            !$order
            || !$order->isUnivapaySubscriptionParent()
            || $order->getUnivapayChargeId()
        ) {
            throw new NotFoundHttpException();
        }

        try {
            $config = $this->configs->findOneById(1);

            if (!$config) {
                throw new \RuntimeException('UnivaPay configuration missing');
            }

            $sdk = new SDK($config);

            SubscriptionMetadata::update(
                $order,
                $sdk->getSubscription(
                    $order->getUnivapayParentSubscriptionId()
                )
            );

            $this->entityManager->flush();

            $this->addSuccess(
                'univa_pay.admin.subscriptions.refreshed',
                'admin'
            );
        } catch (\Throwable $e) {
            UnivaPayLogger::get()->warning(
                'Subscription list refresh failed',
                [
                    'order_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->addError(
                'univa_pay.admin.subscriptions.refresh_failed',
                'admin'
            );
        }

        return $this->redirectToRoute(
            'univa_pay_admin_subscriptions',
            $request->query->all()
        );
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date && $date->format('Y-m-d') === $value
            ? $date
            : null;
    }
}
