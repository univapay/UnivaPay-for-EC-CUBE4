
# Paidy Transaction Token

Stored transaction token resource for a `paidy` payment type.

*This model accepts additional fields of type array.*

## Structure

`PaidyTransactionToken`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `id` | `?string` | Optional | Unique identifier. | getId(): ?string | setId(?string id): void |
| `storeId` | `?string` | Optional | Store identifier. | getStoreId(): ?string | setStoreId(?string storeId): void |
| `email` | `?string` | Optional | Customer email address. | getEmail(): ?string | setEmail(?string email): void |
| `active` | `?bool` | Optional | Whether the resource is active. | getActive(): ?bool | setActive(?bool active): void |
| `mode` | [`?string(TransactionTokenMode)`](../../doc/models/transaction-token-mode.md) | Optional | Transaction Token Mode schema. | getMode(): ?string | setMode(?string mode): void |
| `type` | [`?string(TransactionTokenType)`](../../doc/models/transaction-token-type.md) | Optional | Transaction Token Type schema. | getType(): ?string | setType(?string type): void |
| `usageLimit` | `?string` | Optional | Usage limit applied to the token. | getUsageLimit(): ?string | setUsageLimit(?string usageLimit): void |
| `confirmed` | `?bool` | Optional | Whether the token has been confirmed. | getConfirmed(): ?bool | setConfirmed(?bool confirmed): void |
| `metadata` | array<string,string\|null\|int\|float\|bool>\|null | Optional | Alias of GenericMetadataValue, retained because this schema name is part of the published SDK surface. Do not narrow it — see GenericMetadataValue for the contract. | getMetadata(): ?array | setMetadata(?array metadata): void |
| `createdOn` | `?DateTime` | Optional | Timestamp when the resource was created. | getCreatedOn(): ?\DateTime | setCreatedOn(?\DateTime createdOn): void |
| `updatedOn` | `?DateTime` | Optional | Timestamp when the resource was last updated. | getUpdatedOn(): ?\DateTime | setUpdatedOn(?\DateTime updatedOn): void |
| `lastUsedOn` | `?DateTime` | Optional | Timestamp when the token was last used. | getLastUsedOn(): ?\DateTime | setLastUsedOn(?\DateTime lastUsedOn): void |
| `paymentType` | `string` | Required, Constant | Payment method type. Always `paidy` for this variant.<br><br>**Value**: `'paidy'` | getPaymentType(): string | setPaymentType(string paymentType): void |
| `data` | [`TokenResponsePaidyData`](../../doc/models/token-response-paidy-data.md) | Required | Token Response Paidy Data schema. | getData(): TokenResponsePaidyData | setData(TokenResponsePaidyData data): void |
| `additionalProperties` | `array<string, array>` | Optional | - | findAdditionalProperty(string key): array | additionalProperty(string key, array value): void |

## Example

```php
use UnivaPay\Models\Builders\PaidyTransactionTokenBuilder;
use UnivaPay\Models\Builders\TokenResponsePaidyDataBuilder;
use UnivaPay\Models\Builders\TokenResponsePaidyDataShippingAddressBuilder;
use UnivaPay\ApiHelper;
use UnivaPay\Models\TransactionTokenMode;
use UnivaPay\Models\TransactionTokenType;
use UnivaPay\Utils\DateTimeHelper;

$paidyTransactionToken = PaidyTransactionTokenBuilder::init(
    TokenResponsePaidyDataBuilder::init(
        'paidy-token-abc123'
    )
        ->phoneNumber('08012341234')
        ->shippingAddress(
            TokenResponsePaidyDataShippingAddressBuilder::init()
                ->zip('105-0011')
                ->line1('1-1-1')
                ->line2('line24')
                ->city('Minato')
                ->state('Tokyo')
                ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
                ->build()
        )
        ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
        ->build()
)
    ->id('6426bbd2-17bd-41bf-883b-1fe970db48ee')
    ->storeId('fc264608-9a9e-495e-844e-a08129a81af4')
    ->email('test@univapay.com')
    ->active(true)
    ->mode(TransactionTokenMode::LIVE)
    ->type(TransactionTokenType::ONE_TIME)
    ->usageLimit('example')
    ->confirmed(true)
    ->metadata(
        [
            'customer_id' => 'cust_12345'
        ]
    )
    ->createdOn(DateTimeHelper::fromRfc3339DateTime('2026-04-09T07:35:50Z'))
    ->updatedOn(DateTimeHelper::fromRfc3339DateTime('2026-04-09T07:35:50Z'))
    ->lastUsedOn(DateTimeHelper::fromRfc3339DateTime('2026-04-09T07:35:50.000000Z'))
    ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
    ->build();
```

