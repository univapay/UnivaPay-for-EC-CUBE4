# Transaction Tokens

```php
$transactionTokensApi = $client->getTransactionTokensApi();
```

## Class Name

`TransactionTokensApi`

## Methods

* [Create Transaction Token](../../doc/controllers/transaction-tokens.md#create-transaction-token)
* [List All Transaction Tokens](../../doc/controllers/transaction-tokens.md#list-all-transaction-tokens)
* [List Store Transaction Tokens](../../doc/controllers/transaction-tokens.md#list-store-transaction-tokens)
* [Get Transaction Token](../../doc/controllers/transaction-tokens.md#get-transaction-token)
* [Update Transaction Token](../../doc/controllers/transaction-tokens.md#update-transaction-token)
* [Delete Transaction Token](../../doc/controllers/transaction-tokens.md#delete-transaction-token)
* [Enable Token Three Ds](../../doc/controllers/transaction-tokens.md#enable-token-three-ds)
* [Disable Token Three Ds](../../doc/controllers/transaction-tokens.md#disable-token-three-ds)
* [Get Token Three Ds Issuer Token](../../doc/controllers/transaction-tokens.md#get-token-three-ds-issuer-token)


# Create Transaction Token

Exchange raw payment data for a secure token. **PCI DSS Compliance Required** if sending raw card numbers.

```php
function createTransactionToken(
    TransactionTokenCreateRequest $body,
    ?string $idempotencyKey = null
): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `body` | [`TransactionTokenCreateRequest`](../../doc/models/transaction-token-create-request.md) | Body, Required | Request payload for creating a transaction token. |
| `idempotencyKey` | `?string` | Header, Optional | An optional idempotency key to prevent double charges and duplicate operations. We recommend a randomly generated UUID (v4). |

## Response Type

**201**: Token Created

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type `CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken`.

## Example Usage

```php
$body = TransactionTokenCreateRequestBuilder::init(
    TransactionTokenCreateRequestPaymentType::CARD,
    TransactionTokenCreateRequestType::RECURRING,
    TokenCreateCardDataBuilder::init(
        '4242424242424242',
        '09',
        '26'
    )
        ->cardholder('TEST TEST')
        ->cvv('123')
        ->phoneNumber(
            TokenCreatePhoneNumberBuilder::init(
                '81',
                '08012341234'
            )->build()
        )
        ->cvvAuthorize(
            TokenCreateCardDataCvvAuthorizeBuilder::init()
                ->enabled(false)
                ->currency('JPY')
                ->build()
        )
        ->threeDs(
            TokenCreateCardDataThreeDsBuilder::init()
                ->redirectEndpoint('https://univapay.com/redirect/index.html')
                ->build()
        )
        ->build()
)
    ->email('test@univapay.com')
    ->metadata(
        TransactionTokenCreateRequestMetadataBuilder::init()
            ->univapayPhoneNumber('+81 08012341234')
            ->build()
    )
    ->build();

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->createTransactionToken($body);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response

```
{
  "id": "11f11e85-e9e9-b198-b990-c3a715943241",
  "store_id": "11f0e274-1e3b-4752-9513-33d3e07ede13",
  "email": "test@test.com",
  "payment_type": "card",
  "active": true,
  "mode": "live",
  "type": "recurring",
  "usage_limit": null,
  "confirmed": null,
  "metadata": {
    "univapay-link-id": "11f11e85-1b45-dace-bf3d-cbcae52f65fc",
    "univapay-name": "test",
    "univapay-phone-number": "+81 08012341234",
    "items": [
      "productName: Cherry Ice Sandwich, price: 3080, quantity: 1",
      "productName: Shipping, price: 200, quantity: 1"
    ],
    "order_no": 1,
    "note": null
  },
  "created_on": "2026-03-13T02:39:52.908468Z",
  "updated_on": "2026-03-13T02:39:52.908468Z",
  "last_used_on": null,
  "data": {
    "card": {
      "cardholder": "TEST TEST",
      "exp_month": 9,
      "exp_year": 2026,
      "card_bin": "424242",
      "last_four": "424242",
      "brand": "visa",
      "card_type": "credit",
      "country": "JP",
      "category": "standard",
      "issuer": "issuer",
      "sub_brand": "none"
    },
    "billing": {
      "line1": null,
      "line2": null,
      "state": null,
      "city": null,
      "country": null,
      "zip": null,
      "phone_number": {
        "country_code": 81,
        "local_number": "08012341234"
      }
    },
    "cvv_authorize": {
      "enabled": false,
      "status": null,
      "charge_id": null,
      "credentials_id": null,
      "currency": null
    },
    "cvv_authorize_check": {
      "status": null,
      "charge_id": null,
      "date": null
    },
    "three_ds": {
      "enabled": true,
      "status": "pending",
      "redirect_endpoint": "https://univapay.com/redirect/index.html",
      "error": null,
      "exempted": false
    }
  }
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# List All Transaction Tokens

Lists all transaction tokens across all stores.

```php
function listAllTransactionTokens(
    ?string $search = null,
    ?string $customerId = null,
    ?string $type = null,
    ?string $mode = null,
    ?string $active = TransactionTokenActiveFilter::ACTIVE,
    ?int $limit = 10,
    ?string $cursor = null,
    ?string $cursorDirection = CursorDirectionQuery::DESC
): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `search` | `?string` | Query, Optional | Case-insensitive free-text search. |
| `customerId` | `?string` | Query, Optional | Filter by customer ID. |
| `type` | [`?string(TransactionTokenListType)`](../../doc/models/transaction-token-list-type.md) | Query, Optional | Filter by token type. `one_time` tokens are excluded from listings and cannot be filtered on; filtering to `recurring` requires the App Token Secret. |
| `mode` | [`?string(ModeQuery)`](../../doc/models/mode-query.md) | Query, Optional | Filter by environment mode. |
| `active` | [`?string(TransactionTokenActiveFilter)`](../../doc/models/transaction-token-active-filter.md) | Query, Optional | Filter recurring tokens by whether they are still active.<br><br>**Default**: `TransactionTokenActiveFilter::ACTIVE` |
| `limit` | `?int` | Query, Optional | Maximum number of resources to return in one page.<br><br>**Default**: `10`<br><br>**Constraints**: `<= 100` |
| `cursor` | `?string` | Query, Optional | Cursor pointing to the resource after which pagination should continue. |
| `cursorDirection` | [`?string(CursorDirectionQuery)`](../../doc/models/cursor-direction-query.md) | Query, Optional | Pagination direction relative to the supplied cursor.<br><br>**Default**: `CursorDirectionQuery::DESC` |

## Response Type

**200**: List of Tokens

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type [`TransactionTokenList`](../../doc/models/transaction-token-list.md).

## Example Usage

```php
$search = 'tokyo';

$customerId = '8a3f1b8e-2c1a-4b7a-9c2e-6f6b6f6e2b10';

$type = TransactionTokenListType::RECURRING;

$mode = ModeQuery::LIVE;

$active = TransactionTokenActiveFilter::ACTIVE;

$limit = 10;

$cursor = '3541d4fa-596d-428e-8a36-f274e1b3d505';

$cursorDirection = CursorDirectionQuery::ASC;

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->listAllTransactionTokens(
    $search,
    $customerId,
    $type,
    $mode,
    $active,
    $limit,
    $cursor,
    $cursorDirection
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'TransactionTokenList:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response *(as JSON)*

```json
{
  "items": [
    {
      "id": "2fe23e45-f95d-4c95-9963-739070096443",
      "store_id": "79e9504e-96d8-46ed-8d22-2e8b36238605",
      "merchant_name": "Test Merchant",
      "store_name": "Tokyo Store",
      "email": "taro@example.com",
      "payment_type": "card",
      "active": true,
      "mode": "live",
      "type": "recurring",
      "created_on": "2026-04-09T07:35:50Z",
      "updated_on": "2026-04-09T07:35:50Z",
      "user_data": {
        "cardholder_name": "TARO YAMADA",
        "email": "taro@example.com"
      }
    },
    {
      "id": "3af34f56-a06e-4d06-aa74-84a181107554",
      "store_id": "8bfa615f-a7e9-47fe-9e33-3f9c47349716",
      "merchant_name": "Test Merchant",
      "store_name": "Osaka Store",
      "email": "hanako@example.com",
      "payment_type": "card",
      "active": true,
      "mode": "live",
      "type": "one_time",
      "created_on": "2026-04-10T10:20:11Z",
      "updated_on": "2026-04-10T10:20:11Z",
      "user_data": {
        "cardholder_name": "HANAKO SUZUKI",
        "email": "hanako@example.com"
      }
    },
    {
      "id": "4bf45e67-b17f-4e17-bb85-95b292218665",
      "store_id": "79e9504e-96d8-46ed-8d22-2e8b36238605",
      "merchant_name": "Test Merchant",
      "store_name": "Tokyo Store",
      "email": "jiro@example.com",
      "payment_type": "card",
      "active": false,
      "mode": "live",
      "type": "subscription",
      "created_on": "2026-04-11T18:05:42Z",
      "updated_on": "2026-04-12T08:31:09Z",
      "user_data": {
        "cardholder_name": "JIRO TANAKA",
        "email": "jiro@example.com"
      }
    }
  ],
  "has_more": false,
  "total_hits": 3
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# List Store Transaction Tokens

Lists all transaction tokens for a specific store.

```php
function listStoreTransactionTokens(
    string $storeId,
    ?string $search = null,
    ?string $customerId = null,
    ?string $type = null,
    ?string $mode = null,
    ?string $active = TransactionTokenActiveFilter::ACTIVE,
    ?int $limit = 10,
    ?string $cursor = null,
    ?string $cursorDirection = CursorDirectionQuery::DESC
): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `search` | `?string` | Query, Optional | Case-insensitive free-text search. |
| `customerId` | `?string` | Query, Optional | Filter by customer ID. |
| `type` | [`?string(TransactionTokenListType)`](../../doc/models/transaction-token-list-type.md) | Query, Optional | Filter by token type. `one_time` tokens are excluded from listings and cannot be filtered on; filtering to `recurring` requires the App Token Secret. |
| `mode` | [`?string(ModeQuery)`](../../doc/models/mode-query.md) | Query, Optional | Filter by environment mode. |
| `active` | [`?string(TransactionTokenActiveFilter)`](../../doc/models/transaction-token-active-filter.md) | Query, Optional | Filter recurring tokens by whether they are still active.<br><br>**Default**: `TransactionTokenActiveFilter::ACTIVE` |
| `limit` | `?int` | Query, Optional | Maximum number of resources to return in one page.<br><br>**Default**: `10`<br><br>**Constraints**: `<= 100` |
| `cursor` | `?string` | Query, Optional | Cursor pointing to the resource after which pagination should continue. |
| `cursorDirection` | [`?string(CursorDirectionQuery)`](../../doc/models/cursor-direction-query.md) | Query, Optional | Pagination direction relative to the supplied cursor.<br><br>**Default**: `CursorDirectionQuery::DESC` |

## Response Type

**200**: List of Tokens

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type [`TransactionTokenList`](../../doc/models/transaction-token-list.md).

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$search = 'tokyo';

$customerId = '8a3f1b8e-2c1a-4b7a-9c2e-6f6b6f6e2b10';

$type = TransactionTokenListType::RECURRING;

$mode = ModeQuery::LIVE;

$active = TransactionTokenActiveFilter::ACTIVE;

$limit = 10;

$cursor = '3541d4fa-596d-428e-8a36-f274e1b3d505';

$cursorDirection = CursorDirectionQuery::ASC;

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->listStoreTransactionTokens(
    $storeId,
    $search,
    $customerId,
    $type,
    $mode,
    $active,
    $limit,
    $cursor,
    $cursorDirection
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'TransactionTokenList:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response *(as JSON)*

```json
{
  "items": [
    {
      "id": "2fe23e45-f95d-4c95-9963-739070096443",
      "store_id": "79e9504e-96d8-46ed-8d22-2e8b36238605",
      "merchant_name": "Test Merchant",
      "store_name": "Tokyo Store",
      "email": "taro@example.com",
      "payment_type": "card",
      "active": true,
      "mode": "live",
      "type": "recurring",
      "created_on": "2026-04-09T07:35:50Z",
      "updated_on": "2026-04-09T07:35:50Z",
      "user_data": {
        "cardholder_name": "TARO YAMADA",
        "email": "taro@example.com"
      }
    },
    {
      "id": "5cf56e78-c28a-4f28-cc96-06c303329776",
      "store_id": "79e9504e-96d8-46ed-8d22-2e8b36238605",
      "merchant_name": "Test Merchant",
      "store_name": "Tokyo Store",
      "email": "saburo@example.com",
      "payment_type": "card",
      "active": true,
      "mode": "live",
      "type": "one_time",
      "created_on": "2026-04-10T12:14:00Z",
      "updated_on": "2026-04-10T12:14:00Z",
      "user_data": {
        "cardholder_name": "SABURO KATO",
        "email": "saburo@example.com"
      }
    },
    {
      "id": "6df67e89-d39a-4039-dd07-17d414430887",
      "store_id": "79e9504e-96d8-46ed-8d22-2e8b36238605",
      "merchant_name": "Test Merchant",
      "store_name": "Tokyo Store",
      "email": "shiro@example.com",
      "payment_type": "card",
      "active": true,
      "mode": "live",
      "type": "subscription",
      "created_on": "2026-04-11T16:48:23Z",
      "updated_on": "2026-04-11T16:48:23Z",
      "user_data": {
        "cardholder_name": "SHIRO ITO",
        "email": "shiro@example.com"
      }
    }
  ],
  "has_more": false,
  "total_hits": 3
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Get Transaction Token

Retrieves the details of an existing transaction token.

```php
function getTransactionToken(string $storeId, string $id, ?bool $polling = null): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |
| `polling` | `?bool` | Query, Optional | If set to true, instructs the API to internally poll the token's 3DS or CVV authorization sub-status until it transitions to another status, or until the ~3 second server-side timeout is reached. |

## Response Type

**200**: Token Details

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type `CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken`.

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$polling = true;

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->getTransactionToken(
    $storeId,
    $id,
    $polling
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response

```
{
  "id": "11f11e85-e9e9-b198-b990-c3a715943241",
  "store_id": "11f0e274-1e3b-4752-9513-33d3e07ede13",
  "email": "test@test.com",
  "payment_type": "card",
  "active": true,
  "mode": "live",
  "type": "recurring",
  "usage_limit": null,
  "confirmed": null,
  "metadata": {
    "univapay-link-id": "11f11e85-1b45-dace-bf3d-cbcae52f65fc",
    "univapay-name": "test",
    "univapay-phone-number": "+81 08012341234",
    "items": [
      "productName: Cherry Ice Sandwich, price: 3080, quantity: 1",
      "productName: Shipping, price: 200, quantity: 1"
    ],
    "order_no": 1,
    "note": null
  },
  "created_on": "2026-03-13T02:39:52.908468Z",
  "updated_on": "2026-03-13T02:39:52.908468Z",
  "last_used_on": null,
  "data": {
    "card": {
      "cardholder": "TEST TEST",
      "exp_month": 9,
      "exp_year": 2026,
      "card_bin": "424242",
      "last_four": "424242",
      "brand": "visa",
      "card_type": "credit",
      "country": "JP",
      "category": "standard",
      "issuer": "issuer",
      "sub_brand": "none"
    },
    "billing": {
      "line1": null,
      "line2": null,
      "state": null,
      "city": null,
      "country": null,
      "zip": null,
      "phone_number": {
        "country_code": 81,
        "local_number": "08012341234"
      }
    },
    "cvv_authorize": {
      "enabled": false,
      "status": null,
      "charge_id": null,
      "credentials_id": null,
      "currency": null
    },
    "cvv_authorize_check": {
      "status": null,
      "charge_id": null,
      "date": null
    },
    "three_ds": {
      "enabled": true,
      "status": "pending",
      "redirect_endpoint": "https://univapay.com/redirect/index.html",
      "error": null,
      "exempted": false
    }
  }
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Update Transaction Token

⚠️ **LEGACY WARNING: Discouraged Operation**
While it is technically possible to update a transaction token, this practice is highly discouraged and is maintained solely for legacy reasons.
**Updating raw card details requires your server environment to be fully PCI DSS compliant.**
**Recommended Approach:** Instead of updating an existing token, it is best practice to create an entirely new transaction token using Univapay's frontend integrations (**Link Form**, **Widget**, or **Inline Form**). This allows Univapay to securely handle the customer's payment data without it ever touching your servers.
--- **Legacy Usage:** Updates CVV, Address, Email, or Card Details.  *Note: If updating only the CVV to resolve a `RECURRING_USAGE_REQUIRES_CVV` error, the application token secret is not required.*

```php
function updateTransactionToken(
    string $storeId,
    string $id,
    ?string $idempotencyKey = null,
    ?TransactionTokenUpdateRequest $body = null
): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |
| `idempotencyKey` | `?string` | Header, Optional | An optional idempotency key to prevent double charges and duplicate operations. We recommend a randomly generated UUID (v4). |
| `body` | [`?TransactionTokenUpdateRequest`](../../doc/models/transaction-token-update-request.md) | Body, Optional | Request payload for updating a transaction token. |

## Response Type

**200**: Token Updated Successfully

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type `CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken`.

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$body = TransactionTokenUpdateRequestBuilder::init()
    ->email('test.update@test.com')
    ->data(
        TransactionTokenUpdateRequestDataBuilder::init()
            ->cvv('123')
            ->cardholder('TARO YAMADA')
            ->cardNumber('4000020000000000')
            ->expMonth(12)
            ->expYear(2099)
            ->line1('11111')
            ->line2('222')
            ->state('Tokyo')
            ->city('テスト区一丁目')
            ->country('JP')
            ->zip('1234567')
            ->phoneNumber(
                TransactionTokenUpdateRequestDataPhoneNumberBuilder::init()
                    ->countryCode('81')
                    ->localNumber('08000000000')
                    ->build()
            )
            ->build()
    )
    ->build();

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->updateTransactionToken(
    $storeId,
    $id,
    null,
    $body
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response

```
{
  "id": "11f11e85-e9e9-b198-b990-c3a715943241",
  "store_id": "11f0e274-1e3b-4752-9513-33d3e07ede13",
  "email": "test@test.com",
  "payment_type": "card",
  "active": true,
  "mode": "live",
  "type": "recurring",
  "usage_limit": null,
  "confirmed": null,
  "metadata": {
    "univapay-link-id": "11f11e85-1b45-dace-bf3d-cbcae52f65fc",
    "univapay-name": "test",
    "univapay-phone-number": "+81 08012341234",
    "items": [
      "productName: Cherry Ice Sandwich, price: 3080, quantity: 1",
      "productName: Shipping, price: 200, quantity: 1"
    ],
    "order_no": 1,
    "note": null
  },
  "created_on": "2026-03-13T02:39:52.908468Z",
  "updated_on": "2026-03-13T02:39:52.908468Z",
  "last_used_on": null,
  "data": {
    "card": {
      "cardholder": "TEST TEST",
      "exp_month": 9,
      "exp_year": 2026,
      "card_bin": "424242",
      "last_four": "424242",
      "brand": "visa",
      "card_type": "credit",
      "country": "JP",
      "category": "standard",
      "issuer": "issuer",
      "sub_brand": "none"
    },
    "billing": {
      "line1": null,
      "line2": null,
      "state": null,
      "city": null,
      "country": null,
      "zip": null,
      "phone_number": {
        "country_code": 81,
        "local_number": "08012341234"
      }
    },
    "cvv_authorize": {
      "enabled": false,
      "status": null,
      "charge_id": null,
      "credentials_id": null,
      "currency": null
    },
    "cvv_authorize_check": {
      "status": null,
      "charge_id": null,
      "date": null
    },
    "three_ds": {
      "enabled": true,
      "status": "pending",
      "redirect_endpoint": "https://univapay.com/redirect/index.html",
      "error": null,
      "exempted": false
    }
  }
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Delete Transaction Token

Deletes a specific transaction token.
⚠️ **WARNING: Breaks Linked Subscriptions**
Please note that deleting a transaction token will immediately prevent any linked recurring charges or subscriptions from being processed. Proceed with caution.

```php
function deleteTransactionToken(string $storeId, string $id): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |

## Response Type

**204**: Token successfully deleted. No content.

This method returns an [`ApiResponse`](../../doc/api-response.md) instance.

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->deleteTransactionToken(
    $storeId,
    $id
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'void:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Enable Token Three Ds

Enables 3-D Secure on an existing `recurring` transaction token that was created without it. Only applies to `recurring` tokens; returns an error if 3DS is already enabled. After calling this endpoint, poll the token until `data.three_ds.status` becomes `awaiting`, then use the token 3DS issuer token endpoint to complete authentication.

```php
function enableTokenThreeDs(
    string $storeId,
    string $id,
    ?string $idempotencyKey = null,
    ?EnableTokenThreeDsRequest $body = null
): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |
| `idempotencyKey` | `?string` | Header, Optional | An optional idempotency key to prevent double charges and duplicate operations. We recommend a randomly generated UUID (v4). |
| `body` | [`?EnableTokenThreeDsRequest`](../../doc/models/enable-token-three-ds-request.md) | Body, Optional | Optional request payload. Omit entirely, or omit `redirect_endpoint`, if no redirect is needed. |

## Response Type

**200**: 3DS enabled successfully. Returns the updated token.

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type `CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken`.

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$body = EnableTokenThreeDsRequestBuilder::init()
    ->redirectEndpoint('https://univapay.com/3ds-redirect')
    ->build();

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->enableTokenThreeDs(
    $storeId,
    $id,
    null,
    $body
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response

```
{
  "id": "11f11e85-e9e9-b198-b990-c3a715943241",
  "store_id": "11f0e274-1e3b-4752-9513-33d3e07ede13",
  "email": "test@test.com",
  "payment_type": "card",
  "active": true,
  "mode": "live",
  "type": "recurring",
  "usage_limit": null,
  "confirmed": null,
  "metadata": {
    "univapay-link-id": "11f11e85-1b45-dace-bf3d-cbcae52f65fc",
    "univapay-name": "test",
    "univapay-phone-number": "+81 08012341234",
    "items": [
      "productName: Cherry Ice Sandwich, price: 3080, quantity: 1",
      "productName: Shipping, price: 200, quantity: 1"
    ],
    "order_no": 1,
    "note": null
  },
  "created_on": "2026-03-13T02:39:52.908468Z",
  "updated_on": "2026-03-13T02:39:52.908468Z",
  "last_used_on": null,
  "data": {
    "card": {
      "cardholder": "TEST TEST",
      "exp_month": 9,
      "exp_year": 2026,
      "card_bin": "424242",
      "last_four": "424242",
      "brand": "visa",
      "card_type": "credit",
      "country": "JP",
      "category": "standard",
      "issuer": "issuer",
      "sub_brand": "none"
    },
    "billing": {
      "line1": null,
      "line2": null,
      "state": null,
      "city": null,
      "country": null,
      "zip": null,
      "phone_number": {
        "country_code": 81,
        "local_number": "08012341234"
      }
    },
    "cvv_authorize": {
      "enabled": false,
      "status": null,
      "charge_id": null,
      "credentials_id": null,
      "currency": null
    },
    "cvv_authorize_check": {
      "status": null,
      "charge_id": null,
      "date": null
    },
    "three_ds": {
      "enabled": true,
      "status": "pending",
      "redirect_endpoint": "https://univapay.com/redirect/index.html",
      "error": null,
      "exempted": false
    }
  }
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Disable Token Three Ds

Disables 3-D Secure on an existing `recurring` transaction token. Only applies to `recurring` tokens.

```php
function disableTokenThreeDs(string $storeId, string $id): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |

## Response Type

**200**: 3DS disabled successfully. Returns the updated token.

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type `CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken`.

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->disableTokenThreeDs(
    $storeId,
    $id
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'CardTransactionToken|KonbiniTransactionToken|OnlineTransactionToken|BankTransferTransactionToken|PaidyTransactionToken|QrScanTransactionToken|QrMerchantTransactionToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response

```
{
  "id": "11f11e85-e9e9-b198-b990-c3a715943241",
  "store_id": "11f0e274-1e3b-4752-9513-33d3e07ede13",
  "email": "test@test.com",
  "payment_type": "card",
  "active": true,
  "mode": "live",
  "type": "recurring",
  "usage_limit": null,
  "confirmed": null,
  "metadata": {
    "univapay-link-id": "11f11e85-1b45-dace-bf3d-cbcae52f65fc",
    "univapay-name": "test",
    "univapay-phone-number": "+81 08012341234",
    "items": [
      "productName: Cherry Ice Sandwich, price: 3080, quantity: 1",
      "productName: Shipping, price: 200, quantity: 1"
    ],
    "order_no": 1,
    "note": null
  },
  "created_on": "2026-03-13T02:39:52.908468Z",
  "updated_on": "2026-03-13T02:39:52.908468Z",
  "last_used_on": null,
  "data": {
    "card": {
      "cardholder": "TEST TEST",
      "exp_month": 9,
      "exp_year": 2026,
      "card_bin": "424242",
      "last_four": "424242",
      "brand": "visa",
      "card_type": "credit",
      "country": "JP",
      "category": "standard",
      "issuer": "issuer",
      "sub_brand": "none"
    },
    "billing": {
      "line1": null,
      "line2": null,
      "state": null,
      "city": null,
      "country": null,
      "zip": null,
      "phone_number": {
        "country_code": 81,
        "local_number": "08012341234"
      }
    },
    "cvv_authorize": {
      "enabled": false,
      "status": null,
      "charge_id": null,
      "credentials_id": null,
      "currency": null
    },
    "cvv_authorize_check": {
      "status": null,
      "charge_id": null,
      "date": null
    },
    "three_ds": {
      "enabled": true,
      "status": "pending",
      "redirect_endpoint": "https://univapay.com/redirect/index.html",
      "error": null,
      "exempted": false
    }
  }
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |


# Get Token Three Ds Issuer Token

Retrieves the information required to execute 3-D Secure authentication when creating a recurring transaction token.
**⚠️ Important Notes:** 1. **PCI DSS Compliance:** This endpoint is only available to PCI DSS compliant merchants who are authorized to send raw card data directly via the API to create tokens. 2. **Target Tokens:** This only applies to tokens where `type` is `recurring`. For `one_time` or `subscription` tokens, 3-D Secure is requested during charge creation, not token creation. 3. **Execution Flow:**

- After creating the token, poll the token object until `data.three_ds.status` becomes `awaiting`.
- Once `awaiting`, use this endpoint to fetch the issuer token details.
- Format the returned `payload` according to the `content_type` (e.g., URL-encoded) and execute an `http_post` request from the consumer's browser to the `issuer_token` URL.

```php
function getTokenThreeDsIssuerToken(string $storeId, string $id): ApiResponse
```

## Authentication

This endpoint requires [JWT_TOKEN](../../doc/auth/oauth-2-bearer-token.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `storeId` | `string` | Template, Required | The unique identifier of the store. |
| `id` | `string` | Template, Required | The unique identifier of the resource. |

## Response Type

**200**: 3-D Secure authentication details retrieved successfully.

This method returns an [`ApiResponse`](../../doc/api-response.md) instance. The `getResult()` method on this instance returns the response data which is of type [`ThreeDsIssuerToken`](../../doc/models/three-ds-issuer-token.md).

## Example Usage

```php
$storeId = '0cab399b-5621-425b-993b-f8507eba1e78';

$id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

$transactionTokensApi = $client->getTransactionTokensApi();
$apiResponse = $transactionTokensApi->getTokenThreeDsIssuerToken(
    $storeId,
    $id
);

// Extracting response status code
var_dump($apiResponse->getStatusCode());
// Extracting response headers
var_dump($apiResponse->getHeaders());

if ($apiResponse->isSuccess()) {
    echo 'ThreeDsIssuerToken:';
    var_dump($apiResponse->getResult());
} else {
    $error = $apiResponse->getResult();
    var_dump($error);
}
```

## Example Response *(as JSON)*

```json
{
  "issuer_token": "http://test.com/action",
  "call_method": "http_post",
  "payload": {
    "request_data": "example_value"
  },
  "payment_type": "card",
  "content_type": "application/x-www-form-urlencoded; charset=UTF-8"
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | Bad Request (400). The request was invalid or could not be processed.  Common codes: VALIDATION_ERROR, INVALID_TOKEN_TYPE, NOT_SUPPORTED_BY_PROCESSOR. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Unauthorized (401). Authentication failed.  Common codes: AUTH_HEADER_MISSING, INVALID_APP_TOKEN, INVALID_CREDENTIALS. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 403 | Forbidden (403). The request is understood, but access is refused.  This occurs if permissions are insufficient or if a security lock is triggered. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | Not Found (404). The requested resource (e.g., Store ID or Token ID) does not exist. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Too Many Requests (429). Rate limit exceeded. Returns an empty JSON object in this spec. | `ApiException` |

