<?php
namespace Plugin\UnivaPay\Util;

use Plugin\UnivaPay\Entity\Config;
use Univapay\Resources\Authentication\AppJWT;
use Univapay\UnivapayClient;
use Univapay\UnivapayClientOptions;

class SDK {
    private $token;
    private $client;

    public function __construct(Config $config)
    {
        $clientOptions = new UnivapayClientOptions($config->getApiUrl());
        $this->token = AppJWT::createToken($config->getAppId(), $config->getAppSecret());
        $this->client = new UnivapayClient($this->token, $clientOptions);
    }

    public function getCharge($chargeId) {
        return $this->client->getCharge($this->token->storeId, $chargeId);
    }

    public function getTransactionTokenByChargeId($chargeId) {
        return $this->client->getTransactionToken($this->client->getCharge($this->token->storeId, $chargeId)->transactionTokenId);
    }

    public function getChargeBySubscriptionId($subscriptionId) {
        return current($this->client->getSubscription($this->token->storeId, $subscriptionId)->listCharges()->items);
    }

    public function getSubscriptionByChargeId($chargeId) {
        return $this->client->getSubscription($this->token->storeId, $this->getCharge($chargeId)->subscriptionId);
    }

    public function getSubscription($subscriptionId) {
        return $this->client->getSubscription($this->token->storeId, $subscriptionId);
    }
}
