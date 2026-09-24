
# Transaction History Item

A single charge or refund row in the merchant's transaction history.

*This model accepts additional fields of type array.*

## Structure

`TransactionHistoryItem`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `storeId` | `?string` | Optional | Store identifier. | getStoreId(): ?string | setStoreId(?string storeId): void |
| `resourceId` | `?string` | Optional | ID of the underlying resource — a charge ID for charge rows, a refund ID for refund rows. | getResourceId(): ?string | setResourceId(?string resourceId): void |
| `chargeId` | `?string` | Optional | ID of the originating charge. `null` for charge rows; set for refund rows. | getChargeId(): ?string | setChargeId(?string chargeId): void |
| `amount` | `?int` | Optional | Amount, in the currency's minor unit. | getAmount(): ?int | setAmount(?int amount): void |
| `currency` | `?string` | Optional | ISO-4217 currency code. | getCurrency(): ?string | setCurrency(?string currency): void |
| `amountFormatted` | `?float` | Optional | Amount, formatted per the currency's display scale. | getAmountFormatted(): ?float | setAmountFormatted(?float amountFormatted): void |
| `type` | [`?string(TransactionHistoryType)`](../../doc/models/transaction-history-type.md) | Optional | Whether this row represents a charge or a refund. | getType(): ?string | setType(?string type): void |
| `status` | [`?string(TransactionHistoryStatus)`](../../doc/models/transaction-history-status.md) | Optional | Status of the underlying resource. Charge rows use the full set of values; refund rows only ever report `pending`, `successful`, `failed`, or `error`. | getStatus(): ?string | setStatus(?string status): void |
| `metadata` | [`?GenericMetadata`](../../doc/models/generic-metadata.md) | Optional | A free-form dictionary for custom metadata. | getMetadata(): ?GenericMetadata | setMetadata(?GenericMetadata metadata): void |
| `createdOn` | `?DateTime` | Optional | Timestamp when the underlying resource was created. | getCreatedOn(): ?\DateTime | setCreatedOn(?\DateTime createdOn): void |
| `mode` | [`?string(TransactionHistoryMode)`](../../doc/models/transaction-history-mode.md) | Optional | Environment mode: `live` and `test` reflect the credential used to authenticate, while `live_test` is reserved for privileged callers testing against live-mode data. | getMode(): ?string | setMode(?string mode): void |
| `merchantName` | `?string` | Optional | Merchant display name. | getMerchantName(): ?string | setMerchantName(?string merchantName): void |
| `storeName` | `?string` | Optional | Store display name. | getStoreName(): ?string | setStoreName(?string storeName): void |
| `paymentType` | [`?string(TransactionHistoryPaymentType)`](../../doc/models/transaction-history-payment-type.md) | Optional | The payment method used for the underlying charge. | getPaymentType(): ?string | setPaymentType(?string paymentType): void |
| `userData` | [`?TransactionHistoryUserData`](../../doc/models/transaction-history-user-data.md) | Optional | Payment-type-specific details for this row. This is a single flat object covering every payment type — the fields actually populated depend on `payment_type` (documented per field below). Fields not applicable to a given payment type are omitted. | getUserData(): ?TransactionHistoryUserData | setUserData(?TransactionHistoryUserData userData): void |
| `bankTransferPaymentStatus` | [`?string(BankTransferPaymentStatus)`](../../doc/models/bank-transfer-payment-status.md) | Optional | Bank transfer payment status, or `null` when not applicable. | getBankTransferPaymentStatus(): ?string | setBankTransferPaymentStatus(?string bankTransferPaymentStatus): void |
| `bankTransferLatestDepositDate` | `?DateTime` | Optional | Timestamp of the most recent deposit matched against a bank transfer charge. `null` when not applicable. | getBankTransferLatestDepositDate(): ?\DateTime | setBankTransferLatestDepositDate(?\DateTime bankTransferLatestDepositDate): void |
| `mcpTokenId` | `?string` | Optional | ID of the multi-currency-pricing token used, when applicable. `null` when not applicable. | getMcpTokenId(): ?string | setMcpTokenId(?string mcpTokenId): void |
| `chargeType` | [`?string(TransactionHistoryChargeType)`](../../doc/models/transaction-history-charge-type.md) | Optional | Charge type, or `null` when not applicable. | getChargeType(): ?string | setChargeType(?string chargeType): void |
| `additionalProperties` | `array<string, array>` | Optional | - | findAdditionalProperty(string key): array | additionalProperty(string key, array value): void |

## Example

```php
use UnivaPay\Models\Builders\TransactionHistoryItemBuilder;
use UnivaPay\Models\TransactionHistoryType;
use UnivaPay\Models\TransactionHistoryStatus;
use UnivaPay\Models\Builders\GenericMetadataBuilder;
use UnivaPay\Utils\DateTimeHelper;
use UnivaPay\Models\TransactionHistoryMode;
use UnivaPay\Models\TransactionHistoryPaymentType;
use UnivaPay\Models\Builders\TransactionHistoryUserDataBuilder;
use UnivaPay\Models\TransactionHistoryServiceProvider;
use UnivaPay\Models\Builders\TransactionHistoryRefundBuilder;
use UnivaPay\Models\TransactionHistoryRefundStatus;
use UnivaPay\ApiHelper;
use UnivaPay\Models\BankTransferPaymentStatus;
use UnivaPay\Models\TransactionHistoryChargeType;

$transactionHistoryItem = TransactionHistoryItemBuilder::init()
    ->storeId('11edf541-c42d-653c-8c3d-dfe0a55f95c0')
    ->resourceId('11ef0000-0000-4000-8000-000000000070')
    ->chargeId('000017d4-0000-0000-0000-000000000000')
    ->amount(1000)
    ->currency('JPY')
    ->amountFormatted(1000)
    ->type(TransactionHistoryType::CHARGE)
    ->status(TransactionHistoryStatus::SUCCESSFUL)
    ->metadata(
        GenericMetadataBuilder::init()
            ->orderId('12345')
            ->univapayName('univapay-name8')
            ->univapayPhoneNumber('univapay-phone-number2')
            ->additionalProperty('exampleAdditionalProperty', 'String4')
            ->build()
    )
    ->createdOn(DateTimeHelper::fromRfc3339DateTime('2024-05-01T12:34:56.789Z'))
    ->mode(TransactionHistoryMode::TEST)
    ->merchantName('Test merchant')
    ->storeName('Test store')
    ->paymentType(TransactionHistoryPaymentType::CARD)
    ->userData(
        TransactionHistoryUserDataBuilder::init()
            ->type(TransactionHistoryType::CHARGE)
            ->cardholderName('Some Guy')
            ->cardholderEmailAddress('test4@univapay.com')
            ->cardholderPhoneNumber('cardholder_phone_number4')
            ->customerName('customer_name8')
            ->brand('visa')
            ->gateway('test')
            ->serviceProvider(TransactionHistoryServiceProvider::CREDIT)
            ->refunds(
                [
                    TransactionHistoryRefundBuilder::init()
                        ->refundId('11ef0000-0000-4000-8000-000000000010')
                        ->amount(500)
                        ->currency('JPY')
                        ->amountFormatted(500)
                        ->status(TransactionHistoryRefundStatus::SUCCESSFUL)
                        ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
                        ->build()
                ]
            )
            ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
            ->build()
    )
    ->bankTransferPaymentStatus(BankTransferPaymentStatus::EXACT)
    ->chargeType(TransactionHistoryChargeType::NORMAL)
    ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
    ->build();
```

