<?php

declare(strict_types=1);

/*
 * UnivapayClientSdk
 *
 * This file was automatically generated for Univapay by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace UnivaPay;

use Core\ClientBuilder;
use Core\Request\Parameters\TemplateParam;
use Core\Utils\CoreHelper;
use Unirest\Configuration;
use Unirest\HttpClient;
use UnivaPay\Apis\CancelsApi;
use UnivaPay\Apis\ChargesApi;
use UnivaPay\Apis\CheckoutApi;
use UnivaPay\Apis\DirectDebitApi;
use UnivaPay\Apis\MerchantsApi;
use UnivaPay\Apis\RefundsApi;
use UnivaPay\Apis\StoresApi;
use UnivaPay\Apis\SubscriptionsApi;
use UnivaPay\Apis\TransactionHistoryApi;
use UnivaPay\Apis\TransactionTokensApi;
use UnivaPay\Apis\WebhooksApi;
use UnivaPay\Authentication\BearerAuthCredentialsBuilder;
use UnivaPay\Authentication\BearerAuthManager;
use UnivaPay\Http\ApiResponse;
use UnivaPay\Models\CancelCreateRequest;
use UnivaPay\Models\ChargeCaptureRequest;
use UnivaPay\Models\ChargeUpdateRequest;
use UnivaPay\Models\CancelUpdateRequest;
use UnivaPay\Models\CursorDirectionQuery;
use UnivaPay\Models\CustomsDeclarationCreateRequest;
use UnivaPay\Models\CustomsDeclarationPatchRequest;
use UnivaPay\Models\RefundCreateRequest;
use UnivaPay\Models\RefundUpdateRequest;
use UnivaPay\Models\SubscriptionPatchPaymentRequest;
use UnivaPay\Models\SubscriptionPatchTokenRequest;
use UnivaPay\Models\SubscriptionSimulationRequest;
use UnivaPay\Models\SubscriptionSuspendRequest;
use UnivaPay\Models\SubscriptionUpdateRequest;
use UnivaPay\Models\CreateCustomerIdRequest;
use UnivaPay\Models\EnableTokenThreeDsRequest;
use UnivaPay\Models\TransactionTokenUpdateRequest;
use UnivaPay\Models\WebhookCreateRequest;
use UnivaPay\Models\WebhookUpdateRequest;
use UnivaPay\Logging\LoggingConfigurationBuilder;
use UnivaPay\Logging\RequestLoggingConfigurationBuilder;
use UnivaPay\Logging\ResponseLoggingConfigurationBuilder;
use UnivaPay\Proxy\ProxyConfigurationBuilder;
use UnivaPay\Utils\CompatibilityConverter;

/**
 * Hand-authored customization — adds an Idempotency-Key header to mutating
 * requests. Kept above the generated class, where codegen never edits, so it
 * does not conflict on regeneration.
 */
class IdempotencyCallback extends \Core\Types\Sdk\CoreCallback
{
    private $userCallback;

    public function __construct(?\Core\Types\Sdk\CoreCallback $userCallback)
    {
        $this->userCallback = $userCallback;
        parent::__construct(
            [$this, 'onBeforeRequest'],
            [$this, 'onAfterRequest']
        );
    }

    private function injectIdempotencyHeader($request): void
    {
        if ($request !== null && method_exists($request, 'getHttpMethod') && method_exists($request, 'addHeader')) {
            $method = strtoupper($request->getHttpMethod());
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $headers = [];
                if (method_exists($request, 'getHeaders')) {
                    $headers = $request->getHeaders();
                }
                $hasIdempotency = false;
                foreach ($headers as $key => $val) {
                    if (strtolower($key) === 'idempotency-key') {
                        $hasIdempotency = true;
                        break;
                    }
                }
                if (!$hasIdempotency) {
                    $uuid = $this->generateUuidV4();
                    $request->addHeader('Idempotency-Key', $uuid);
                }
            }
        }
    }

    public function onBeforeRequest($request): void
    {
        $this->injectIdempotencyHeader($request);
        if ($this->userCallback !== null) {
            $this->userCallback->callOnBeforeRequest($request);
        }
    }

    public function callOnBeforeWithConversion(\CoreInterfaces\Core\Request\RequestInterface $request, \CoreInterfaces\Sdk\ConverterInterface $converter)
    {
        $this->injectIdempotencyHeader($request);
        if ($this->userCallback !== null) {
            $this->userCallback->callOnBeforeWithConversion($request, $converter);
        } else {
            parent::callOnBeforeWithConversion($request, $converter);
        }
    }

    public function onAfterRequest($context): void
    {
        if ($this->userCallback !== null) {
            $this->userCallback->callOnAfterRequest($context);
        }
    }

    public function callOnAfterWithConversion(\CoreInterfaces\Core\ContextInterface $context, \CoreInterfaces\Sdk\ConverterInterface $converter)
    {
        if ($this->userCallback !== null) {
            $this->userCallback->callOnAfterWithConversion($context, $converter);
        } else {
            parent::callOnAfterWithConversion($context, $converter);
        }
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

class UnivapayClientSdkClient implements ConfigurationInterface
{
    private $charges;

    private $transactionTokens;

    private $refunds;

    private $subscriptions;

    private $cancels;

    private $merchants;

    private $stores;

    private $webhooks;

    private $directDebit;

    private $checkout;

    private $transactionHistory;

    private $bearerAuthManager;

    private $loggingConfigurationBuilder;

    private $proxyConfiguration;

    private $config;

    private $client;

    /**
     * @see UnivapayClientSdkClientBuilder::init()
     * @see UnivapayClientSdkClientBuilder::build()
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(ConfigurationDefaults::_ALL, CoreHelper::clone($config));
        $userCallback = $this->config['httpCallback'] ?? null;
        if (!($userCallback instanceof IdempotencyCallback)) {
            $this->config['httpCallback'] = new IdempotencyCallback($userCallback);
        }
        $this->bearerAuthManager = new BearerAuthManager($this->config);
        $loggingConfiguration = null;
        if ($this->config['loggingConfiguration'] instanceof LoggingConfigurationBuilder) {
            $this->loggingConfigurationBuilder = $this->config['loggingConfiguration'];
            $loggingConfiguration = $this->loggingConfigurationBuilder->build();
        }
        $this->proxyConfiguration = $this->config['proxyConfiguration'] ?? ConfigurationDefaults::PROXY_CONFIGURATION;
        $this->client = ClientBuilder::init(
            new HttpClient(Configuration::init($this)->proxyConfiguration($this->proxyConfiguration))
        )
            ->converter(new CompatibilityConverter())
            ->jsonHelper(ApiHelper::getJsonHelper())
            ->apiCallback($this->config['httpCallback'] ?? null)
            ->userAgent('PHP-SDK/1.2.3 (OS: {os-info}, Engine: {engine}/{engine-version})')
            ->globalConfig($this->getGlobalConfiguration())
            ->serverUrls(self::ENVIRONMENT_MAP[$this->getEnvironment()], Server::DEFAULT_)
            ->authManagers(['JWT_TOKEN' => $this->bearerAuthManager])
            ->loggingConfiguration($loggingConfiguration)
            ->build();
    }

    /**
     * Create a builder with the current client's configurations.
     *
     * @return UnivapayClientSdkClientBuilder UnivapayClientSdkClientBuilder instance
     */
    public function toBuilder(): UnivapayClientSdkClientBuilder
    {
        $builder = UnivapayClientSdkClientBuilder::init()
            ->timeout($this->getTimeout())
            ->enableRetries($this->shouldEnableRetries())
            ->numberOfRetries($this->getNumberOfRetries())
            ->retryInterval($this->getRetryInterval())
            ->backOffFactor($this->getBackOffFactor())
            ->maximumRetryWaitTime($this->getMaximumRetryWaitTime())
            ->retryOnTimeout($this->shouldRetryOnTimeout())
            ->httpStatusCodesToRetry($this->getHttpStatusCodesToRetry())
            ->httpMethodsToRetry($this->getHttpMethodsToRetry())
            ->environment($this->getEnvironment())
            ->baseUrl($this->getBaseUrl())
            ->directDebitBaseUrl($this->getDirectDebitBaseUrl())
            ->httpCallback($this->config['httpCallback'] ?? null)
            ->proxyConfiguration($this->getProxyConfigurationBuilder());

        $bearerAuth = $this->getBearerAuthCredentialsBuilder();
        if ($bearerAuth != null) {
            $builder->bearerAuthCredentials($bearerAuth);
        }
        $loggingConfigurationBuilder = $this->getLoggingConfigurationBuilder();
        if ($loggingConfigurationBuilder != null) {
            $builder->loggingConfiguration($loggingConfigurationBuilder);
        }
        return $builder;
    }

    public function getTimeout(): int
    {
        return $this->config['timeout'] ?? ConfigurationDefaults::TIMEOUT;
    }

    public function shouldEnableRetries(): bool
    {
        return $this->config['enableRetries'] ?? ConfigurationDefaults::ENABLE_RETRIES;
    }

    public function getNumberOfRetries(): int
    {
        return $this->config['numberOfRetries'] ?? ConfigurationDefaults::NUMBER_OF_RETRIES;
    }

    public function getRetryInterval(): float
    {
        return $this->config['retryInterval'] ?? ConfigurationDefaults::RETRY_INTERVAL;
    }

    public function getBackOffFactor(): float
    {
        return $this->config['backOffFactor'] ?? ConfigurationDefaults::BACK_OFF_FACTOR;
    }

    public function getMaximumRetryWaitTime(): int
    {
        return $this->config['maximumRetryWaitTime'] ?? ConfigurationDefaults::MAXIMUM_RETRY_WAIT_TIME;
    }

    public function shouldRetryOnTimeout(): bool
    {
        return $this->config['retryOnTimeout'] ?? ConfigurationDefaults::RETRY_ON_TIMEOUT;
    }

    public function getHttpStatusCodesToRetry(): array
    {
        return $this->config['httpStatusCodesToRetry'] ?? ConfigurationDefaults::HTTP_STATUS_CODES_TO_RETRY;
    }

    public function getHttpMethodsToRetry(): array
    {
        return $this->config['httpMethodsToRetry'] ?? ConfigurationDefaults::HTTP_METHODS_TO_RETRY;
    }

    public function getEnvironment(): string
    {
        return $this->config['environment'] ?? ConfigurationDefaults::ENVIRONMENT;
    }

    public function getBaseUrl(): string
    {
        return $this->config['baseUrl'] ?? ConfigurationDefaults::BASE_URL;
    }

    public function getDirectDebitBaseUrl(): string
    {
        return $this->config['directDebitBaseUrl'] ?? ConfigurationDefaults::DIRECT_DEBIT_BASE_URL;
    }

    public function getBearerAuthCredentials(): BearerAuthCredentials
    {
        return $this->bearerAuthManager;
    }

    public function getBearerAuthCredentialsBuilder(): ?BearerAuthCredentialsBuilder
    {
        if (empty($this->bearerAuthManager->getSecretKey()) || empty($this->bearerAuthManager->getJwtToken())) {
            return null;
        }
        return BearerAuthCredentialsBuilder::init(
            $this->bearerAuthManager->getSecretKey(),
            $this->bearerAuthManager->getJwtToken()
        );
    }

    /**
     * The merchant this client's app token was issued for, decoded from the
     * configured JWT.
     *
     * Both merchant-level and store-level app tokens carry a merchant, so this
     * is set for either kind of token.
     *
     * @return string|null The merchant id as a UUID string, or null if no JWT is
     *         configured or its `merchant_id` claim is absent or not a UUID.
     */
    public function getCurrentMerchantId(): ?string
    {
        return AppJwt::readUuidClaim($this->bearerAuthManager->getJwtToken(), 'merchant_id');
    }

    /**
     * The store this client's app token was issued for, decoded from the
     * configured JWT.
     *
     * Only store-level app tokens are scoped to a store. A merchant-level token
     * carries no `store_id` claim, so this returns null for one -- use
     * `getStoresApi()` to list the merchant's stores instead.
     *
     * @return string|null The store id as a UUID string, or null if no JWT is
     *         configured or its `store_id` claim is absent or not a UUID.
     */
    public function getCurrentStoreId(): ?string
    {
        return AppJwt::readUuidClaim($this->bearerAuthManager->getJwtToken(), 'store_id');
    }

    /**
     * Retrieves a charge without being given a store id.
     *
     * `/stores/{storeId}/charges/{id}` needs a store, which callers would
     * otherwise have to persist alongside every charge id -- but a store-level
     * app token already carries one, so this reads it from the configured token
     * and then behaves exactly like ChargesApi::getCharge().
     *
     * @param string    $chargeId The unique identifier of the charge.
     * @param bool|null $polling  If true, instructs the API to internally poll
     *                            the charge status until it leaves 'pending'.
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built. Resolve the
     *                           store yourself (see getStoresApi()) and use
     *                           ChargesApi::getCharge() instead.
     */
    public function getCharge(string $chargeId, ?bool $polling = null): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling getChargesApi() inline would build a
        // controller even on the failure path.
        $storeId = AppJwt::requireStoreId(
            $this->getCurrentStoreId(),
            'getCharge(chargeId)',
            'getCharge(storeId, chargeId) on ChargesApi'
        );
        return $this->getChargesApi()->getCharge($storeId, $chargeId, $polling);
    }

    public function getLoggingConfigurationBuilder(): ?LoggingConfigurationBuilder
    {
        if (is_null($this->loggingConfigurationBuilder)) {
            return null;
        }
        $config = $this->loggingConfigurationBuilder->getConfiguration();
        return LoggingConfigurationBuilder::init()
            ->level($config['level'])
            ->logger($config['logger'])
            ->maskSensitiveHeaders($config['maskSensitiveHeaders'])
            ->requestConfiguration(RequestLoggingConfigurationBuilder::init()
                ->includeQueryInPath($config['requestConfiguration']['includeQueryInPath'])
                ->body($config['requestConfiguration']['body'])
                ->headers($config['requestConfiguration']['headers'])
                ->includeHeaders(...$config['requestConfiguration']['includeHeaders'])
                ->excludeHeaders(...$config['requestConfiguration']['excludeHeaders'])
                ->unmaskHeaders(...$config['requestConfiguration']['unmaskHeaders']))
            ->responseConfiguration(ResponseLoggingConfigurationBuilder::init()
                ->body($config['responseConfiguration']['body'])
                ->headers($config['responseConfiguration']['headers'])
                ->includeHeaders(...$config['responseConfiguration']['includeHeaders'])
                ->excludeHeaders(...$config['responseConfiguration']['excludeHeaders'])
                ->unmaskHeaders(...$config['responseConfiguration']['unmaskHeaders']));
    }

    /**
     * Get the proxy configuration builder
     */
    public function getProxyConfigurationBuilder(): ProxyConfigurationBuilder
    {
        return ProxyConfigurationBuilder::init($this->proxyConfiguration['address'])
            ->port($this->proxyConfiguration['port'])
            ->tunnel($this->proxyConfiguration['tunnel'])
            ->auth($this->proxyConfiguration['auth']['user'], $this->proxyConfiguration['auth']['pass'])
            ->authMethod($this->proxyConfiguration['auth']['method']);
    }

    /**
     * Get the client configuration as an associative array
     *
     * @see UnivapayClientSdkClientBuilder::getConfiguration()
     */
    public function getConfiguration(): array
    {
        return $this->toBuilder()->getConfiguration();
    }

    /**
     * Clone this client and override given configuration options
     *
     * @see UnivapayClientSdkClientBuilder::build()
     */
    public function withConfiguration(array $config): self
    {
        return new self(array_merge($this->config, $config));
    }

    /**
     * Get the base uri for a given server in the current environment.
     *
     * @param string $server Server name
     *
     * @return string Base URI
     */
    public function getBaseUri(string $server = Server::DEFAULT_): string
    {
        return $this->client->getGlobalRequest($server)->getQueryUrl();
    }

    /**
     * Returns Charges Api
     */
    public function getChargesApi(): ChargesApi
    {
        if ($this->charges == null) {
            $this->charges = new ChargesApi($this->client);
        }
        return $this->charges;
    }

    /**
     * Returns Transaction Tokens Api
     */
    public function getTransactionTokensApi(): TransactionTokensApi
    {
        if ($this->transactionTokens == null) {
            $this->transactionTokens = new TransactionTokensApi($this->client);
        }
        return $this->transactionTokens;
    }

    /**
     * Returns Refunds Api
     */
    public function getRefundsApi(): RefundsApi
    {
        if ($this->refunds == null) {
            $this->refunds = new RefundsApi($this->client);
        }
        return $this->refunds;
    }

    /**
     * Returns Subscriptions Api
     */
    public function getSubscriptionsApi(): SubscriptionsApi
    {
        if ($this->subscriptions == null) {
            $this->subscriptions = new SubscriptionsApi($this->client);
        }
        return $this->subscriptions;
    }

    /**
     * Returns Cancels Api
     */
    public function getCancelsApi(): CancelsApi
    {
        if ($this->cancels == null) {
            $this->cancels = new CancelsApi($this->client);
        }
        return $this->cancels;
    }

    /**
     * Returns Merchants Api
     */
    public function getMerchantsApi(): MerchantsApi
    {
        if ($this->merchants == null) {
            $this->merchants = new MerchantsApi($this->client);
        }
        return $this->merchants;
    }

    /**
     * Returns Stores Api
     */
    public function getStoresApi(): StoresApi
    {
        if ($this->stores == null) {
            $this->stores = new StoresApi($this->client);
        }
        return $this->stores;
    }

    /**
     * Returns Webhooks Api
     */
    public function getWebhooksApi(): WebhooksApi
    {
        if ($this->webhooks == null) {
            $this->webhooks = new WebhooksApi($this->client);
        }
        return $this->webhooks;
    }

    /**
     * Returns Direct Debit Api
     */
    public function getDirectDebitApi(): DirectDebitApi
    {
        if ($this->directDebit == null) {
            $this->directDebit = new DirectDebitApi($this->client);
        }
        return $this->directDebit;
    }

    /**
     * Returns Checkout Api
     */
    public function getCheckoutApi(): CheckoutApi
    {
        if ($this->checkout == null) {
            $this->checkout = new CheckoutApi($this->client);
        }
        return $this->checkout;
    }

    /**
     * Returns Transaction History Api
     */
    public function getTransactionHistoryApi(): TransactionHistoryApi
    {
        if ($this->transactionHistory == null) {
            $this->transactionHistory = new TransactionHistoryApi($this->client);
        }
        return $this->transactionHistory;
    }

    /**
     * Get the defined global configurations
     */
    private function getGlobalConfiguration(): array
    {
        return [
            TemplateParam::init('baseUrl', $this->getBaseUrl())->dontEncode(),
            TemplateParam::init('directDebitBaseUrl', $this->getDirectDebitBaseUrl())->dontEncode()
        ];
    }

    /**
     * A map of all base urls used in different environments and servers
     *
     * @var array
     */
    private const ENVIRONMENT_MAP =
        [
            Environment::PRODUCTION => [
                Server::DEFAULT_ => '{baseUrl}',
                Server::DIRECTDEBIT => '{directDebitBaseUrl}'
            ]
        ];

    // ── Store-scoped convenience calls ───────────────────────────────────────
    //
    // Each reads the store id from the configured app token and then delegates
    // to the controller method that takes one, forwarding every remaining
    // argument unchanged. They exist so a merchant never has to persist a store
    // id alongside every charge, refund, cancel or subscription id.
    //
    // getCharge($chargeId) above was the first of these; these follow its shape
    // exactly. Default values mirror the controller's, so a shortcut behaves
    // identically to the call it replaces.

    /**
     * The store id this client's app token was issued for, or a thrown error.
     *
     * Every shortcut below funnels through here so that the guard -- and with it
     * the rule that the credential and its claims never reach a message -- has a
     * single call site, and so each shortcut stays two readable lines.
     *
     * @param string $shortcut This shortcut's own signature, named in the error.
     * @param string $explicit The explicit-store call to fall back to.
     *
     * @return string The store id carried by the configured app token.
     *
     * @throws \RuntimeException When the token carries no usable claim.
     */
    private function requireStore(string $shortcut, string $explicit): string
    {
        return AppJwt::requireStoreId($this->getCurrentStoreId(), $shortcut, $explicit);
    }

    /**
     * Lists a charge's refunds without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getRefundsApi()->listRefunds().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function listRefunds(
        string $chargeId,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC,
        ?string $metadata = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listRefunds(chargeId)',
            'listRefunds(storeId, chargeId) on RefundsApi'
        );
        return $this->getRefundsApi()->listRefunds(
            $storeId,
            $chargeId,
            $limit,
            $cursor,
            $cursorDirection,
            $metadata
        );
    }

    /**
     * Refunds a charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getRefundsApi()->createRefund().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function createRefund(
        string $chargeId,
        RefundCreateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'createRefund(chargeId, body)',
            'createRefund(storeId, chargeId, body) on RefundsApi'
        );
        return $this->getRefundsApi()->createRefund(
            $storeId,
            $chargeId,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Retrieves a refund without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getRefundsApi()->getRefund().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function getRefund(string $chargeId, string $id, ?bool $polling = null): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getRefund(chargeId, id)',
            'getRefund(storeId, chargeId, id) on RefundsApi'
        );
        return $this->getRefundsApi()->getRefund($storeId, $chargeId, $id, $polling);
    }

    /**
     * Updates a refund without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getRefundsApi()->updateRefund().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function updateRefund(
        string $chargeId,
        string $id,
        RefundUpdateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateRefund(chargeId, id, body)',
            'updateRefund(storeId, chargeId, id, body) on RefundsApi'
        );
        return $this->getRefundsApi()->updateRefund(
            $storeId,
            $chargeId,
            $id,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Polls a refund to a final status without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getRefundsApi()->pollRefund().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function pollRefund(string $chargeId, string $id, int $maxAttempts = 10): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'pollRefund(chargeId, id)',
            'pollRefund(storeId, chargeId, id) on RefundsApi'
        );
        return $this->getRefundsApi()->pollRefund($storeId, $chargeId, $id, $maxAttempts);
    }

    /**
     * Lists a charge's cancels without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getCancelsApi()->listCancels().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function listCancels(
        string $chargeId,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listCancels(chargeId)',
            'listCancels(storeId, chargeId) on CancelsApi'
        );
        return $this->getCancelsApi()->listCancels(
            $storeId,
            $chargeId,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Cancels a charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getCancelsApi()->createCancel().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function createCancel(
        string $chargeId,
        ?string $idempotencyKey = null,
        ?CancelCreateRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'createCancel(chargeId)',
            'createCancel(storeId, chargeId) on CancelsApi'
        );
        return $this->getCancelsApi()->createCancel(
            $storeId,
            $chargeId,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Retrieves a cancel without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getCancelsApi()->getCancel().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function getCancel(string $chargeId, string $id, ?bool $polling = false): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getCancel(chargeId, id)',
            'getCancel(storeId, chargeId, id) on CancelsApi'
        );
        return $this->getCancelsApi()->getCancel($storeId, $chargeId, $id, $polling);
    }

    /**
     * Updates a cancel without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getCancelsApi()->updateCancel().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function updateCancel(
        string $chargeId,
        string $id,
        CancelUpdateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateCancel(chargeId, id, body)',
            'updateCancel(storeId, chargeId, id, body) on CancelsApi'
        );
        return $this->getCancelsApi()->updateCancel(
            $storeId,
            $chargeId,
            $id,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Polls a cancel to a final status without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getCancelsApi()->pollCancel().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function pollCancel(string $chargeId, string $id, int $maxAttempts = 10): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'pollCancel(chargeId, id)',
            'pollCancel(storeId, chargeId, id) on CancelsApi'
        );
        return $this->getCancelsApi()->pollCancel($storeId, $chargeId, $id, $maxAttempts);
    }

    /**
     * Lists the store's subscriptions without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->listStoreSubscriptions().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function listStoreSubscriptions(
        ?string $search = null,
        ?string $status = null,
        ?string $mode = null,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listStoreSubscriptions()',
            'listStoreSubscriptions(storeId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->listStoreSubscriptions(
            $storeId,
            $search,
            $status,
            $mode,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Simulates a subscription plan for the store without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->simulateStoreSubscriptionPlan().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function simulateStoreSubscriptionPlan(
        ?string $idempotencyKey = null,
        ?SubscriptionSimulationRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'simulateStoreSubscriptionPlan()',
            'simulateStoreSubscriptionPlan(storeId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->simulateStoreSubscriptionPlan(
            $storeId,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Retrieves a subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->getSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function getSubscription(string $id, ?bool $polling = null): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getSubscription(id)',
            'getSubscription(storeId, id) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->getSubscription($storeId, $id, $polling);
    }

    /**
     * Updates a subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->updateSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function updateSubscription(
        string $id,
        ?string $idempotencyKey = null,
        ?SubscriptionUpdateRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateSubscription(id)',
            'updateSubscription(storeId, id) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->updateSubscription(
            $storeId,
            $id,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Cancels a subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->cancelSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function cancelSubscription(string $id): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'cancelSubscription(id)',
            'cancelSubscription(storeId, id) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->cancelSubscription($storeId, $id);
    }

    /**
     * Lists a subscription's payments without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->listSubscriptionPayments().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function listSubscriptionPayments(
        string $subscriptionId,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listSubscriptionPayments(subscriptionId)',
            'listSubscriptionPayments(storeId, subscriptionId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->listSubscriptionPayments(
            $storeId,
            $subscriptionId,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Retrieves one subscription payment without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->getSubscriptionPayment().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function getSubscriptionPayment(
        string $subscriptionId,
        string $paymentId
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getSubscriptionPayment(subscriptionId, paymentId)',
            'getSubscriptionPayment(storeId, subscriptionId, paymentId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->getSubscriptionPayment(
            $storeId,
            $subscriptionId,
            $paymentId
        );
    }

    /**
     * Updates one subscription payment without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->updateSubscriptionPayment().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function updateSubscriptionPayment(
        string $subscriptionId,
        string $paymentId,
        SubscriptionPatchPaymentRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateSubscriptionPayment(subscriptionId, paymentId, body)',
            'updateSubscriptionPayment(storeId, subscriptionId, paymentId, body) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->updateSubscriptionPayment(
            $storeId,
            $subscriptionId,
            $paymentId,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Retrieves a subscription's latest charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->getSubscriptionLatestCharge().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function getSubscriptionLatestCharge(string $subscriptionId): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getSubscriptionLatestCharge(subscriptionId)',
            'getSubscriptionLatestCharge(storeId, subscriptionId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->getSubscriptionLatestCharge(
            $storeId,
            $subscriptionId
        );
    }

    /**
     * Lists a subscription's charges without being given a merchant or store id.
     *
     * The one shortcut whose endpoint is scoped by both ids -- and both are in
     * the app token, so neither has to be passed. The merchant is checked first;
     * every app token carries a `merchant_id`, so that guard fires only for a
     * token that could not be decoded at all.
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no usable
     *                           `merchant_id` or `store_id` claim. Thrown before
     *                           any request is built.
     */
    public function listSubscriptionCharges(
        string $subscriptionId,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse {
        $shortcut = 'listSubscriptionCharges(subscriptionId)';
        $explicit = 'listSubscriptionCharges(merchantId, storeId, subscriptionId) '
            . 'on SubscriptionsApi';
        // Both guards run before the controller is touched, so neither a missing
        // merchant nor a missing store can reach the request path.
        $merchantId = AppJwt::requireMerchantId(
            $this->getCurrentMerchantId(),
            $shortcut,
            $explicit
        );
        $storeId = $this->requireStore($shortcut, $explicit);
        return $this->getSubscriptionsApi()->listSubscriptionCharges(
            $merchantId,
            $storeId,
            $subscriptionId,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Lists the charges for one subscription payment without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->listChargesForSubscriptionPayment().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function listChargesForSubscriptionPayment(
        string $subscriptionId,
        string $paymentId,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listChargesForSubscriptionPayment(subscriptionId, paymentId)',
            'listChargesForSubscriptionPayment(storeId, subscriptionId, paymentId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->listChargesForSubscriptionPayment(
            $storeId,
            $subscriptionId,
            $paymentId,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Suspends a subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->suspendSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function suspendSubscription(
        string $subscriptionId,
        ?string $idempotencyKey = null,
        ?SubscriptionSuspendRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'suspendSubscription(subscriptionId)',
            'suspendSubscription(storeId, subscriptionId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->suspendSubscription(
            $storeId,
            $subscriptionId,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Resumes a suspended subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->unsuspendSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function unsuspendSubscription(
        string $subscriptionId,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'unsuspendSubscription(subscriptionId)',
            'unsuspendSubscription(storeId, subscriptionId) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->unsuspendSubscription(
            $storeId,
            $subscriptionId,
            $idempotencyKey
        );
    }

    /**
     * Swaps the transaction token behind a subscription without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->updateSubscriptionToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function updateSubscriptionToken(
        string $subscriptionId,
        SubscriptionPatchTokenRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateSubscriptionToken(subscriptionId, body)',
            'updateSubscriptionToken(storeId, subscriptionId, body) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->updateSubscriptionToken(
            $storeId,
            $subscriptionId,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Polls a subscription until it leaves 'unverified', without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getSubscriptionsApi()->pollSubscription().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function pollSubscription(string $id, int $maxAttempts = 10): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'pollSubscription(id)',
            'pollSubscription(storeId, id) on SubscriptionsApi'
        );
        return $this->getSubscriptionsApi()->pollSubscription($storeId, $id, $maxAttempts);
    }

    /**
     * Polls a charge out of its current status without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->pollCharge().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim -- a merchant-level token, or none at all.
     *                           Thrown before any request is built.
     */
    public function pollCharge(string $chargeId, int $maxAttempts = 10): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'pollCharge(chargeId)',
            'pollCharge(storeId, chargeId) on ChargesApi'
        );
        return $this->getChargesApi()->pollCharge($storeId, $chargeId, $maxAttempts);
    }


    /**
     * Lists the store's charges without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->listStoreCharges().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function listStoreCharges(
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC,
        ?string $lastFour = null,
        ?string $name = null,
        ?int $expMonth = null,
        ?int $expYear = null,
        ?string $from = null,
        ?string $to = null,
        ?string $email = null,
        ?string $phone = null,
        ?int $amountFrom = null,
        ?int $amountTo = null,
        ?string $currency = null,
        ?string $mode = null,
        ?string $metadata = null,
        ?string $transactionTokenId = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listStoreCharges()',
            'listStoreCharges(storeId) on ChargesApi'
        );
        return $this->getChargesApi()->listStoreCharges(
            $storeId,
            $limit,
            $cursor,
            $cursorDirection,
            $lastFour,
            $name,
            $expMonth,
            $expYear,
            $from,
            $to,
            $email,
            $phone,
            $amountFrom,
            $amountTo,
            $currency,
            $mode,
            $metadata,
            $transactionTokenId
        );
    }

    /**
     * Updates a charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->updateCharge().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function updateCharge(
        string $id,
        ?string $idempotencyKey = null,
        ?ChargeUpdateRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'updateCharge(id)',
            'updateCharge(storeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->updateCharge($storeId, $id, $idempotencyKey, $body);
    }

    /**
     * Captures an authorized charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->captureCharge().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function captureCharge(
        string $id,
        ?string $idempotencyKey = null,
        ?ChargeCaptureRequest $body = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'captureCharge(id)',
            'captureCharge(storeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->captureCharge($storeId, $id, $idempotencyKey, $body);
    }

    /**
     * Retrieves a charge's issuer token without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->getChargeIssuerToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function getChargeIssuerToken(string $id): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getChargeIssuerToken(id)',
            'getChargeIssuerToken(storeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->getChargeIssuerToken($storeId, $id);
    }

    /**
     * Retrieves a charge's 3-D Secure issuer token without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->getChargeThreeDsIssuerToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function getChargeThreeDsIssuerToken(string $id): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getChargeThreeDsIssuerToken(id)',
            'getChargeThreeDsIssuerToken(storeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->getChargeThreeDsIssuerToken($storeId, $id);
    }

    /**
     * Lists a charge's bank transfer ledgers without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->listBankTransferLedgers().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function listBankTransferLedgers(string $id): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'listBankTransferLedgers(id)',
            'listBankTransferLedgers(storeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->listBankTransferLedgers($storeId, $id);
    }

    /**
     * Files a customs declaration for a charge without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->createCustomsDeclaration().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function createCustomsDeclaration(
        string $chargeId,
        CustomsDeclarationCreateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'createCustomsDeclaration(chargeId, body)',
            'createCustomsDeclaration(storeId, chargeId, body) on ChargesApi'
        );
        return $this->getChargesApi()->createCustomsDeclaration(
            $storeId,
            $chargeId,
            $body,
            $idempotencyKey
        );
    }

    /**
     * Retrieves a customs declaration without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->getCustomsDeclaration().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function getCustomsDeclaration(
        string $chargeId,
        string $id,
        ?bool $polling = false
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'getCustomsDeclaration(chargeId, id)',
            'getCustomsDeclaration(storeId, chargeId, id) on ChargesApi'
        );
        return $this->getChargesApi()->getCustomsDeclaration(
            $storeId,
            $chargeId,
            $id,
            $polling
        );
    }

    /**
     * Updates a customs declaration without being given a store id.
     *
     * The endpoint is scoped to a store, which callers would otherwise have to
     * persist -- but a store-level app token already carries one, so this reads
     * it from the configured token and then behaves exactly like
     * getChargesApi()->patchCustomsDeclaration().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the configured token carries no `store_id`
     *                           claim. Thrown before any request is built.
     */
    public function patchCustomsDeclaration(
        string $chargeId,
        string $id,
        CustomsDeclarationPatchRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        // Guard first, controller second: PHP evaluates the object expression
        // before the arguments, so calling the accessor inline would build a
        // controller even on the failure path.
        $storeId = $this->requireStore(
            'patchCustomsDeclaration(chargeId, id, body)',
            'patchCustomsDeclaration(storeId, chargeId, id, body) on ChargesApi'
        );
        return $this->getChargesApi()->patchCustomsDeclaration(
            $storeId,
            $chargeId,
            $id,
            $body,
            $idempotencyKey
        );
    }


    /**
     * Lists the store's transaction tokens without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->listStoreTransactionTokens().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function listStoreTransactionTokens(
        ?string $search = null,
        ?string $customerId = null,
        ?string $type = null,
        ?string $mode = null,
        ?string $active = TransactionTokenActiveFilter::ACTIVE,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'listStoreTransactionTokens(search, customerId)',
            'listStoreTransactionTokens(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->listStoreTransactionTokens(
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
    }

    /**
     * Retrieves a transaction token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->getTransactionToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function getTransactionToken(string $id, ?bool $polling = null): ApiResponse
    {
        $storeId = $this->requireStore(
            'getTransactionToken(id, polling)',
            'getTransactionToken(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->getTransactionToken($storeId, $id, $polling);
    }

    /**
     * Updates a transaction token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->updateTransactionToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function updateTransactionToken(
        string $id,
        ?string $idempotencyKey = null,
        ?TransactionTokenUpdateRequest $body = null
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'updateTransactionToken(id, idempotencyKey)',
            'updateTransactionToken(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->updateTransactionToken(
            $storeId,
            $id,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Deletes a transaction token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->deleteTransactionToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function deleteTransactionToken(string $id): ApiResponse
    {
        $storeId = $this->requireStore(
            'deleteTransactionToken(id)',
            'deleteTransactionToken(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->deleteTransactionToken($storeId, $id);
    }

    /**
     * Enables 3-D Secure on a transaction token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->enableTokenThreeDs().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function enableTokenThreeDs(
        string $id,
        ?string $idempotencyKey = null,
        ?EnableTokenThreeDsRequest $body = null
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'enableTokenThreeDs(id, idempotencyKey)',
            'enableTokenThreeDs(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->enableTokenThreeDs(
            $storeId,
            $id,
            $idempotencyKey,
            $body
        );
    }

    /**
     * Disables 3-D Secure on a transaction token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->disableTokenThreeDs().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function disableTokenThreeDs(string $id): ApiResponse
    {
        $storeId = $this->requireStore(
            'disableTokenThreeDs(id)',
            'disableTokenThreeDs(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->disableTokenThreeDs($storeId, $id);
    }

    /**
     * Retrieves a token's 3-D Secure issuer token without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionTokensApi()->getTokenThreeDsIssuerToken().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function getTokenThreeDsIssuerToken(string $id): ApiResponse
    {
        $storeId = $this->requireStore(
            'getTokenThreeDsIssuerToken(id)',
            'getTokenThreeDsIssuerToken(storeId, ...) on TransactionTokensApi'
        );
        return $this->getTransactionTokensApi()->getTokenThreeDsIssuerToken($storeId, $id);
    }

    /**
     * Lists the store's webhooks without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->listWebhooks().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function listWebhooks(
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC,
        ?bool $active = null
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'listWebhooks(limit, cursor)',
            'listWebhooks(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->listWebhooks(
            $storeId,
            $limit,
            $cursor,
            $cursorDirection,
            $active
        );
    }

    /**
     * Creates a webhook without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->createWebhook().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function createWebhook(
        WebhookCreateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'createWebhook(body, idempotencyKey)',
            'createWebhook(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->createWebhook($storeId, $body, $idempotencyKey);
    }

    /**
     * Retrieves a webhook without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->getWebhook().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function getWebhook(string $id): ApiResponse
    {
        $storeId = $this->requireStore(
            'getWebhook(id)',
            'getWebhook(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->getWebhook($storeId, $id);
    }

    /**
     * Updates a webhook without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->updateWebhook().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function updateWebhook(
        string $id,
        WebhookUpdateRequest $body,
        ?string $idempotencyKey = null
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'updateWebhook(id, body)',
            'updateWebhook(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->updateWebhook($storeId, $id, $body, $idempotencyKey);
    }

    /**
     * Deletes a webhook without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->deleteWebhook().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function deleteWebhook(string $id): ApiResponse
    {
        $storeId = $this->requireStore(
            'deleteWebhook(id)',
            'deleteWebhook(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->deleteWebhook($storeId, $id);
    }

    /**
     * Lists a webhook's events without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getWebhooksApi()->listWebhookEvents().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function listWebhookEvents(
        string $id,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'listWebhookEvents(id, limit)',
            'listWebhookEvents(storeId, ...) on WebhooksApi'
        );
        return $this->getWebhooksApi()->listWebhookEvents(
            $storeId,
            $id,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Lists the store's transaction history without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getTransactionHistoryApi()->listStoreTransactionHistory().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function listStoreTransactionHistory(
        ?string $mode = null,
        ?string $shortId = null,
        ?string $from = null,
        ?string $to = null,
        ?string $status = null,
        ?string $type = null,
        ?string $search = null,
        ?string $email = null,
        ?string $id = null,
        ?string $metadata = null,
        ?string $cardExp = null,
        ?string $cardLastFour = null,
        ?string $cardholder = null,
        ?array $cardBrand = null,
        ?array $brand = null,
        ?array $brands = null,
        ?string $currency = null,
        ?string $serviceProvider = null,
        ?array $serviceProviders = null,
        ?string $gatewayTransactionId = null,
        ?array $bankTransferPaymentStatuses = null,
        ?string $bankTransferLatestDepositDateFrom = null,
        ?string $bankTransferLatestDepositDateTo = null,
        ?int $limit = 10,
        ?string $cursor = null,
        ?string $cursorDirection = CursorDirectionQuery::DESC
    ): ApiResponse
    {
        $storeId = $this->requireStore(
            'listStoreTransactionHistory(mode, shortId)',
            'listStoreTransactionHistory(storeId, ...) on TransactionHistoryApi'
        );
        return $this->getTransactionHistoryApi()->listStoreTransactionHistory(
            $storeId,
            $mode,
            $shortId,
            $from,
            $to,
            $status,
            $type,
            $search,
            $email,
            $id,
            $metadata,
            $cardExp,
            $cardLastFour,
            $cardholder,
            $cardBrand,
            $brand,
            $brands,
            $currency,
            $serviceProvider,
            $serviceProviders,
            $gatewayTransactionId,
            $bankTransferPaymentStatuses,
            $bankTransferLatestDepositDateFrom,
            $bankTransferLatestDepositDateTo,
            $limit,
            $cursor,
            $cursorDirection
        );
    }

    /**
     * Creates a customer id for the store without being given a store id.
     *
     * Reads the store from the configured app token and then behaves exactly
     * like getStoresApi()->createCustomerId().
     *
     * @return ApiResponse The controller's response, untouched.
     *
     * @throws \RuntimeException When the token carries no `store_id` claim.
     */
    public function createCustomerId(CreateCustomerIdRequest $body): ApiResponse
    {
        $storeId = $this->requireStore(
            'createCustomerId(body)',
            'createCustomerId(storeId, ...) on StoresApi'
        );
        return $this->getStoresApi()->createCustomerId($storeId, $body);
    }

}
