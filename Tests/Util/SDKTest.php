<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\UnivaPay\Tests\Util;

use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Util\SDK;
use Psr\Log\NullLogger;

class SDKTest extends TestCase
{
    private function createSdk(array $claims)
    {
        $encode = function (array $data) {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };
        $config = new Config();
        $config->setApiUrl('https://api.univapay.com');
        $config->setCustomerUrl('https://customer.univapay.com');
        $config->setAppId($encode(['alg' => 'HS256']).'.'.$encode($claims).'.signature');
        $config->setAppSecret('secret');

        return new SDK($config, null, new NullLogger());
    }

    public function testCustomerPageUrl()
    {
        $url = $this->createSdk(['store_id' => '11edf541-c42d-653c-8c3d-dfe0a55f95c0'])
            ->getCustomerPageUrl('11ef335e-9aa5-c54a-8313-7f9847da313a', 'user+1@example.com');

        $this->assertSame(
            'https://customer.univapay.com/stores/11edf541-c42d-653c-8c3d-dfe0a55f95c0'
            .'?emailAddress=user%2B1%40example.com&subscriptionId=11ef335e-9aa5-c54a-8313-7f9847da313a',
            $url
        );
    }

    public function testCustomerPageUrlIsNullForMerchantLevelToken()
    {
        $this->assertNull($this->createSdk(['merchant_id' => '11edf541-c42d-653c-8c3d-dfe0a55f95c0'])->getCustomerPageUrl('11ef335e-9aa5-c54a-8313-7f9847da313a', 'user@example.com'));
    }
}
