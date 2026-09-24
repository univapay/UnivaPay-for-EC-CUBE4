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
use UnivaPay\Utils\DateTimeHelper;

/**
 * Stored transaction token resource for a `qr_merchant` (QR Merchant-Presented Mode) payment type.
 */
class QrMerchantTransactionToken implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $id;

    /**
     * @var string|null
     */
    private $storeId;

    /**
     * @var array
     */
    private $email = [];

    /**
     * @var bool|null
     */
    private $active;

    /**
     * @var string|null
     */
    private $mode;

    /**
     * @var string|null
     */
    private $type;

    /**
     * @var array
     */
    private $usageLimit = [];

    /**
     * @var array
     */
    private $confirmed = [];

    /**
     * @var array<string,string|null|int|float|bool>|null
     */
    private $metadata;

    /**
     * @var \DateTime|null
     */
    private $createdOn;

    /**
     * @var \DateTime|null
     */
    private $updatedOn;

    /**
     * @var array
     */
    private $lastUsedOn = [];

    /**
     * @var string
     */
    private $paymentType;

    /**
     * @var TokenResponseQrMerchantData
     */
    private $data;

    /**
     * @param TokenResponseQrMerchantData $data
     */
    public function __construct(TokenResponseQrMerchantData $data)
    {
        $this->data = $data;
    }

    /**
     * Returns Id.
     * Unique identifier.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Sets Id.
     * Unique identifier.
     *
     * @maps id
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * Returns Store Id.
     * Store identifier.
     */
    public function getStoreId(): ?string
    {
        return $this->storeId;
    }

    /**
     * Sets Store Id.
     * Store identifier.
     *
     * @maps store_id
     */
    public function setStoreId(?string $storeId): void
    {
        $this->storeId = $storeId;
    }

    /**
     * Returns Email.
     * Customer email address.
     */
    public function getEmail(): ?string
    {
        if (count($this->email) == 0) {
            return null;
        }
        return $this->email['value'];
    }

    /**
     * Sets Email.
     * Customer email address.
     *
     * @maps email
     */
    public function setEmail(?string $email): void
    {
        $this->email['value'] = $email;
    }

    /**
     * Unsets Email.
     * Customer email address.
     */
    public function unsetEmail(): void
    {
        $this->email = [];
    }

    /**
     * Returns Active.
     * Whether the resource is active.
     */
    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * Sets Active.
     * Whether the resource is active.
     *
     * @maps active
     */
    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Returns Mode.
     * Transaction Token Mode schema.
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * Sets Mode.
     * Transaction Token Mode schema.
     *
     * @maps mode
     * @factory \UnivaPay\Models\TransactionTokenMode::checkValue
     */
    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Returns Type.
     * Transaction Token Type schema.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Sets Type.
     * Transaction Token Type schema.
     *
     * @maps type
     * @factory \UnivaPay\Models\TransactionTokenType::checkValue
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * Returns Usage Limit.
     * Usage limit applied to the token.
     */
    public function getUsageLimit(): ?string
    {
        if (count($this->usageLimit) == 0) {
            return null;
        }
        return $this->usageLimit['value'];
    }

    /**
     * Sets Usage Limit.
     * Usage limit applied to the token.
     *
     * @maps usage_limit
     */
    public function setUsageLimit(?string $usageLimit): void
    {
        $this->usageLimit['value'] = $usageLimit;
    }

    /**
     * Unsets Usage Limit.
     * Usage limit applied to the token.
     */
    public function unsetUsageLimit(): void
    {
        $this->usageLimit = [];
    }

    /**
     * Returns Confirmed.
     * Whether the token has been confirmed.
     */
    public function getConfirmed(): ?bool
    {
        if (count($this->confirmed) == 0) {
            return null;
        }
        return $this->confirmed['value'];
    }

    /**
     * Sets Confirmed.
     * Whether the token has been confirmed.
     *
     * @maps confirmed
     */
    public function setConfirmed(?bool $confirmed): void
    {
        $this->confirmed['value'] = $confirmed;
    }

    /**
     * Unsets Confirmed.
     * Whether the token has been confirmed.
     */
    public function unsetConfirmed(): void
    {
        $this->confirmed = [];
    }

    /**
     * Returns Metadata.
     * Arbitrary key-value metadata.
     *
     * @return array<string,string|null|int|float|bool>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Sets Metadata.
     * Arbitrary key-value metadata.
     *
     * @maps metadata
     * @mapsBy anyOf(array<string,anyOf(anyOf(string,null),int,float,bool,anyOf(anyOf(string,null),int,float,bool)[])>,null)
     *
     * @param array<string,string|null|int|float|bool>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Returns Created On.
     * Timestamp when the resource was created.
     */
    public function getCreatedOn(): ?\DateTime
    {
        return $this->createdOn;
    }

    /**
     * Sets Created On.
     * Timestamp when the resource was created.
     *
     * @maps created_on
     * @factory \UnivaPay\Utils\DateTimeHelper::fromRfc3339DateTime
     */
    public function setCreatedOn(?\DateTime $createdOn): void
    {
        $this->createdOn = $createdOn;
    }

    /**
     * Returns Updated On.
     * Timestamp when the resource was last updated.
     */
    public function getUpdatedOn(): ?\DateTime
    {
        return $this->updatedOn;
    }

    /**
     * Sets Updated On.
     * Timestamp when the resource was last updated.
     *
     * @maps updated_on
     * @factory \UnivaPay\Utils\DateTimeHelper::fromRfc3339DateTime
     */
    public function setUpdatedOn(?\DateTime $updatedOn): void
    {
        $this->updatedOn = $updatedOn;
    }

    /**
     * Returns Last Used On.
     * Timestamp when the token was last used.
     */
    public function getLastUsedOn(): ?\DateTime
    {
        if (count($this->lastUsedOn) == 0) {
            return null;
        }
        return $this->lastUsedOn['value'];
    }

    /**
     * Sets Last Used On.
     * Timestamp when the token was last used.
     *
     * @maps last_used_on
     * @factory \UnivaPay\Utils\DateTimeHelper::fromRfc3339DateTime
     */
    public function setLastUsedOn(?\DateTime $lastUsedOn): void
    {
        $this->lastUsedOn['value'] = $lastUsedOn;
    }

    /**
     * Unsets Last Used On.
     * Timestamp when the token was last used.
     */
    public function unsetLastUsedOn(): void
    {
        $this->lastUsedOn = [];
    }

    /**
     * Returns Payment Type.
     * Payment method type. Always `qr_merchant` for this variant.
     */
    public function getPaymentType(): string
    {
        return $this->paymentType;
    }

    /**
     * Sets Payment Type.
     * Payment method type. Always `qr_merchant` for this variant.
     *
     * @maps payment_type
     */
    public function setPaymentType(string $paymentType): void
    {
        $this->paymentType = $paymentType;
    }

    /**
     * Returns Data.
     * Token Response Qr Merchant Data schema.
     */
    public function getData(): TokenResponseQrMerchantData
    {
        return $this->data;
    }

    /**
     * Sets Data.
     * Token Response Qr Merchant Data schema.
     *
     * @required
     * @maps data
     */
    public function setData(TokenResponseQrMerchantData $data): void
    {
        $this->data = $data;
    }

    /**
     * Converts the QrMerchantTransactionToken object to a human-readable string representation.
     *
     * @return string The string representation of the QrMerchantTransactionToken object.
     */
    public function __toString(): string
    {
        return ApiHelper::stringify(
            'QrMerchantTransactionToken',
            [
                'id' => $this->id,
                'storeId' => $this->storeId,
                'email' => $this->getEmail(),
                'active' => $this->active,
                'mode' => $this->mode,
                'type' => $this->type,
                'usageLimit' => $this->getUsageLimit(),
                'confirmed' => $this->getConfirmed(),
                'metadata' => $this->metadata,
                'createdOn' => $this->createdOn,
                'updatedOn' => $this->updatedOn,
                'lastUsedOn' => $this->getLastUsedOn(),
                'paymentType' => $this->paymentType,
                'data' => $this->data,
                'additionalProperties' => $this->additionalProperties
            ]
        );
    }

    protected $propertyNames = [
        'id',
        'store_id',
        'email',
        'active',
        'mode',
        'type',
        'usage_limit',
        'confirmed',
        'metadata',
        'created_on',
        'updated_on',
        'last_used_on',
        'payment_type',
        'data'
    ];

    private $additionalProperties = [];

    /**
     * Add an additional property to this model.
     *
     * @param string $name Name of property.
     * @param mixed $value Value of property.
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
     * @return mixed|false Value of the property.
     */
    public function findAdditionalProperty(string $name)
    {
        if (isset($this->additionalProperties[$name])) {
            return $this->additionalProperties[$name];
        }
        return false;
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
        if (isset($this->id)) {
            $json['id']           = $this->id;
        }
        if (isset($this->storeId)) {
            $json['store_id']     = $this->storeId;
        }
        if (!empty($this->email)) {
            $json['email']        = $this->email['value'];
        }
        if (isset($this->active)) {
            $json['active']       = $this->active;
        }
        if (isset($this->mode)) {
            $json['mode']         = TransactionTokenMode::checkValue($this->mode);
        }
        if (isset($this->type)) {
            $json['type']         = TransactionTokenType::checkValue($this->type);
        }
        if (!empty($this->usageLimit)) {
            $json['usage_limit']  = $this->usageLimit['value'];
        }
        if (!empty($this->confirmed)) {
            $json['confirmed']    = $this->confirmed['value'];
        }
        if (isset($this->metadata)) {
            $json['metadata']     =
                ApiHelper::getJsonHelper()->verifyTypes(
                    $this->metadata,
                    'anyOf(array<string,anyOf(anyOf(string,null),int,float,bool,anyOf(anyOf(string,nu' .
                    'll),int,float,bool)[])>,null)'
                );
        }
        if (isset($this->createdOn)) {
            $json['created_on']   = DateTimeHelper::toRfc3339DateTime($this->createdOn);
        }
        if (isset($this->updatedOn)) {
            $json['updated_on']   = DateTimeHelper::toRfc3339DateTime($this->updatedOn);
        }
        if (!empty($this->lastUsedOn)) {
            $json['last_used_on'] = DateTimeHelper::toRfc3339DateTime($this->lastUsedOn['value']);
        }
        $json['payment_type']     = $this->paymentType;
        $json['data']             = $this->data;
        $json = array_merge($json, $this->additionalProperties);

        return (!$asArrayWhenEmpty && empty($json)) ? new stdClass() : $json;
    }
}
