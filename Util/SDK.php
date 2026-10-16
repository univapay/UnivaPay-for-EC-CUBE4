<?php

namespace Plugin\UnivaPay\Util;

require_once __DIR__.'/../Resource/vendor/autoload.php';

use Plugin\UnivaPay\Entity\Config;
use Psr\Log\LoggerInterface;
use UnivaPay\Authentication\BearerAuthCredentialsBuilder;
use UnivaPay\Logging\LoggingConfigurationBuilder;
use UnivaPay\Logging\RequestLoggingConfigurationBuilder;
use UnivaPay\Logging\ResponseLoggingConfigurationBuilder;
use UnivaPay\Models\Builders\ChargeUpdateRequestBuilder;
use UnivaPay\Models\Builders\GenericMetadataBuilder;
use UnivaPay\Models\Charge;
use UnivaPay\Models\ChargeCaptureRequest;
use UnivaPay\Models\RefundCreateRequest;
use UnivaPay\Models\RefundStatus;
use UnivaPay\UnivapayClientSdkClient;
use UnivaPay\UnivapayClientSdkClientBuilder;

class SDK
{
    /** @var univapayclientsdkclient */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(Config $config, ?UnivapayClientSdkClient $client = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? UnivaPayLogger::get();
        $this->client = $client ?? UnivapayClientSdkClientBuilder::init()
            ->bearerAuthCredentials(
                BearerAuthCredentialsBuilder::init(
                    $config->getAppSecret(),
                    $config->getAppId()
                )
            )
            ->baseUrl($config->getApiUrl())
            ->loggingConfiguration(
                LoggingConfigurationBuilder::init()->logger($this->logger)
                    ->level(\Psr\Log\LogLevel::INFO)
                    ->requestConfiguration(RequestLoggingConfigurationBuilder::init()
                        ->headers(true)
                        ->body(true)
                    )
                    ->responseConfiguration(ResponseLoggingConfigurationBuilder::init()
                        ->body(true)
                    )
            )
            ->build();
    }

    private function execute(string $resource, string $id, callable $apiCall)
    {
        try {
            $resp = $apiCall();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('UnivaPay request failed: %s %s - %s', $resource, $id, $e->getMessage()));
            throw new UnivaPayApiException(sprintf('Failed to %s %s: request error - %s', $resource, $id, $e->getMessage()), 0, $e);
        }

        if (!$resp->isSuccess()) {
            $this->throwApiError($resource, $id, $resp);
        }

        return $resp->getResult();
    }

    private function throwApiError(string $resource, string $id, $resp)
    {
        $result = $resp->getResult();
        $detail = is_array($result) ? json_encode($result) : $resp->getBody();

        $this->logger->error(sprintf('UnivaPay request failed: %s %s - HTTP %d %s - %s', $resource, $id, $resp->getStatusCode(), $resp->getReasonPhrase(), $detail));
        throw new UnivaPayApiException(sprintf('Failed to %s %s: HTTP %d %s - %s', $resource, $id, $resp->getStatusCode(), $resp->getReasonPhrase(), $detail));
    }

    public function getCharge(string $chargeId)
    {
        return $this->execute('get charge', $chargeId, function () use ($chargeId) {
            return $this->client->getChargesApi()->getCharge(
                $this->client->getCurrentStoreId(),
                $chargeId
            );
        });
    }

    public function getTransactionToken(string $tokenId)
    {
        return $this->execute('get transaction token', $tokenId, function () use ($tokenId) {
            return $this->client->getTransactionTokensApi()->getTransactionToken(
                $this->client->getCurrentStoreId(),
                $tokenId
            );
        });
    }

    public function captureCharge($order, Charge $charge)
    {
        return $this->execute('capture charge', $charge->getId(), function () use ($order, $charge) {
            return $this->client->getChargesApi()->captureCharge(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                $order->getId(),
                new ChargeCaptureRequest(
                    $charge->getChargedAmount(),
                    $charge->getChargedCurrency()
                )
            );
        });
    }

    public function getRefunds(string $chargeId)
    {
        return $this->execute('get refunds', $chargeId, function () use ($chargeId) {
            return $this->client->getRefundsApi()->listRefunds($this->client->getCurrentStoreId(), $chargeId);
        });
    }

    public function createChargeRefund(Charge $charge)
    {
        $refund = $this->execute('create refund', $charge->getId(), function () use ($charge) {
            return $this->client->getRefundsApi()->createRefund(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                new RefundCreateRequest(
                    $charge->getChargedAmount(),
                    $charge->getChargedCurrency()
                )
            );
        });

        $refundResult = $this->execute('poll refund', $refund->getId(), function () use ($charge, $refund) {
            return $this->client->getRefundsApi()->getRefund(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                $refund->getId(),
                true
            );
        });

        switch ($refundResult->getStatus()) {
            case RefundStatus::SUCCESSFUL:
                return $refundResult;
            default:
                $this->logger->error(sprintf('UnivaPay request failed: create refund for charge ID %s - refund ID %s did not complete successfully (status: %s)', $charge->getId(), $refund->getId(), $refundResult->getStatus()));
                throw new UnivaPayApiException(sprintf('Failed to create refund for charge ID : refund ID %s did not complete successfully (status: %s)', $charge->getId(), $refund->getId(), $refundResult->getStatus()));
        }
    }

    public function createChargeCancel($order, Charge $charge)
    {
        $cancel = $this->execute('create cancel', $charge->getId(), function () use ($order, $charge) {
            return $this->client->getCancelsApi()->createCancel(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                $order->getId(),
            );
        });

        return $this->execute('poll cancel', $cancel->getId(), function () use ($charge, $cancel) {
            return $this->client->getCancelsApi()->getCancel(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                $cancel->getId(),
                true
            );
        });
    }

    public function updateChargeMetaData($order, Charge $charge)
    {
        $this->execute('upate charge', $charge->getId(), function () use ($order, $charge) {
            return $this->client->getChargesApi()->updateCharge(
                $this->client->getCurrentStoreId(),
                $charge->getId(),
                null,
                ChargeUpdateRequestBuilder::init()
                    ->metadata(
                        GenericMetadataBuilder::init()
                            ->additionalProperty('orderNo', $order->getId())
                            ->build()
                    )
                    ->build()
            );
        });
    }

    public function getLatestChargeBySubscriptionId(string $subscriptionId)
    {
        return $this->execute('get subscription by subscription id', $subscriptionId, function () use ($subscriptionId) {
            return $this->client->getSubscriptionsApi()->getSubscriptionLatestCharge($this->client->getCurrentStoreId(), $subscriptionId);
        });
    }

    public function getSubscriptionByChargeId(string $chargeId)
    {
        return $this->execute('get subscription by charge', $chargeId, function () use ($chargeId) {
            return $this->client->getChargesApi()->getCharge($this->client->getCurrentStoreId(), $chargeId);
        });
    }

    public function getSubscription(string $subscriptionId)
    {
        return $this->execute('get subscription', $subscriptionId, function () use ($subscriptionId) {
            return $this->client->getSubscriptionsApi()->getSubscription($this->client->getCurrentStoreId(), $subscriptionId);
        });
    }

    public function suspendSubscription(string $subscriptionId)
    {
        return $this->execute('suspend subscription', $subscriptionId, function () use ($subscriptionId) {
            return $this->client->getSubscriptionsApi()->suspendSubscription(
                $this->client->getCurrentStoreId(),
                $subscriptionId
            );
        });
    }

    public function unsuspendSubscription(string $subscriptionId)
    {
        return $this->execute('unsuspend subscription', $subscriptionId, function () use ($subscriptionId) {
            return $this->client->getSubscriptionsApi()->unsuspendSubscription(
                $this->client->getCurrentStoreId(),
                $subscriptionId
            );
        });
    }

    public function cancelSubscription(string $subscriptionId)
    {
        return $this->execute('cancel subscription', $subscriptionId, function () use ($subscriptionId) {
            return $this->client->getSubscriptionsApi()->cancelSubscription(
                $this->client->getCurrentStoreId(),
                $subscriptionId
            );
        });
    }
}
