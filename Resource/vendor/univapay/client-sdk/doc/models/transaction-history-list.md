
# Transaction History List

Paginated list of transaction history rows. Unlike other list responses in this API, `total_hits` is only present on the first page (no `cursor` supplied) or the last page, and `next_cursor` is only present while `has_more` is `true`.

*This model accepts additional fields of type array.*

## Structure

`TransactionHistoryList`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `items` | [`?(TransactionHistoryItem[])`](../../doc/models/transaction-history-item.md) | Optional | List of resources. | getItems(): ?array | setItems(?array items): void |
| `hasMore` | `?bool` | Optional | Whether more results are available. | getHasMore(): ?bool | setHasMore(?bool hasMore): void |
| `totalHits` | `?int` | Optional | Total number of matching resources. Present on the first page (no `cursor` supplied) or the last page; absent on intermediate pages while `has_more` is `true`. | getTotalHits(): ?int | setTotalHits(?int totalHits): void |
| `nextCursor` | `?string` | Optional | Cursor to pass as `cursor` to fetch the next page. Present only while `has_more` is `true`. | getNextCursor(): ?string | setNextCursor(?string nextCursor): void |
| `additionalProperties` | `array<string, array>` | Optional | - | findAdditionalProperty(string key): array | additionalProperty(string key, array value): void |

## Example

```php
use UnivaPay\Models\Builders\TransactionHistoryListBuilder;
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
use UnivaPay\Models\TransactionHistoryRefundReason;
use UnivaPay\ApiHelper;
use UnivaPay\Models\TransactionHistoryChargeType;

$transactionHistoryList = TransactionHistoryListBuilder::init()
    ->items(
        [
            TransactionHistoryItemBuilder::init()
                ->storeId('11edf541-c42d-653c-8c3d-dfe0a55f95c0')
                ->resourceId('11ef0000-0000-4000-8000-000000000070')
                ->chargeId(null)
                ->amount(1000)
                ->currency('JPY')
                ->amountFormatted(1000)
                ->type(TransactionHistoryType::CHARGE)
                ->status(TransactionHistoryStatus::SUCCESSFUL)
                ->metadata(
                    GenericMetadataBuilder::init()
                        ->orderId('order_id0')
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
                ->bankTransferPaymentStatus(null)
                ->bankTransferLatestDepositDate(null)
                ->mcpTokenId(null)
                ->chargeType(TransactionHistoryChargeType::NORMAL)
                ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
                ->build(),
            TransactionHistoryItemBuilder::init()
                ->storeId('11edf541-c42d-653c-8c3d-dfe0a55f95c0')
                ->resourceId('11ef0000-0000-4000-8000-000000000010')
                ->chargeId('11ef0000-0000-4000-8000-000000000070')
                ->amount(500)
                ->currency('JPY')
                ->amountFormatted(500)
                ->type(TransactionHistoryType::REFUND)
                ->status(TransactionHistoryStatus::SUCCESSFUL)
                ->metadata(
                    GenericMetadataBuilder::init()
                        ->orderId('order_id0')
                        ->univapayName('univapay-name8')
                        ->univapayPhoneNumber('univapay-phone-number2')
                        ->additionalProperty('exampleAdditionalProperty', 'String4')
                        ->build()
                )
                ->createdOn(DateTimeHelper::fromRfc3339DateTime('2024-05-01T13:00:00.000000Z'))
                ->mode(TransactionHistoryMode::TEST)
                ->merchantName('Test merchant')
                ->storeName('Test store')
                ->paymentType(TransactionHistoryPaymentType::CARD)
                ->userData(
                    TransactionHistoryUserDataBuilder::init()
                        ->type(TransactionHistoryType::REFUND)
                        ->cardholderName('cardholder_name8')
                        ->cardholderEmailAddress('cardholder_email_address0')
                        ->cardholderPhoneNumber('cardholder_phone_number4')
                        ->customerName('customer_name8')
                        ->reason(TransactionHistoryRefundReason::CUSTOMER_REQUEST)
                        ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
                        ->build()
                )
                ->bankTransferPaymentStatus(null)
                ->bankTransferLatestDepositDate(null)
                ->mcpTokenId(null)
                ->chargeType(null)
                ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
                ->build()
        ]
    )
    ->hasMore(false)
    ->totalHits(2)
    ->nextCursor('11ef0000-0000-4000-8000-000000000071')
    ->additionalProperty('exampleAdditionalProperty', ApiHelper::deserialize('{"key1":"val1","key2":"val2"}'))
    ->build();
```

