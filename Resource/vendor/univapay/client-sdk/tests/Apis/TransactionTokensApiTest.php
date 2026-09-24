<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay\Tests\Apis;

use Core\TestCase\BodyMatchers\KeysBodyMatcher;
use Core\TestCase\TestParam;
use UnivaPay\Apis\TransactionTokensApi;
use UnivaPay\Models;

class TransactionTokensApiTest extends BaseTestController
{
    /**
     * @var TransactionTokensApi TransactionTokensApi instance
     */
    protected static $controller;

    /**
     * Setup test class
     */
    public static function setUpBeforeClass(): void
    {
        self::$controller = parent::getClient()->getTransactionTokensApi();
    }

    public function testCreateTransactionToken()
    {
        // Parameters for the API call
        $body = TestParam::object(
            '{"payment_type":"card","type":"recurring","email":"test@univapay.com","metadata":{' .
            '"univapay-phone-number":"+81 08012341234"},"data":{"cardholder":"TEST TEST","card_n' .
            'umber":"4242424242424242","exp_month":"09","exp_year":"26","cvv":"123","phone_numbe' .
            'r":{"country_code":"81","local_number":"08012341234"},"three_ds":{"redirect_endpoin' .
            't":"https://univapay.com/redirect/index.html"},"cvv_authorize":{"enabled":false,"cu' .
            'rrency":"JPY"}}}',
            Models\TransactionTokenCreateRequest::class
        );
        $idempotencyKey = 'f64be872-353d-4c3c-84cb-3dc617fe89f7';

        // Perform API call
        $result = self::$controller->createTransactionToken($body, $idempotencyKey)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(201)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"id":"11f11e85-e9e9-b198-b990-c3a715943241","store_id":"11f0e274-1e3b-4752-95' .
                '13-33d3e07ede13","email":"test@test.com","payment_type":"card","active":true,"m' .
                'ode":"live","type":"recurring","usage_limit":null,"confirmed":null,"metadata":{' .
                '"univapay-link-id":"11f11e85-1b45-dace-bf3d-cbcae52f65fc","univapay-name":"test' .
                '","univapay-phone-number":"+81 08012341234","items":["productName: Cherry Ice S' .
                'andwich, price: 3080, quantity: 1","productName: Shipping, price: 200, quantity' .
                ': 1"],"order_no":1,"note":null},"created_on":"2026-03-13T02:39:52.908468Z","upd' .
                'ated_on":"2026-03-13T02:39:52.908468Z","last_used_on":null,"data":{"card":{"car' .
                'dholder":"TEST TEST","exp_month":9,"exp_year":2026,"card_bin":"424242","last_fo' .
                'ur":"424242","brand":"visa","card_type":"credit","country":"JP","category":"sta' .
                'ndard","issuer":"issuer","sub_brand":"none"},"billing":{"line1":null,"line2":nu' .
                'll,"state":null,"city":null,"country":null,"zip":null,"phone_number":{"country_' .
                'code":81,"local_number":"08012341234"}},"cvv_authorize":{"enabled":false,"statu' .
                's":null,"charge_id":null,"credentials_id":null,"currency":null},"cvv_authorize_' .
                'check":{"status":null,"charge_id":null,"date":null},"three_ds":{"enabled":true,' .
                '"status":"pending","redirect_endpoint":"https://univapay.com/redirect/index.htm' .
                'l","error":null,"exempted":false}}}'
            )))
            ->assert();
    }

    public function testListAllTransactionTokens()
    {
        // Parameters for the API call
        $search = 'tokyo';
        $customerId = '8a3f1b8e-2c1a-4b7a-9c2e-6f6b6f6e2b10';
        $type = Models\TransactionTokenListType::RECURRING;
        $mode = Models\ModeQuery::LIVE;
        $active = Models\TransactionTokenActiveFilter::ACTIVE;
        $limit = 10;
        $cursor = '3541d4fa-596d-428e-8a36-f274e1b3d505';
        $cursorDirection =
            Models\CursorDirectionQuery::DESC;

        // Perform API call
        $result = self::$controller->listAllTransactionTokens(
            $search,
            $customerId,
            $type,
            $mode,
            $active,
            $limit,
            $cursor,
            $cursorDirection
        )->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"items":[{"id":"2fe23e45-f95d-4c95-9963-739070096443","store_id":"79e9504e-96' .
                'd8-46ed-8d22-2e8b36238605","merchant_name":"Test Merchant","store_name":"Tokyo ' .
                'Store","email":"taro@example.com","payment_type":"card","active":true,"mode":"l' .
                'ive","type":"recurring","created_on":"2026-04-09T07:35:50Z","updated_on":"2026-' .
                '04-09T07:35:50Z","user_data":{"cardholder_name":"TARO YAMADA","email":"taro@exa' .
                'mple.com"}},{"id":"3af34f56-a06e-4d06-aa74-84a181107554","store_id":"8bfa615f-a' .
                '7e9-47fe-9e33-3f9c47349716","merchant_name":"Test Merchant","store_name":"Osaka' .
                ' Store","email":"hanako@example.com","payment_type":"card","active":true,"mode"' .
                ':"live","type":"one_time","created_on":"2026-04-10T10:20:11Z","updated_on":"202' .
                '6-04-10T10:20:11Z","user_data":{"cardholder_name":"HANAKO SUZUKI","email":"hana' .
                'ko@example.com"}},{"id":"4bf45e67-b17f-4e17-bb85-95b292218665","store_id":"79e9' .
                '504e-96d8-46ed-8d22-2e8b36238605","merchant_name":"Test Merchant","store_name":' .
                '"Tokyo Store","email":"jiro@example.com","payment_type":"card","active":false,"' .
                'mode":"live","type":"subscription","created_on":"2026-04-11T18:05:42Z","updated' .
                '_on":"2026-04-12T08:31:09Z","user_data":{"cardholder_name":"JIRO TANAKA","email' .
                '":"jiro@example.com"}}],"has_more":false,"total_hits":3}'
            )))
            ->assert();
    }

    public function testListStoreTransactionTokens()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $search = 'tokyo';
        $customerId = '8a3f1b8e-2c1a-4b7a-9c2e-6f6b6f6e2b10';
        $type = Models\TransactionTokenListType::RECURRING;
        $mode = Models\ModeQuery::LIVE;
        $active = Models\TransactionTokenActiveFilter::ACTIVE;
        $limit = 10;
        $cursor = '3541d4fa-596d-428e-8a36-f274e1b3d505';
        $cursorDirection =
            Models\CursorDirectionQuery::DESC;

        // Perform API call
        $result = self::$controller->listStoreTransactionTokens(
            $storeId,
            $search,
            $customerId,
            $type,
            $mode,
            $active,
            $limit,
            $cursor,
            $cursorDirection
        )->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"items":[{"id":"2fe23e45-f95d-4c95-9963-739070096443","store_id":"79e9504e-96' .
                'd8-46ed-8d22-2e8b36238605","merchant_name":"Test Merchant","store_name":"Tokyo ' .
                'Store","email":"taro@example.com","payment_type":"card","active":true,"mode":"l' .
                'ive","type":"recurring","created_on":"2026-04-09T07:35:50Z","updated_on":"2026-' .
                '04-09T07:35:50Z","user_data":{"cardholder_name":"TARO YAMADA","email":"taro@exa' .
                'mple.com"}},{"id":"5cf56e78-c28a-4f28-cc96-06c303329776","store_id":"79e9504e-9' .
                '6d8-46ed-8d22-2e8b36238605","merchant_name":"Test Merchant","store_name":"Tokyo' .
                ' Store","email":"saburo@example.com","payment_type":"card","active":true,"mode"' .
                ':"live","type":"one_time","created_on":"2026-04-10T12:14:00Z","updated_on":"202' .
                '6-04-10T12:14:00Z","user_data":{"cardholder_name":"SABURO KATO","email":"saburo' .
                '@example.com"}},{"id":"6df67e89-d39a-4039-dd07-17d414430887","store_id":"79e950' .
                '4e-96d8-46ed-8d22-2e8b36238605","merchant_name":"Test Merchant","store_name":"T' .
                'okyo Store","email":"shiro@example.com","payment_type":"card","active":true,"mo' .
                'de":"live","type":"subscription","created_on":"2026-04-11T16:48:23Z","updated_o' .
                'n":"2026-04-11T16:48:23Z","user_data":{"cardholder_name":"SHIRO ITO","email":"s' .
                'hiro@example.com"}}],"has_more":false,"total_hits":3}'
            )))
            ->assert();
    }

    public function testGetTransactionToken()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';
        $polling = true;

        // Perform API call
        $result = self::$controller->getTransactionToken($storeId, $id, $polling)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"id":"11f11e85-e9e9-b198-b990-c3a715943241","store_id":"11f0e274-1e3b-4752-95' .
                '13-33d3e07ede13","email":"test@test.com","payment_type":"card","active":true,"m' .
                'ode":"live","type":"recurring","usage_limit":null,"confirmed":null,"metadata":{' .
                '"univapay-link-id":"11f11e85-1b45-dace-bf3d-cbcae52f65fc","univapay-name":"test' .
                '","univapay-phone-number":"+81 08012341234","items":["productName: Cherry Ice S' .
                'andwich, price: 3080, quantity: 1","productName: Shipping, price: 200, quantity' .
                ': 1"],"order_no":1,"note":null},"created_on":"2026-03-13T02:39:52.908468Z","upd' .
                'ated_on":"2026-03-13T02:39:52.908468Z","last_used_on":null,"data":{"card":{"car' .
                'dholder":"TEST TEST","exp_month":9,"exp_year":2026,"card_bin":"424242","last_fo' .
                'ur":"424242","brand":"visa","card_type":"credit","country":"JP","category":"sta' .
                'ndard","issuer":"issuer","sub_brand":"none"},"billing":{"line1":null,"line2":nu' .
                'll,"state":null,"city":null,"country":null,"zip":null,"phone_number":{"country_' .
                'code":81,"local_number":"08012341234"}},"cvv_authorize":{"enabled":false,"statu' .
                's":null,"charge_id":null,"credentials_id":null,"currency":null},"cvv_authorize_' .
                'check":{"status":null,"charge_id":null,"date":null},"three_ds":{"enabled":true,' .
                '"status":"pending","redirect_endpoint":"https://univapay.com/redirect/index.htm' .
                'l","error":null,"exempted":false}}}'
            )))
            ->assert();
    }

    public function testUpdateTransactionToken()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';
        $idempotencyKey = 'f64be872-353d-4c3c-84cb-3dc617fe89f7';
        $body = TestParam::object(
            '{"email":"test.update@test.com","data":{"cardholder":"TARO YAMADA","card_number":"' .
            '4000020000000000","exp_month":12,"exp_year":2099,"cvv":"123","line1":"11111","line2' .
            '":"222","state":"Tokyo","city":"テスト区一丁目","country":"JP","zip":"1234567","phone_numb' .
            'er":{"country_code":"81","local_number":"08000000000"}}}',
            Models\TransactionTokenUpdateRequest::class
        );

        // Perform API call
        $result = self::$controller->updateTransactionToken($storeId, $id, $idempotencyKey, $body)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"id":"11f11e85-e9e9-b198-b990-c3a715943241","store_id":"11f0e274-1e3b-4752-95' .
                '13-33d3e07ede13","email":"test@test.com","payment_type":"card","active":true,"m' .
                'ode":"live","type":"recurring","usage_limit":null,"confirmed":null,"metadata":{' .
                '"univapay-link-id":"11f11e85-1b45-dace-bf3d-cbcae52f65fc","univapay-name":"test' .
                '","univapay-phone-number":"+81 08012341234","items":["productName: Cherry Ice S' .
                'andwich, price: 3080, quantity: 1","productName: Shipping, price: 200, quantity' .
                ': 1"],"order_no":1,"note":null},"created_on":"2026-03-13T02:39:52.908468Z","upd' .
                'ated_on":"2026-03-13T02:39:52.908468Z","last_used_on":null,"data":{"card":{"car' .
                'dholder":"TEST TEST","exp_month":9,"exp_year":2026,"card_bin":"424242","last_fo' .
                'ur":"424242","brand":"visa","card_type":"credit","country":"JP","category":"sta' .
                'ndard","issuer":"issuer","sub_brand":"none"},"billing":{"line1":null,"line2":nu' .
                'll,"state":null,"city":null,"country":null,"zip":null,"phone_number":{"country_' .
                'code":81,"local_number":"08012341234"}},"cvv_authorize":{"enabled":false,"statu' .
                's":null,"charge_id":null,"credentials_id":null,"currency":null},"cvv_authorize_' .
                'check":{"status":null,"charge_id":null,"date":null},"three_ds":{"enabled":true,' .
                '"status":"pending","redirect_endpoint":"https://univapay.com/redirect/index.htm' .
                'l","error":null,"exempted":false}}}'
            )))
            ->assert();
    }

    public function testDeleteTransactionToken()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

        // Perform API call
        $result = self::$controller->deleteTransactionToken($storeId, $id)->getResult();

        // Assert result with expected response
        $this->newTestCase(null)->expectStatus(204)->assert();
    }

    public function testEnableTokenThreeDs()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';
        $idempotencyKey = 'f64be872-353d-4c3c-84cb-3dc617fe89f7';
        $body = TestParam::object(
            '{"redirect_endpoint":"https://univapay.com/3ds-redirect"}',
            Models\EnableTokenThreeDsRequest::class
        );

        // Perform API call
        $result = self::$controller->enableTokenThreeDs($storeId, $id, $idempotencyKey, $body)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"id":"11f11e85-e9e9-b198-b990-c3a715943241","store_id":"11f0e274-1e3b-4752-95' .
                '13-33d3e07ede13","email":"test@test.com","payment_type":"card","active":true,"m' .
                'ode":"live","type":"recurring","usage_limit":null,"confirmed":null,"metadata":{' .
                '"univapay-link-id":"11f11e85-1b45-dace-bf3d-cbcae52f65fc","univapay-name":"test' .
                '","univapay-phone-number":"+81 08012341234","items":["productName: Cherry Ice S' .
                'andwich, price: 3080, quantity: 1","productName: Shipping, price: 200, quantity' .
                ': 1"],"order_no":1,"note":null},"created_on":"2026-03-13T02:39:52.908468Z","upd' .
                'ated_on":"2026-03-13T02:39:52.908468Z","last_used_on":null,"data":{"card":{"car' .
                'dholder":"TEST TEST","exp_month":9,"exp_year":2026,"card_bin":"424242","last_fo' .
                'ur":"424242","brand":"visa","card_type":"credit","country":"JP","category":"sta' .
                'ndard","issuer":"issuer","sub_brand":"none"},"billing":{"line1":null,"line2":nu' .
                'll,"state":null,"city":null,"country":null,"zip":null,"phone_number":{"country_' .
                'code":81,"local_number":"08012341234"}},"cvv_authorize":{"enabled":false,"statu' .
                's":null,"charge_id":null,"credentials_id":null,"currency":null},"cvv_authorize_' .
                'check":{"status":null,"charge_id":null,"date":null},"three_ds":{"enabled":true,' .
                '"status":"pending","redirect_endpoint":"https://univapay.com/redirect/index.htm' .
                'l","error":null,"exempted":false}}}'
            )))
            ->assert();
    }

    public function testDisableTokenThreeDs()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

        // Perform API call
        $result = self::$controller->disableTokenThreeDs($storeId, $id)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"id":"11f11e85-e9e9-b198-b990-c3a715943241","store_id":"11f0e274-1e3b-4752-95' .
                '13-33d3e07ede13","email":"test@test.com","payment_type":"card","active":true,"m' .
                'ode":"live","type":"recurring","usage_limit":null,"confirmed":null,"metadata":{' .
                '"univapay-link-id":"11f11e85-1b45-dace-bf3d-cbcae52f65fc","univapay-name":"test' .
                '","univapay-phone-number":"+81 08012341234","items":["productName: Cherry Ice S' .
                'andwich, price: 3080, quantity: 1","productName: Shipping, price: 200, quantity' .
                ': 1"],"order_no":1,"note":null},"created_on":"2026-03-13T02:39:52.908468Z","upd' .
                'ated_on":"2026-03-13T02:39:52.908468Z","last_used_on":null,"data":{"card":{"car' .
                'dholder":"TEST TEST","exp_month":9,"exp_year":2026,"card_bin":"424242","last_fo' .
                'ur":"424242","brand":"visa","card_type":"credit","country":"JP","category":"sta' .
                'ndard","issuer":"issuer","sub_brand":"none"},"billing":{"line1":null,"line2":nu' .
                'll,"state":null,"city":null,"country":null,"zip":null,"phone_number":{"country_' .
                'code":81,"local_number":"08012341234"}},"cvv_authorize":{"enabled":false,"statu' .
                's":null,"charge_id":null,"credentials_id":null,"currency":null},"cvv_authorize_' .
                'check":{"status":null,"charge_id":null,"date":null},"three_ds":{"enabled":true,' .
                '"status":"pending","redirect_endpoint":"https://univapay.com/redirect/index.htm' .
                'l","error":null,"exempted":false}}}'
            )))
            ->assert();
    }

    public function testGetTokenThreeDsIssuerToken()
    {
        // Parameters for the API call
        $storeId = '0cab399b-5621-425b-993b-f8507eba1e78';
        $id = 'c4e87129-cad4-47fb-8ded-b4c0a4ae0dd4';

        // Perform API call
        $result = self::$controller->getTokenThreeDsIssuerToken($storeId, $id)->getResult();

        $headers = [];
        $headers['Content-Type'] = ['application/json', true];

        // Assert result with expected response
        $this->newTestCase($result)
            ->expectStatus(200)
            ->allowExtraHeaders()
            ->expectHeaders($headers)
            ->bodyMatcher(KeysBodyMatcher::init(TestParam::object(
                '{"issuer_token":"http://test.com/action","call_method":"http_post","payload":{' .
                '"request_data":"example_value"},"payment_type":"card","content_type":"applicati' .
                'on/x-www-form-urlencoded; charset=UTF-8"}'
            )))
            ->assert();
    }
}
