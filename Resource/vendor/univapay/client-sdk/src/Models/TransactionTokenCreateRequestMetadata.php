<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay\Models;

use stdClass;
use UnivaPay\ApiHelper;

/**
 * A free-form dictionary for custom metadata.
 */
class TransactionTokenCreateRequestMetadata implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $univapayReferenceId;

    /**
     * @var string|null
     */
    private $univapayCustomerId;

    /**
     * @var string|null
     */
    private $univapayName;

    /**
     * @var string|null
     */
    private $univapayPhoneNumber;

    /**
     * Returns Univapay Reference Id.
     * Any arbitrary value (Free format).
     */
    public function getUnivapayReferenceId(): ?string
    {
        return $this->univapayReferenceId;
    }

    /**
     * Sets Univapay Reference Id.
     * Any arbitrary value (Free format).
     *
     * @maps univapay-reference-id
     */
    public function setUnivapayReferenceId(?string $univapayReferenceId): void
    {
        $this->univapayReferenceId = $univapayReferenceId;
    }

    /**
     * Returns Univapay Customer Id.
     * Customer ID.
     */
    public function getUnivapayCustomerId(): ?string
    {
        return $this->univapayCustomerId;
    }

    /**
     * Sets Univapay Customer Id.
     * Customer ID.
     *
     * @maps univapay-customer-id
     */
    public function setUnivapayCustomerId(?string $univapayCustomerId): void
    {
        $this->univapayCustomerId = $univapayCustomerId;
    }

    /**
     * Returns Univapay Name.
     * Consumer name passed to payment processors that require it (e.g., konbini, bank transfer).
     */
    public function getUnivapayName(): ?string
    {
        return $this->univapayName;
    }

    /**
     * Sets Univapay Name.
     * Consumer name passed to payment processors that require it (e.g., konbini, bank transfer).
     *
     * @maps univapay-name
     */
    public function setUnivapayName(?string $univapayName): void
    {
        $this->univapayName = $univapayName;
    }

    /**
     * Returns Univapay Phone Number.
     * Consumer phone number passed to payment processors that require it.
     */
    public function getUnivapayPhoneNumber(): ?string
    {
        return $this->univapayPhoneNumber;
    }

    /**
     * Sets Univapay Phone Number.
     * Consumer phone number passed to payment processors that require it.
     *
     * @maps univapay-phone-number
     */
    public function setUnivapayPhoneNumber(?string $univapayPhoneNumber): void
    {
        $this->univapayPhoneNumber = $univapayPhoneNumber;
    }

    /**
     * Converts the TransactionTokenCreateRequestMetadata object to a human-readable string representation.
     *
     * @return string The string representation of the TransactionTokenCreateRequestMetadata object.
     */
    public function __toString(): string
    {
        return ApiHelper::stringify(
            'TransactionTokenCreateRequestMetadata',
            [
                'univapayReferenceId' => $this->univapayReferenceId,
                'univapayCustomerId' => $this->univapayCustomerId,
                'univapayName' => $this->univapayName,
                'univapayPhoneNumber' => $this->univapayPhoneNumber,
                'additionalProperties' => $this->additionalProperties
            ]
        );
    }

    protected $propertyNames = [
        'univapay-reference-id',
        'univapay-customer-id',
        'univapay-name',
        'univapay-phone-number'
    ];

    private $additionalProperties = [];

    /**
     * Add an additional property to this model.
     *
     * @mapsBy anyOf(anyOf(string,null),int,float,bool,anyOf(anyOf(string,null),int,float,bool)[])
     *
     * @param string $name Name of property.
     * @param string|null|int|float|bool $value Value of property.
     */
    public function addAdditionalProperty(string $name, $value)
    {
        if (in_array($name, $this->propertyNames, true)) {
            throw new \InvalidArgumentException(
                "The additional property key, '$name' conflicts with one of the model's properties"
            );
        }

        $this->additionalProperties[$name] = $value;
    }

    /**
     * Find an additional property by name in this model or false if property does not exist.
     *
     * @param string $name Name of property.
     *
     * @return string|null|int|float|bool|false Value of the property.
     */
    public function findAdditionalProperty(string $name)
    {
        if (isset($this->additionalProperties[$name])) {
            return $this->additionalProperties[$name];
        }
        return false;
    }

    /**
     * Extract all additional properties.
     */
    private function extractAdditionalProperties(): array
    {
        return array_map(
            function ($value) {
                return ApiHelper::getJsonHelper()
                    ->verifyTypes(
                        $value,
                        'anyOf(anyOf(string,null),int,float,bool,anyOf(anyOf(string,null),int,float,bool)[])'
                    );
            },
            $this->additionalProperties
        );
    }

    /**
     * Encode this object to JSON
     *
     * @param bool $asArrayWhenEmpty Whether to serialize this model as an array whenever no fields
     *        are set. (default: false)
     *
     * @return array|stdClass
     */
    #[\ReturnTypeWillChange] // @phan-suppress-current-line PhanUndeclaredClassAttribute for (php < 8.1)
    public function jsonSerialize(bool $asArrayWhenEmpty = false)
    {
        $json = [];
        if (isset($this->univapayReferenceId)) {
            $json['univapay-reference-id'] = $this->univapayReferenceId;
        }
        if (isset($this->univapayCustomerId)) {
            $json['univapay-customer-id']  = $this->univapayCustomerId;
        }
        if (isset($this->univapayName)) {
            $json['univapay-name']         = $this->univapayName;
        }
        if (isset($this->univapayPhoneNumber)) {
            $json['univapay-phone-number'] = $this->univapayPhoneNumber;
        }
        $json = array_merge($json, $this->extractAdditionalProperties());

        return (!$asArrayWhenEmpty && empty($json)) ? new stdClass() : $json;
    }
}
