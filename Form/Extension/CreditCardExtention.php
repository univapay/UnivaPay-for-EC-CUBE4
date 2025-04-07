<?php
namespace Plugin\UnivaPay\Form\Extension;

use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * サーバに決済IDを保存する
 */
class CreditCardExtention extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var Order $data */
            $form = $event->getForm();

            $form->add('univa_pay_charge_id', HiddenType::class, [
                'required' => false,
                'mapped' => true, // Orderエンティティに追加したカラムなので、mappedはtrue
            ]);

            $form->add('univa_pay_subscription_id', HiddenType::class, [
                'required' => false,
                'mapped' => true,
            ]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }

    /**
     * @return iterable
     */
    public static function getExtendedTypes(): iterable
    {
        return [OrderType::class];
    }
}
