<?php

namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * 決済IDを保持するカラム
     *
     * dtb_order.univa_pay_charge_id
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $univa_pay_charge_id;

    /**
     * Legacy subscription id. Holds 1.5.0 data only; new orders use univa_pay_parent_subscription_id. Keep it mapped: the 1.5.0 migration reads it,
     * and EC-CUBE's schema update would drop an unmapped column.
     *
     * dtb_order.univa_pay_subscription_id
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $univa_pay_subscription_id;

    /**
     * Subscription id, held by both the parent order (subscription status, no charge id) and its child orders (one per charge).
     *
     * dtb_order.univa_pay_parent_subscription_id
     *
     * @var string
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $univa_pay_parent_subscription_id;

    /**
     * Subscription period on the parent order (SubscriptionPeriod id)
     * Stored at checkout so later product edits do not change its cycle
     *
     * @ORM\Column(name="univa_pay_subscription_period", type="integer", nullable=true)
     */
    private $univa_pay_subscription_period;

    /**
     * Next scheduled payment date as last confirmed by UnivaPay
     *
     * @ORM\Column(name="univa_pay_next_charge_date", type="date", nullable=true)
     */
    private $univa_pay_next_charge_date;

    public function getUnivapaySubscriptionPeriod(): ?int
    {
        return $this->univa_pay_subscription_period;
    }

    public function setUnivapaySubscriptionPeriod(?int $period)
    {
        $this->univa_pay_subscription_period = $period;

        return $this;
    }

    public function getUnivapayNextChargeDate(): ?\DateTimeInterface
    {
        return $this->univa_pay_next_charge_date;
    }

    public function setUnivapayNextChargeDate(?\DateTimeInterface $date)
    {
        $this->univa_pay_next_charge_date = $date;

        return $this;
    }

    /**
     * @return string
     */
    public function getUnivapayChargeId()
    {
        return $this->univa_pay_charge_id;
    }

    /**
     * @param string $univa_pay_charge_id
     *
     * @return $this
     */
    public function setUnivapayChargeId($univa_pay_charge_id)
    {
        $this->univa_pay_charge_id = $univa_pay_charge_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getUnivapaySubscriptionId()
    {
        return $this->univa_pay_subscription_id;
    }

    /**
     * @param string $univa_pay_subscription_id
     *
     * @return $this
     */
    public function setUnivapaySubscriptionId($univa_pay_subscription_id)
    {
        $this->univa_pay_subscription_id = $univa_pay_subscription_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getUnivapayParentSubscriptionId()
    {
        return $this->univa_pay_parent_subscription_id;
    }

    /**
     * @param string $univa_pay_parent_subscription_id
     *
     * @return $this
     */
    public function setUnivapayParentSubscriptionId($univa_pay_parent_subscription_id)
    {
        $this->univa_pay_parent_subscription_id = $univa_pay_parent_subscription_id;

        return $this;
    }

    /**
     * Whether this is the subscription parent order. Checked by status because 1.5.0 first orders can lack a charge id.
     *
     * @return bool
     */
    public function isUnivapaySubscriptionParent()
    {
        return $this->univa_pay_parent_subscription_id !== null
            && $this->getOrderStatus() !== null
            && in_array($this->getOrderStatus()->getId(), UnivaPayOrderStatus::SUBSCRIPTION_STATUSES, true);
    }
}
