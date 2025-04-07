<?php

namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;
use Eccube\Annotation\EntityExtension;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;

/**
 * @EntityExtension("Eccube\Entity\ProductClass")
 */
trait ProductClassTrait
{
    /**
     * 支払周期を保存するカラム
     *
     * @var \Plugin\UnivaPay\Entity\SubscriptionPeriod
     *
     * @ORM\ManyToOne(targetEntity="Plugin\UnivaPay\Entity\SubscriptionPeriod")
     * @ORM\JoinColumns({
     *  @ORM\JoinColumn(name="subscription_period_id", referencedColumnName="id")
     * })
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
     * @param SubscriptionPeriod|null $subscriptionPeriod
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
     * @return \Plugin\UnivaPay\Entity\SubscriptionPeriod|null
     */
    public function getSubscriptionPeriod()
    {
        return $this->SubscriptionPeriod;
    }
}
