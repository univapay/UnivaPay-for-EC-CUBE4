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
     * @ORM\Column(type="string", nullable=true)
     */
    private $univa_pay_charge_id;

    /**
     * 検索用に定期課金IDを保持するカラム
     *
     * @deprecated Holds 1.5.0 data only. New orders use univa_pay_parent_subscription_id. Remove in the next release.
     *
     * dtb_order.univa_pay_subscription_id
     *
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $univa_pay_subscription_id;

    /**
     * Subscription id, held by both the parent order (subscription status, no charge id) and its child orders (one per charge).
     *
     * dtb_order.univa_pay_parent_subscription_id
     *
     * @var string
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $univa_pay_parent_subscription_id;

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
     *
     * @deprecated Remove with univa_pay_subscription_id in the next release.
     */
    public function getUnivapaySubscriptionId()
    {
        return $this->univa_pay_subscription_id;
    }

    /**
     * @param string $univa_pay_subscription_id
     *
     * @return $this
     *
     * @deprecated Remove with univa_pay_subscription_id in the next release.
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
            && in_array($this->getOrderStatus()->getId(), [
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
            ], true);
    }
}
