<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay\Models\Builders;

use Core\Utils\CoreHelper;
use UnivaPay\Models\TransactionTokenCreateRequestMetadata;

/**
 * Builder for model TransactionTokenCreateRequestMetadata
 *
 * @see TransactionTokenCreateRequestMetadata
 */
class TransactionTokenCreateRequestMetadataBuilder
{
    /**
     * @var TransactionTokenCreateRequestMetadata
     */
    private $instance;

    private function __construct(TransactionTokenCreateRequestMetadata $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new Transaction Token Create Request Metadata Builder object.
     */
    public static function init(): self
    {
        return new self(new TransactionTokenCreateRequestMetadata());
    }

    /**
     * Sets univapay-reference-id field.
     *
     * @param string|null $value
     */
    public function univapayReferenceId(?string $value): self
    {
        $this->instance->setUnivapayReferenceId($value);
        return $this;
    }

    /**
     * Sets univapay-customer-id field.
     *
     * @param string|null $value
     */
    public function univapayCustomerId(?string $value): self
    {
        $this->instance->setUnivapayCustomerId($value);
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
     * Initializes a new Transaction Token Create Request Metadata object.
     */
    public function build(): TransactionTokenCreateRequestMetadata
    {
        return CoreHelper::clone($this->instance);
    }
}
