<?php

namespace Plugin\UnivaPay\Util;

use Eccube\Entity\Order;

class OrderCopier
{
    /**
     * Copies an order to build a subscription parent or child order. Discount and point items are not copied.
     * The caller sets order number, charge id, status and payment date.
     */
    public static function copy(Order $source): Order
    {
        // Built from scratch because cloning an order keeps its id.
        $order = new Order();
        $order->setUnivapayParentSubscriptionId($source->getUnivapayParentSubscriptionId());
        $order->setMessage($source->getMessage());
        $order->setName01($source->getName01());
        $order->setName02($source->getName02());
        $order->setKana01($source->getKana01());
        $order->setKana02($source->getKana02());
        $order->setCompanyName($source->getCompanyName());
        $order->setEmail($source->getEmail());
        $order->setPhoneNumber($source->getPhoneNumber());
        $order->setPostalCode($source->getPostalCode());
        $order->setAddr01($source->getAddr01());
        $order->setAddr02($source->getAddr02());
        $order->setBirth($source->getBirth());
        $order->setSubtotal($source->getSubtotal());
        $order->setDiscount($source->getDiscount());
        $order->setDeliveryFeeTotal($source->getDeliveryFeeTotal());
        $order->setCharge($source->getCharge());
        $order->setTax($source->getTax());
        $order->setTotal($source->getTotal());
        $order->setPaymentTotal($source->getPaymentTotal());
        $order->setPaymentMethod($source->getPaymentMethod());
        $order->setNote($source->getNote());
        $order->setCurrencyCode($source->getCurrencyCode());
        $order->setCompleteMessage($source->getCompleteMessage());
        $order->setCompleteMailMessage($source->getCompleteMailMessage());
        $order->setCustomer($source->getCustomer());
        $order->setCountry($source->getCountry());
        $order->setPref($source->getPref());
        $order->setSex($source->getSex());
        $order->setJob($source->getJob());
        $order->setPayment($source->getPayment());
        $order->setDeviceType($source->getDeviceType());
        $order->setCustomerOrderStatus($source->getCustomerOrderStatus());
        $order->setOrderStatusColor($source->getOrderStatusColor());

        foreach ($source->getOrderItems() as $value) {
            $newOrderItem = clone $value;
            // Discounts are not carried over.
            if ($newOrderItem->isDiscount() || $newOrderItem->isPoint()) {
                continue;
            }
            $newOrderItem->setOrder($order);
            $order->addOrderItem($newOrderItem);
        }
        foreach ($source->getShippings() as $value) {
            $newShipping = clone $value;
            $newShipping->setShippingDeliveryDate(null);
            $newShipping->setShippingDeliveryTime(null);
            $newShipping->setTimeId(null);
            $newShipping->setShippingDate(null);
            $newShipping->setTrackingNumber(null);
            $newShipping->setOrder($order);
            // Shipping and OrderItem reference each other, so relink them manually.
            foreach ($newShipping->getOrderItems() as $v) {
                $newShipping->removeOrderItem($v);
            }
            foreach ($order->getOrderItems() as $v) {
                if ($v->getShipping() && $v->getShipping()->getId() == $value->getId()) {
                    $v->setShipping($newShipping);
                    $newShipping->addOrderItem($v);
                }
            }
            $order->addShipping($newShipping);
        }

        return $order;
    }
}
