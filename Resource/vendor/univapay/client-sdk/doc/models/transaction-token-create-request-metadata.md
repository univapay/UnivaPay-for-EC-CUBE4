
# Transaction Token Create Request Metadata

A free-form dictionary for custom metadata.

*This model accepts additional fields of type [?string|int|float|bool](../../doc/models/containers/transaction-token-create-metadata-props.md).*

## Structure

`TransactionTokenCreateRequestMetadata`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `univapayReferenceId` | `?string` | Optional | Any arbitrary value (Free format). | getUnivapayReferenceId(): ?string | setUnivapayReferenceId(?string univapayReferenceId): void |
| `univapayCustomerId` | `?string` | Optional | Customer ID. | getUnivapayCustomerId(): ?string | setUnivapayCustomerId(?string univapayCustomerId): void |
| `univapayName` | `?string` | Optional | Consumer name passed to payment processors that require it (e.g., konbini, bank transfer). | getUnivapayName(): ?string | setUnivapayName(?string univapayName): void |
| `univapayPhoneNumber` | `?string` | Optional | Consumer phone number passed to payment processors that require it. | getUnivapayPhoneNumber(): ?string | setUnivapayPhoneNumber(?string univapayPhoneNumber): void |
| `additionalProperties` | array<string, string\|null\|int\|float\|bool> | Optional | Alias of GenericMetadataValue, retained because this schema name is part of the published SDK surface. Do not narrow it — see GenericMetadataValue for the contract. | findAdditionalProperty(string key): string\|null\|int\|float\|bool | additionalProperty(string key, string\|null\|int\|float\|bool value): void |

## Example

```php
use UnivaPay\Models\Builders\TransactionTokenCreateRequestMetadataBuilder;

$transactionTokenCreateRequestMetadata = TransactionTokenCreateRequestMetadataBuilder::init()
    ->univapayReferenceId('ref-998877')
    ->univapayCustomerId('0fd29949-07d5-4a91-8eaf-fbce0897d944')
    ->build();
```

