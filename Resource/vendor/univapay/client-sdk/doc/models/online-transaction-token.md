
# Online Transaction Token

Stored transaction token resource for an `online` payment type.

*This model accepts additional fields of type array.*

## Structure

`OnlineTransactionToken`

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
| `paymentType` | `string` | Required, Constant | Payment method type. Always `online` for this variant.<br><br>**Value**: `'online'` | getPaymentType(): string | setPaymentType(string paymentType): void |
| `data` | [`TokenResponseOnlineData`](../../doc/models/token-response-online-data.md) | Required | Token Response Online Data schema. | getData(): TokenResponseOnlineData | setData(TokenResponseOnlineData data): void |
| `additionalProperties` | `array<string, array>` | Optional | - | findAdditionalProperty(string key): array | additionalProperty(string key, array value): void |

## Example

```php
use UnivaPay\Models\Builders\OnlineTransactionTokenBuilder;
use UnivaPay\Models\Builders\TokenResponseOnlineDataBuilder;
use UnivaPay\Models\BaseOnlineDataBrand;
use UnivaPay\Models\BaseOnlineDataCallMethod;
use UnivaPay\Models\BaseOnlineDataOsType;
use UnivaPay\Models\BaseOnlineDataUserIdentifierSource;
use UnivaPay\ApiHelper;
use UnivaPay\Models\TransactionTokenMode;
use UnivaPay\Models\TransactionTokenType;
use UnivaPay\Utils\DateTimeHelper;

$onlineTransactionToken = OnlineTransactionTokenBuilder::init(
    TokenResponseOnlineDataBuilder::init()
        ->brand(BaseOnlineDataBrand::WE_CHAT_ONLINE)
        ->callMethod(BaseOnlineDataCallMethod::WEB)
        ->osType(BaseOnlineDataOsType::ANDROID)
        ->userIdentifier('wechat_open_id_12345')
        ->userIdentifierSource(BaseOnlineDataUserIdentifierSource::PROVIDED)
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

