<?php

/*
 * Custom test (not auto-generated): pins the store-scoped convenience calls on
 * UnivapayClientSdkClient for Refunds, Cancels and Subscriptions -- the
 * shortcuts that read the store id from the configured App Token instead of
 * making the caller pass (and persist) one.
 *
 * $client->getCharge($chargeId) was the first of these and has its own file,
 * ClientGetChargeTest, which spells out the contract in prose. This file holds
 * that same contract to the other fifty shortcuts, and is written as one
 * table rather than twenty-six near-identical test methods so that a new
 * shortcut is one row, and so that no shortcut can quietly be given weaker
 * coverage than its neighbours.
 *
 * Two things are guarded, exactly as in ClientGetChargeTest.
 *
 * First the *guard*: when the configured token carries no usable `store_id`,
 * the call must fail before a request is built. Interpolating a missing id
 * would send /stores//... -- a confusing 4xx instead of a clear client-side
 * failure -- so the failure cases assert not just the throw but that the
 * controller was never reached.
 *
 * Second the *delegation*: on the happy path each shortcut must behave exactly
 * like its controller counterpart, forwarding every argument in order and
 * returning the response untouched. Anything else and a shortcut becomes a
 * second, subtly different way to move (or stop) money.
 *
 * listSubscriptionCharges is the one shortcut whose endpoint is scoped by both
 * a merchant and a store, so it is the only one that resolves two ids and the
 * only one with a merchant-guard case of its own.
 *
 * Everything is synthetic and offline -- no network, no real credential: the
 * generated controller is replaced with a double on the client's private field,
 * the same trick ClientGetChargeTest uses.
 *
 * The rows are wired up with a doc-comment @dataProvider rather than PHPUnit's
 * #[DataProvider] attribute. PHPUnit deprecates the doc-comment form, but
 * attributes are PHP 8.0+ syntax and composer.json still supports ^7.2 -- so the
 * attribute form would not parse for part of the supported range. The existing
 * tests make the same trade; revisit it package-wide, not here.
 *
 * This table mirrors clientStoreShortcuts.test.ts; keep the rows aligned when
 * porting to the remaining SDKs.
 */

namespace UnivaPay\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use UnivaPay\Apis\CancelsApi;
use UnivaPay\Apis\ChargesApi;
use UnivaPay\Apis\RefundsApi;
use UnivaPay\Apis\StoresApi;
use UnivaPay\Apis\SubscriptionsApi;
use UnivaPay\Apis\TransactionHistoryApi;
use UnivaPay\Apis\TransactionTokensApi;
use UnivaPay\Apis\WebhooksApi;
use UnivaPay\Authentication\BearerAuthCredentialsBuilder;
use UnivaPay\Http\ApiResponse;
use UnivaPay\Models\CancelCreateRequest;
use UnivaPay\Models\ChargeCaptureRequest;
use UnivaPay\Models\ChargeUpdateRequest;
use UnivaPay\Models\CustomsDeclarationCreateRequest;
use UnivaPay\Models\CreateCustomerIdRequest;
use UnivaPay\Models\CustomsDeclarationPatchRequest;
use UnivaPay\Models\EnableTokenThreeDsRequest;
use UnivaPay\Models\TransactionTokenUpdateRequest;
use UnivaPay\Models\WebhookCreateRequest;
use UnivaPay\Models\WebhookUpdateRequest;
use UnivaPay\Models\CancelUpdateRequest;
use UnivaPay\Models\RefundCreateRequest;
use UnivaPay\Models\RefundUpdateRequest;
use UnivaPay\Models\SubscriptionPatchPaymentRequest;
use UnivaPay\Models\SubscriptionPatchTokenRequest;
use UnivaPay\Models\SubscriptionScheduleSettings;
use UnivaPay\Models\SubscriptionSimulationRequest;
use UnivaPay\Models\SubscriptionSuspendRequest;
use UnivaPay\Models\SubscriptionUpdateRequest;
use UnivaPay\UnivapayClientSdkClient;
use UnivaPay\UnivapayClientSdkClientBuilder;

class ClientStoreShortcutsTest extends TestCase
{
    private const MERCHANT_ID = '11ec8e24-0ecf-2c5a-923c-331b915dc311';
    private const STORE_ID = '11ec8e24-b133-6c68-b54d-971717202e9b';
    private const CHARGE_ID = '11ec8e24-c5f5-6f2e-b9b0-1f4d3c6a9e10';
    private const REFUND_ID = '11ec8e24-d1a0-7b3f-8c21-2e5f4d7b0a22';
    private const CANCEL_ID = '11ec8e24-e2b1-8c40-9d32-3f605e8c1b33';
    private const SUBSCRIPTION_ID = '11ec8e24-f3c2-9d51-ae43-406f1f9d2c44';
    private const PAYMENT_ID = '11ec8e24-04d3-ae62-bf54-5170209e3d55';
    private const TRANSACTION_TOKEN_ID = '11ec8e24-15e4-bf73-c065-628131af4e66';
    private const CUSTOMS_ID = '11ec8e24-26f5-c084-d176-739242b05f77';
    private const LAST_FOUR = '4242';
    private const TOKEN_ID = '11ec8e24-3706-d195-e287-84a353c16088';
    private const WEBHOOK_ID = '11ec8e24-4817-e2a6-f398-95b464d27199';

    // Distinct sentinel values per parameter, so a shortcut that forwards its
    // arguments in the wrong order fails instead of coincidentally matching.
    private const LIMIT = 20;
    private const CURSOR = 'cursor-1';
    private const DIRECTION = 'asc';
    private const METADATA = 'metadata-query';
    private const SEARCH = 'search-term';
    private const STATUS = 'current';
    private const MODE = 'test';
    private const IDEMPOTENCY_KEY = 'idempotency-key-1';
    private const MAX_ATTEMPTS = 3;

    /** Builds a JWT carrying $claims. Header and signature are inert. */
    private static function jwt(array $claims): string
    {
        $encode = function (string $bytes): string {
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        };

        return $encode('{"alg":"HS256","typ":"JWT"}') . '.'
            . $encode(json_encode($claims)) . '.c2ln';
    }

    private static function storeToken(): string
    {
        return self::jwt([
            'merchant_id' => self::MERCHANT_ID,
            'store_id' => self::STORE_ID,
        ]);
    }

    private static function merchantToken(): string
    {
        return self::jwt(['merchant_id' => self::MERCHANT_ID]);
    }

    /** A store scope with no merchant -- only listSubscriptionCharges notices. */
    private static function storeOnlyToken(): string
    {
        return self::jwt(['store_id' => self::STORE_ID]);
    }

    private static function clientWith(?string $jwtToken): UnivapayClientSdkClient
    {
        $builder = UnivapayClientSdkClientBuilder::init();
        if ($jwtToken !== null) {
            $builder->bearerAuthCredentials(
                BearerAuthCredentialsBuilder::init('not-a-real-secret', $jwtToken)
            );
        }

        return $builder->build();
    }

    /**
     * Puts a double in place of the generated controller, so no HTTP is issued.
     *
     * @param class-string $api      The controller class to stand in for.
     * @param string       $property The client's private field holding it.
     */
    private function watch(
        UnivapayClientSdkClient $client,
        string $api,
        string $property
    ): MockObject {
        $double = $this->createMock($api);
        $field = new ReflectionProperty(UnivapayClientSdkClient::class, $property);
        // composer.json still supports PHP ^7.2, where private access must be
        // opened explicitly. The call became a no-op in 8.1 and deprecated in
        // 8.5, so it is made only where it is actually needed.
        if (PHP_VERSION_ID < 80100) {
            $field->setAccessible(true);
        }
        $field->setValue($client, $double);

        return $double;
    }

    /**
     * Every store-scoped shortcut, with the controller call it must produce.
     *
     * Each row is [label, controller class, client field, method, arguments the
     * shortcut is called with, arguments the controller must receive]. The
     * expected list always begins with STORE_ID -- the id the shortcut resolved
     * from the token and the caller never passed -- except
     * listSubscriptionCharges, whose endpoint is scoped by merchant *and* store.
     */
    public static function shortcuts(): array
    {
        // Bodies are inert: the controller is a double, so nothing serialises
        // them. Only their identity matters -- each must arrive at the
        // controller as the same object the caller handed the shortcut. Three of
        // these models take required constructor arguments, so they get minimal
        // valid ones rather than being faked.
        $refundBody = new RefundCreateRequest(1000, 'jpy');
        $refundPatch = new RefundUpdateRequest();
        $cancelBody = new CancelCreateRequest();
        $cancelPatch = new CancelUpdateRequest();
        $subscriptionPatch = new SubscriptionUpdateRequest();
        $simulationBody = new SubscriptionSimulationRequest(
            1000,
            'jpy',
            'card',
            new SubscriptionScheduleSettings()
        );
        $paymentPatch = new SubscriptionPatchPaymentRequest();
        $tokenPatch = new SubscriptionPatchTokenRequest(self::TRANSACTION_TOKEN_ID);
        $suspendBody = new SubscriptionSuspendRequest();
        $chargePatch = new ChargeUpdateRequest();
        $captureBody = new ChargeCaptureRequest();
        $customsBody = new CustomsDeclarationCreateRequest(
            'CN',
            'merchant-customs-1',
            'certificate-1',
            'certificate-name'
        );
        $customsPatch = new CustomsDeclarationPatchRequest('merchant-customs-2');
        $tokenUpdate = new TransactionTokenUpdateRequest();
        $enableThreeDs = new EnableTokenThreeDsRequest();
        $webhookCreate = new WebhookCreateRequest([], 'https://example.test/hook');
        $webhookUpdate = new WebhookUpdateRequest();
        $customerIdBody = new CreateCustomerIdRequest('customer-1');

        $rows = [
            // ── Refunds ──────────────────────────────────────────────────────
            ['listRefunds', RefundsApi::class, 'refunds', 'listRefunds',
                [self::CHARGE_ID, self::LIMIT, self::CURSOR, self::DIRECTION, self::METADATA],
                [self::STORE_ID, self::CHARGE_ID, self::LIMIT, self::CURSOR,
                    self::DIRECTION, self::METADATA]],
            ['createRefund', RefundsApi::class, 'refunds', 'createRefund',
                [self::CHARGE_ID, $refundBody, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::CHARGE_ID, $refundBody, self::IDEMPOTENCY_KEY]],
            ['getRefund', RefundsApi::class, 'refunds', 'getRefund',
                [self::CHARGE_ID, self::REFUND_ID, true],
                [self::STORE_ID, self::CHARGE_ID, self::REFUND_ID, true]],
            ['updateRefund', RefundsApi::class, 'refunds', 'updateRefund',
                [self::CHARGE_ID, self::REFUND_ID, $refundPatch, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::CHARGE_ID, self::REFUND_ID, $refundPatch,
                    self::IDEMPOTENCY_KEY]],
            ['pollRefund', RefundsApi::class, 'refunds', 'pollRefund',
                [self::CHARGE_ID, self::REFUND_ID, self::MAX_ATTEMPTS],
                [self::STORE_ID, self::CHARGE_ID, self::REFUND_ID, self::MAX_ATTEMPTS]],

            // ── Cancels ──────────────────────────────────────────────────────
            ['listCancels', CancelsApi::class, 'cancels', 'listCancels',
                [self::CHARGE_ID, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::STORE_ID, self::CHARGE_ID, self::LIMIT, self::CURSOR,
                    self::DIRECTION]],
            ['createCancel', CancelsApi::class, 'cancels', 'createCancel',
                [self::CHARGE_ID, self::IDEMPOTENCY_KEY, $cancelBody],
                [self::STORE_ID, self::CHARGE_ID, self::IDEMPOTENCY_KEY, $cancelBody]],
            ['getCancel', CancelsApi::class, 'cancels', 'getCancel',
                [self::CHARGE_ID, self::CANCEL_ID, true],
                [self::STORE_ID, self::CHARGE_ID, self::CANCEL_ID, true]],
            ['updateCancel', CancelsApi::class, 'cancels', 'updateCancel',
                [self::CHARGE_ID, self::CANCEL_ID, $cancelPatch, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::CHARGE_ID, self::CANCEL_ID, $cancelPatch,
                    self::IDEMPOTENCY_KEY]],
            ['pollCancel', CancelsApi::class, 'cancels', 'pollCancel',
                [self::CHARGE_ID, self::CANCEL_ID, self::MAX_ATTEMPTS],
                [self::STORE_ID, self::CHARGE_ID, self::CANCEL_ID, self::MAX_ATTEMPTS]],

            // ── Subscriptions ────────────────────────────────────────────────
            ['listStoreSubscriptions', SubscriptionsApi::class, 'subscriptions',
                'listStoreSubscriptions',
                [self::SEARCH, self::STATUS, self::MODE, self::LIMIT, self::CURSOR,
                    self::DIRECTION],
                [self::STORE_ID, self::SEARCH, self::STATUS, self::MODE, self::LIMIT,
                    self::CURSOR, self::DIRECTION]],
            ['simulateStoreSubscriptionPlan', SubscriptionsApi::class, 'subscriptions',
                'simulateStoreSubscriptionPlan',
                [self::IDEMPOTENCY_KEY, $simulationBody],
                [self::STORE_ID, self::IDEMPOTENCY_KEY, $simulationBody]],
            ['getSubscription', SubscriptionsApi::class, 'subscriptions', 'getSubscription',
                [self::SUBSCRIPTION_ID, true],
                [self::STORE_ID, self::SUBSCRIPTION_ID, true]],
            ['updateSubscription', SubscriptionsApi::class, 'subscriptions',
                'updateSubscription',
                [self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY, $subscriptionPatch],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY,
                    $subscriptionPatch]],
            ['cancelSubscription', SubscriptionsApi::class, 'subscriptions',
                'cancelSubscription',
                [self::SUBSCRIPTION_ID],
                [self::STORE_ID, self::SUBSCRIPTION_ID]],
            ['listSubscriptionPayments', SubscriptionsApi::class, 'subscriptions',
                'listSubscriptionPayments',
                [self::SUBSCRIPTION_ID, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::LIMIT, self::CURSOR,
                    self::DIRECTION]],
            ['getSubscriptionPayment', SubscriptionsApi::class, 'subscriptions',
                'getSubscriptionPayment',
                [self::SUBSCRIPTION_ID, self::PAYMENT_ID],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::PAYMENT_ID]],
            ['updateSubscriptionPayment', SubscriptionsApi::class, 'subscriptions',
                'updateSubscriptionPayment',
                [self::SUBSCRIPTION_ID, self::PAYMENT_ID, $paymentPatch,
                    self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::PAYMENT_ID, $paymentPatch,
                    self::IDEMPOTENCY_KEY]],
            ['getSubscriptionLatestCharge', SubscriptionsApi::class, 'subscriptions',
                'getSubscriptionLatestCharge',
                [self::SUBSCRIPTION_ID],
                [self::STORE_ID, self::SUBSCRIPTION_ID]],
            // The only endpoint scoped by merchant *and* store.
            ['listSubscriptionCharges', SubscriptionsApi::class, 'subscriptions',
                'listSubscriptionCharges',
                [self::SUBSCRIPTION_ID, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::MERCHANT_ID, self::STORE_ID, self::SUBSCRIPTION_ID, self::LIMIT,
                    self::CURSOR, self::DIRECTION]],
            ['listChargesForSubscriptionPayment', SubscriptionsApi::class, 'subscriptions',
                'listChargesForSubscriptionPayment',
                [self::SUBSCRIPTION_ID, self::PAYMENT_ID, self::LIMIT, self::CURSOR,
                    self::DIRECTION],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::PAYMENT_ID, self::LIMIT,
                    self::CURSOR, self::DIRECTION]],
            ['suspendSubscription', SubscriptionsApi::class, 'subscriptions',
                'suspendSubscription',
                [self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY, $suspendBody],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY,
                    $suspendBody]],
            ['unsuspendSubscription', SubscriptionsApi::class, 'subscriptions',
                'unsuspendSubscription',
                [self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::IDEMPOTENCY_KEY]],
            ['updateSubscriptionToken', SubscriptionsApi::class, 'subscriptions',
                'updateSubscriptionToken',
                [self::SUBSCRIPTION_ID, $tokenPatch, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::SUBSCRIPTION_ID, $tokenPatch,
                    self::IDEMPOTENCY_KEY]],
            ['pollSubscription', SubscriptionsApi::class, 'subscriptions',
                'pollSubscription',
                [self::SUBSCRIPTION_ID, self::MAX_ATTEMPTS],
                [self::STORE_ID, self::SUBSCRIPTION_ID, self::MAX_ATTEMPTS]],

            // ── Charges ──────────────────────────────────────────────────────
            // Charges are otherwise out of scope here -- getCharge already had
            // its shortcut -- but leaving pollCharge as the one poll helper
            // without one would be a gap callers would trip over.
            // -- Charges (remaining store-scoped operations) ------------------
            ['listStoreCharges', ChargesApi::class, 'charges', 'listStoreCharges',
                [self::LIMIT, self::CURSOR, self::DIRECTION, self::LAST_FOUR],
                [self::STORE_ID, self::LIMIT, self::CURSOR, self::DIRECTION,
                    self::LAST_FOUR, null, null, null, null, null, null, null,
                    null, null, null, null, null, null]],
            ['updateCharge', ChargesApi::class, 'charges', 'updateCharge',
                [self::CHARGE_ID, self::IDEMPOTENCY_KEY, $chargePatch],
                [self::STORE_ID, self::CHARGE_ID, self::IDEMPOTENCY_KEY, $chargePatch]],
            ['captureCharge', ChargesApi::class, 'charges', 'captureCharge',
                [self::CHARGE_ID, self::IDEMPOTENCY_KEY, $captureBody],
                [self::STORE_ID, self::CHARGE_ID, self::IDEMPOTENCY_KEY, $captureBody]],
            ['getChargeIssuerToken', ChargesApi::class, 'charges', 'getChargeIssuerToken',
                [self::CHARGE_ID],
                [self::STORE_ID, self::CHARGE_ID]],
            ['getChargeThreeDsIssuerToken', ChargesApi::class, 'charges',
                'getChargeThreeDsIssuerToken',
                [self::CHARGE_ID],
                [self::STORE_ID, self::CHARGE_ID]],
            ['listBankTransferLedgers', ChargesApi::class, 'charges',
                'listBankTransferLedgers',
                [self::CHARGE_ID],
                [self::STORE_ID, self::CHARGE_ID]],
            ['createCustomsDeclaration', ChargesApi::class, 'charges',
                'createCustomsDeclaration',
                [self::CHARGE_ID, $customsBody, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::CHARGE_ID, $customsBody, self::IDEMPOTENCY_KEY]],
            ['getCustomsDeclaration', ChargesApi::class, 'charges', 'getCustomsDeclaration',
                [self::CHARGE_ID, self::CUSTOMS_ID, true],
                [self::STORE_ID, self::CHARGE_ID, self::CUSTOMS_ID, true]],
            ['patchCustomsDeclaration', ChargesApi::class, 'charges',
                'patchCustomsDeclaration',
                [self::CHARGE_ID, self::CUSTOMS_ID, $customsPatch, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::CHARGE_ID, self::CUSTOMS_ID, $customsPatch,
                    self::IDEMPOTENCY_KEY]],

            ['listStoreTransactionTokens', TransactionTokensApi::class, 'transactionTokens', 'listStoreTransactionTokens',
                [self::SEARCH, null, null, null, null, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::STORE_ID, self::SEARCH, null, null, null, null, self::LIMIT, self::CURSOR, self::DIRECTION]],
            ['getTransactionToken', TransactionTokensApi::class, 'transactionTokens', 'getTransactionToken',
                [self::TOKEN_ID, true],
                [self::STORE_ID, self::TOKEN_ID, true]],
            ['updateTransactionToken', TransactionTokensApi::class, 'transactionTokens', 'updateTransactionToken',
                [self::TOKEN_ID, self::IDEMPOTENCY_KEY, $tokenUpdate],
                [self::STORE_ID, self::TOKEN_ID, self::IDEMPOTENCY_KEY, $tokenUpdate]],
            ['deleteTransactionToken', TransactionTokensApi::class, 'transactionTokens', 'deleteTransactionToken',
                [self::TOKEN_ID],
                [self::STORE_ID, self::TOKEN_ID]],
            ['enableTokenThreeDs', TransactionTokensApi::class, 'transactionTokens', 'enableTokenThreeDs',
                [self::TOKEN_ID, self::IDEMPOTENCY_KEY, $enableThreeDs],
                [self::STORE_ID, self::TOKEN_ID, self::IDEMPOTENCY_KEY, $enableThreeDs]],
            ['disableTokenThreeDs', TransactionTokensApi::class, 'transactionTokens', 'disableTokenThreeDs',
                [self::TOKEN_ID],
                [self::STORE_ID, self::TOKEN_ID]],
            ['getTokenThreeDsIssuerToken', TransactionTokensApi::class, 'transactionTokens', 'getTokenThreeDsIssuerToken',
                [self::TOKEN_ID],
                [self::STORE_ID, self::TOKEN_ID]],
            ['listWebhooks', WebhooksApi::class, 'webhooks', 'listWebhooks',
                [self::LIMIT, self::CURSOR, self::DIRECTION, null],
                [self::STORE_ID, self::LIMIT, self::CURSOR, self::DIRECTION, null]],
            ['createWebhook', WebhooksApi::class, 'webhooks', 'createWebhook',
                [$webhookCreate, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, $webhookCreate, self::IDEMPOTENCY_KEY]],
            ['getWebhook', WebhooksApi::class, 'webhooks', 'getWebhook',
                [self::WEBHOOK_ID],
                [self::STORE_ID, self::WEBHOOK_ID]],
            ['updateWebhook', WebhooksApi::class, 'webhooks', 'updateWebhook',
                [self::WEBHOOK_ID, $webhookUpdate, self::IDEMPOTENCY_KEY],
                [self::STORE_ID, self::WEBHOOK_ID, $webhookUpdate, self::IDEMPOTENCY_KEY]],
            ['deleteWebhook', WebhooksApi::class, 'webhooks', 'deleteWebhook',
                [self::WEBHOOK_ID],
                [self::STORE_ID, self::WEBHOOK_ID]],
            ['listWebhookEvents', WebhooksApi::class, 'webhooks', 'listWebhookEvents',
                [self::WEBHOOK_ID, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::STORE_ID, self::WEBHOOK_ID, self::LIMIT, self::CURSOR, self::DIRECTION]],
            ['listStoreTransactionHistory', TransactionHistoryApi::class, 'transactionHistory', 'listStoreTransactionHistory',
                [null, null, null, null, null, null, self::SEARCH, null, self::WEBHOOK_ID, null, null, null, null, null, null, null, null, null, null, null, null, null, null, self::LIMIT, self::CURSOR, self::DIRECTION],
                [self::STORE_ID, null, null, null, null, null, null, self::SEARCH, null, self::WEBHOOK_ID, null, null, null, null, null, null, null, null, null, null, null, null, null, null, self::LIMIT, self::CURSOR, self::DIRECTION]],
            ['createCustomerId', StoresApi::class, 'stores', 'createCustomerId',
                [$customerIdBody],
                [self::STORE_ID, $customerIdBody]],
            ['pollCharge', ChargesApi::class, 'charges', 'pollCharge',
                [self::CHARGE_ID, self::MAX_ATTEMPTS],
                [self::STORE_ID, self::CHARGE_ID, self::MAX_ATTEMPTS]],
        ];

        $named = [];
        foreach ($rows as $row) {
            $named[$row[0]] = $row;
        }

        return $named;
    }

    public function testCoversEveryShortcutExactlyOnce(): void
    {
        // Keyed by label, so a duplicated row would collapse rather than
        // silently halve another shortcut's coverage.
        $this->assertCount(50, self::shortcuts());
    }

    // ── The delegation: ids taken from the token ─────────────────────────────

    /**
     * @dataProvider shortcuts
     */
    public function testDelegatesWithTheIdsFromTheToken(
        string $label,
        string $api,
        string $property,
        string $method,
        array $args,
        array $expected
    ): void {
        $client = self::clientWith(self::storeToken());
        $response = $this->createMock(ApiResponse::class);
        $double = $this->watch($client, $api, $property);
        $double->expects($this->once())
            ->method($method)
            ->with(...$expected)
            ->willReturn($response);

        // Returned untouched: anything else and the shortcut would be a second,
        // subtly different way to perform the same operation.
        $this->assertSame($response, $client->{$label}(...$args));
    }

    // ── The guard: no usable store_id ────────────────────────────────────────

    /**
     * @dataProvider shortcuts
     */
    public function testRejectsATokenWithNoUsableStore(
        string $label,
        string $api,
        string $property,
        string $method,
        array $args
    ): void {
        // Both tokens carry a merchant, so every shortcut -- including the
        // two-guard one -- reaches the *store* guard.
        $tokens = [
            self::merchantToken(),
            self::jwt([
                'merchant_id' => self::MERCHANT_ID,
                'store_id' => 'store-1',
            ]),
        ];

        foreach ($tokens as $token) {
            $client = self::clientWith($token);
            $double = $this->watch($client, $api, $property);
            // The guard runs before the controller is even reached, so a missing
            // id can never be interpolated into /stores//... .
            $double->expects($this->never())->method($method);

            try {
                $client->{$label}(...$args);
                $this->fail($label . ' did not throw');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString(
                    'store-level App Token',
                    $e->getMessage()
                );
                // The guard builds every message from the caller's own
                // signature, so a shortcut that forgot to identify itself would
                // leave the user guessing which of fifty calls failed.
                $this->assertStringContainsString($label, $e->getMessage());
                $this->assertStringContainsString(
                    'with an explicit store id',
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * @dataProvider shortcuts
     */
    public function testRejectsATokenWithNoUsableIdsAtAll(
        string $label,
        string $api,
        string $property,
        string $method,
        array $args
    ): void {
        // No merchant either. listSubscriptionCharges checks the merchant first,
        // so for that one row this surfaces the merchant message rather than the
        // store one -- both are correct, and both must still fail before a
        // request is built.
        foreach (['not.a-jwt', null] as $token) {
            $client = self::clientWith($token);
            $double = $this->watch($client, $api, $property);
            $double->expects($this->never())->method($method);

            try {
                $client->{$label}(...$args);
                $this->fail($label . ' did not throw');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('App Token', $e->getMessage());
            }
        }
    }

    /**
     * @dataProvider shortcuts
     */
    public function testNeverPutsTheCredentialInTheMessage(
        string $label,
        string $api,
        string $property,
        string $method,
        array $args
    ): void {
        $client = self::clientWith(self::merchantToken());
        $this->watch($client, $api, $property);

        try {
            $client->{$label}(...$args);
            $this->fail($label . ' did not throw');
        } catch (RuntimeException $e) {
            // The credential and its claims must never reach an error message or
            // a log -- checked on every shortcut, not a sampled one, because each
            // is a separate call site that could have composed its own message.
            $message = $e->getMessage();
            $this->assertStringNotContainsString(self::merchantToken(), $message);
            $this->assertStringNotContainsString(self::MERCHANT_ID, $message);
            $this->assertStringNotContainsString(self::STORE_ID, $message);
        }
    }

    // ── The one shortcut scoped by merchant and store ────────────────────────

    public function testListSubscriptionChargesRejectsATokenWithNoMerchant(): void
    {
        $client = self::clientWith(self::storeOnlyToken());
        $double = $this->watch($client, SubscriptionsApi::class, 'subscriptions');
        $double->expects($this->never())->method('listSubscriptionCharges');

        try {
            $client->listSubscriptionCharges(self::SUBSCRIPTION_ID);
            $this->fail('did not throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('merchant', $e->getMessage());
            $this->assertStringNotContainsString(self::storeOnlyToken(), $e->getMessage());
            $this->assertStringNotContainsString(self::MERCHANT_ID, $e->getMessage());
            $this->assertStringNotContainsString(self::STORE_ID, $e->getMessage());
        }
    }

    public function testListSubscriptionChargesIsTheOnlyTwoGuardShortcut(): void
    {
        // If another shortcut ever starts expecting a merchant id, it needs the
        // merchant-guard case above too -- this keeps that honest.
        $withMerchant = [];
        foreach (self::shortcuts() as $label => $row) {
            if ($row[5][0] === self::MERCHANT_ID) {
                $withMerchant[] = $label;
            }
        }

        $this->assertSame(['listSubscriptionCharges'], $withMerchant);
    }
}
