<?php

namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * カードの記憶用カラム.
     *
     * @var string
     *
     * @ORM\Column(type="smallint", nullable=true)
     */
    public $univa_pay_cards;
}
