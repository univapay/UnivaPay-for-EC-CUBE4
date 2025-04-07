<?php
    namespace Plugin\UnivaPay\Entity;

    use Doctrine\ORM\Mapping as ORM;
    use Eccube\Entity\Master\AbstractMasterEntity;

    /**
     * SubscriptionPeriod
     *
     * @ORM\Table(name="plg_univa_pay_subscription_period")
     * @ORM\Entity(repositoryClass="Plugin\UnivaPay\Repository\SubscriptionPeriodRepository")
     */
    class SubscriptionPeriod extends AbstractMasterEntity
    {
        const NON_SUBSCRIPTION = 0;
        const DAILY = 1;
        const WEEKLY = 2;
        const BIWEEKLY = 3;
        const MONTHLY = 4;
        const BIMONTHLY = 5;
        const QUARTERLY = 6;
        const SEMIANNUALLY = 7;
        const ANNUALLY = 8;
    }
