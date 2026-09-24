<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay\Models\Builders;

use Core\Utils\CoreHelper;
use UnivaPay\Models\GenericMetadata;

/**
 * Builder for model GenericMetadata
 *
 * @see GenericMetadata
 */
class GenericMetadataBuilder
{
    /**
     * @var GenericMetadata
     */
    private $instance;

    private function __construct(GenericMetadata $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new Generic Metadata Builder object.
     */
    public static function init(): self
    {
        return new self(new GenericMetadata());
    }

    /**
     * Sets order id field.
     *
     * @param string|null $value
     */
    public function orderId(?string $value): self
    {
        $this->instance->setOrderId($value);
        return $this;
    }

    /**
     * Sets univapay-name field.
     *
     * @param string|null $value
     */
    public function univapayName(?string $value): self
    {
        $this->instance->setUnivapayName($value);
        return $this;
    }

    /**
     * Sets univapay-phone-number field.
     *
     * @param string|null $value
     */
    public function univapayPhoneNumber(?string $value): self
    {
        $this->instance->setUnivapayPhoneNumber($value);
        return $this;
    }

    /**
     * Add an additional property to this model.
     *
     * @param string $name Name of property.
     * @param string|null|int|float|bool $value Value of property.
     */
    public function additionalProperty(string $name, $value): self
    {
        $this->instance->addAdditionalProperty($name, $value);
        return $this;
    }

    /**
     * Initializes a new Generic Metadata object.
     */
    public function build(): GenericMetadata
    {
        return CoreHelper::clone($this->instance);
    }
}
