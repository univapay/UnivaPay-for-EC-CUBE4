<?php

namespace Plugin\UnivaPay\Service\PurchaseFlow\Processor;

use Doctrine\DBAL\LockMode;
use Eccube\Entity\ItemHolderInterface;
use Eccube\Entity\Order;
use Eccube\Service\PurchaseFlow\Processor\StockReduceProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\UnivaPay\Util\UnivaPayLogger;

/**
 * Core StockReduceProcessor, except stock may go negative: a subscription order is already charged by UnivaPay,
 * so the order must be recorded and the admin decides whether to ship or cancel. Cancel restores stock via core rollback.
 */
class SubscriptionOrderStockReduceProcessor extends StockReduceProcessor
{
    public function prepare(ItemHolderInterface $itemHolder, PurchaseContext $context)
    {
        if (!$itemHolder instanceof Order) {
            return;
        }

        foreach ($itemHolder->getProductOrderItems() as $item) {
            $ProductClass = $item->getProductClass();
            if ($ProductClass->isStockUnlimited()) {
                continue;
            }

            $ProductStock = $ProductClass->getProductStock();
            $this->entityManager->lock($ProductStock, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($ProductStock);

            $stock = $ProductStock->getStock() - $item->getQuantity();
            $ProductStock->setStock($stock);
            $ProductClass->setStock($stock);

            if ($stock < 0) {
                UnivaPayLogger::get()->warning('Subscription renewal reduced stock below zero', [
                    'parent_subscription_id' => $itemHolder->getUnivapayParentSubscriptionId(),
                    'product_class_id' => $ProductClass->getId(),
                    'stock' => $stock,
                ]);
            }
        }
    }
}
