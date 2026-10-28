<?php

namespace Plugin\UnivaPay\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Repository\Master\OrderStatusRepository;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Repository\SubscriptionOrderRepository;
use Plugin\UnivaPay\Repository\SubscriptionPeriodRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionListController extends AbstractController
{
    private $orders;
    private $periods;
    private $orderStatuses;

    public function __construct(
        SubscriptionOrderRepository $orders,
        SubscriptionPeriodRepository $periods,
        OrderStatusRepository $orderStatuses,
    ) {
        $this->orders = $orders;
        $this->periods = $periods;
        $this->orderStatuses = $orderStatuses;
    }

    /**
     * @Route("/%eccube_admin_route%/order/univapay/subscriptions", name="univa_pay_admin_subscriptions", methods={"GET"})
     *
     * @Template("@UnivaPay/admin/subscription_list.twig")
     */
    public function index(Request $request, PaginatorInterface $paginator)
    {
        $query = $request->query;

        // (array) keeps old single-value ?status=N links working.
        $status = array_intersect(
            UnivaPayOrderStatus::SUBSCRIPTION_STATUSES,
            (array) ($query->all()['status'] ?? [])
        );
        // Submitted form always sends keyword, so its absence means first visit, not "all unchecked".
        if (!$query->has('keyword') && !$query->has('status')) {
            $status = [UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE];
        }

        $sort = $query->getString('sort', 'next');
        if (!isset(SubscriptionOrderRepository::SORTS[$sort])) {
            $sort = 'next';
        }

        $direction = strtolower($query->getString('direction', 'asc'));
        if ($direction !== 'desc') {
            $direction = 'asc';
        }

        $date = $this->date($query->getString('date'));

        $searchData = [
            'status' => $status,
            'period' => $query->getInt('period'),
            'keyword' => trim($query->getString('keyword')),
            'from' => $date ?? $this->date($query->getString('from')),
            'to' => $date ?? $this->date($query->getString('to')),
            'sort' => $sort,
            'direction' => $direction,
        ];

        // Renamed so KnpPaginator does not consume this page's own sort parameter.
        $pagination = $paginator->paginate(
            $this->orders->getQueryBuilderBySearchData($searchData),
            max(1, $query->getInt('page_no', 1)),
            20,
            [
                'sortFieldParameterName' => '_univapay_knp_sort',
                'sortDirectionParameterName' => '_univapay_knp_direction',
            ]
        );

        $parentIds = [];
        foreach ($pagination as $parent) {
            $parentIds[] = $parent->getUnivapayParentSubscriptionId();
        }

        return [
            'pagination' => $pagination,
            'latest' => $this->orders->findLatestChildStatuses($parentIds),
            'statuses' => $this->orderStatuses->findBy(['id' => UnivaPayOrderStatus::SUBSCRIPTION_STATUSES]),
            'periods' => $this->periods->findBy([], ['id' => 'ASC']),
            'filters' => [
                'status' => $status,
                'period' => $searchData['period'],
                'sort' => $sort,
                'direction' => $direction,
                'keyword' => $searchData['keyword'],
                'date' => $query->getString('date'),
                'from' => $query->getString('from'),
                'to' => $query->getString('to'),
            ],
        ];
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
