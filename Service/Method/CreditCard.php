<?php
namespace Plugin\UnivaPay\Service\Method;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Symfony\Component\Form\FormInterface;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\SDK;
use Univapay\Enums\ChargeStatus;

/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class CreditCard implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /** @var ConfigRepository */
    protected $Config;

    /**
     * CreditCard constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        ConfigRepository $configRepository
    ) {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->Config = $configRepository;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        // 註文確定時に決済を行うのでここでは何もしない

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
     */
    public function apply()
    {
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());
    }

    /**
     * 注文時に呼び出される.
     *
     * クレジットカードの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        // One Time Purchase
        if ($this->Order->getUnivapayChargeId()) { 
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            $this->purchaseFlow->commit($this->Order, new PurchaseContext());

            $util = new SDK($this->Config->findOneById(1));
            $charge = $util->getCharge($this->Order->getUnivapayChargeId());

            if($charge->status === ChargeStatus::SUCCESSFUL()) {
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $this->Order->setOrderStatus($OrderStatus);
                $this->Order->setPaymentDate(new \DateTime());
            }

            $result = new PaymentResult();
            $result->setSuccess(true);
        }
        // Subscription Purchase
        else if($this->Order->getUnivapaySubscriptionId()) {
            $OrderStatus = $this->orderStatusRepository->find(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);
            $this->Order->setOrderStatus($OrderStatus);

            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(true);
        }
        else {
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([trans('univa_pay.shopping.checkout.error')]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }
}
