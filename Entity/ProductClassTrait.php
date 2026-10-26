<?php

namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\ProductClass")
 */
trait ProductClassTrait
{
    /**
     * 支払周期を保存するカラム
     *
     * @var SubscriptionPeriod
     *
     * @ORM\ManyToOne(targetEntity="Plugin\UnivaPay\Entity\SubscriptionPeriod")
     *
     * @ORM\JoinColumns({
     *
     *  @ORM\JoinColumn(name="subscription_period_id", referencedColumnName="id")
     * })
     *
     * @Eccube\FormAppend(
     *  auto_render=true,
     *  options={
     *      "required": true,
     *      "label": "univa_pay.subscription.period"
     *  })
     */
    private $SubscriptionPeriod;

    /**
     * Set subscriptionPeriod.
     *
     * @return ProductClass
     */
    public function setSubscriptionPeriod(?SubscriptionPeriod $subscriptionPeriod)
    {
        $this->SubscriptionPeriod = $subscriptionPeriod;

        return $this;
    }

    /**
     * Get subscriptionPeriod
     *
     * @return SubscriptionPeriod|null
     */
    public function getSubscriptionPeriod()
    {
        return $this->SubscriptionPeriod;
    }
}
