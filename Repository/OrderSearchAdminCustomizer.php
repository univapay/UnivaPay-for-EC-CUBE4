<?php

namespace Plugin\UnivaPay\Repository;

    use Doctrine\ORM\QueryBuilder;
    use Eccube\Doctrine\Query\QueryCustomizer;
    use Eccube\Repository\QueryKey;
    use Eccube\Util\StringUtil;

    class OrderSearchAdminCustomizer implements QueryCustomizer
    {
        /**
         *  課金IDと定期課金IDが検索されるようにクエリを追加
         */
        public function customize(QueryBuilder $qb, $searchData, $queryKey)
        {
            if (isset($searchData['multi']) && StringUtil::isNotBlank($searchData['multi'])) {
                // 注文番号検索の場合を除外
                if (!preg_match('/^\d{0,10}$/', $searchData['multi'])) {
                    $qb->orWhere('o.univa_pay_charge_id like :likemulti OR o.univa_pay_parent_subscription_id like :likemulti');
                }
            }
        }

        public function getQueryKey(): string
        {
            return QueryKey::ORDER_SEARCH_ADMIN;
        }
    }
