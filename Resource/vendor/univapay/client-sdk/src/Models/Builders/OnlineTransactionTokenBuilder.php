<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay\Models\Builders;

use Core\Utils\CoreHelper;
use UnivaPay\Models\OnlineTransactionToken;
use UnivaPay\Models\TokenResponseOnlineData;

/**
 * Builder for model OnlineTransactionToken
 *
 * @see OnlineTransactionToken
 */
class OnlineTransactionTokenBuilder
{
    /**
     * @var OnlineTransactionToken
     */
    private $instance;

    private function __construct(OnlineTransactionToken $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new Online Transaction Token Builder object.
     *
     * @param TokenResponseOnlineData $data
     */
    public static function init(TokenResponseOnlineData $data): self
    {
        return new self(new OnlineTransactionToken($data));
    }

    /**
     * Sets id field.
     *
     * @param string|null $value
     */
    public function id(?string $value): self
    {
        $this->instance->setId($value);
        return $this;
    }

    /**
     * Sets store id field.
     *
     * @param string|null $value
     */
    public function storeId(?string $value): self
    {
        $this->instance->setStoreId($value);
        return $this;
    }

    /**
     * Sets email field.
     *
     * @param string|null $value
     */
    public function email(?string $value): self
    {
        $this->instance->setEmail($value);
        return $this;
    }

    /**
     * Unsets email field.
     */
    public function unsetEmail(): self
    {
        $this->instance->unsetEmail();
        return $this;
    }

    /**
     * Sets active field.
     *
     * @param bool|null $value
     */
    public function active(?bool $value): self
    {
        $this->instance->setActive($value);
        return $this;
    }

    /**
     * Sets mode field.
     *
     * @param string|null $value
     */
    public function mode(?string $value): self
    {
        $this->instance->setMode($value);
        return $this;
    }

    /**
     * Sets type field.
     *
     * @param string|null $value
     */
    public function type(?string $value): self
    {
        $this->instance->setType($value);
        return $this;
    }

    /**
     * Sets usage limit field.
     *
     * @param string|null $value
     */
    public function usageLimit(?string $value): self
    {
        $this->instance->setUsageLimit($value);
        return $this;
    }

    /**
     * Unsets usage limit field.
     */
    public function unsetUsageLimit(): self
    {
        $this->instance->unsetUsageLimit();
        return $this;
    }

    /**
     * Sets confirmed field.
     *
     * @param bool|null $value
     */
    public function confirmed(?bool $value): self
    {
        $this->instance->setConfirmed($value);
        return $this;
    }

    /**
     * Unsets confirmed field.
     */
    public function unsetConfirmed(): self
    {
        $this->instance->unsetConfirmed();
        return $this;
    }

    /**
     * Sets metadata field.
     *
     * @param array<string,string|null|int|float|bool>|null $value
     */
    public function metadata(?array $value): self
    {
        $this->instance->setMetadata($value);
        return $this;
    }

    /**
     * Sets created on field.
     *
     * @param \DateTime|null $value
     */
    public function createdOn(?\DateTime $value): self
    {
        $this->instance->setCreatedOn($value);
        return $this;
    }

    /**
     * Sets updated on field.
     *
     * @param \DateTime|null $value
     */
    public function updatedOn(?\DateTime $value): self
    {
        $this->instance->setUpdatedOn($value);
        return $this;
    }

    /**
     * Sets last used on field.
     *
     * @param \DateTime|null $value
     */
    public function lastUsedOn(?\DateTime $value): self
    {
        $this->instance->setLastUsedOn($value);
        return $this;
    }

    /**
     * Unsets last used on field.
     */
    public function unsetLastUsedOn(): self
    {
        $this->instance->unsetLastUsedOn();
        return $this;
    }

    /**
     * Add an additional property to this model.
     *
     * @param string $name Name of property.
     * @param mixed $value Value of property.
     */
    public function additionalProperty(string $name, $value): self
    {
        $this->instance->addAdditionalProperty($name, $value);
        return $this;
    }

    /**
     * Initializes a new Online Transaction Token object.
     */
    public function build(): OnlineTransactionToken
    {
        return CoreHelper::clone($this->instance);
    }
}
