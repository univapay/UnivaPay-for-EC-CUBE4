<?php

namespace Plugin\UnivaPay\Tests\Admin;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\UnivaPay\Util\Constants;

/**
 * Test UnivaPay Config
 */
class ConfigControllerTest extends AbstractAdminWebTestCase
{
    protected $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = $this->getFaker();
    }

    public function testConfigRouting()
    {
        $crawler = $this->client->request('GET', $this->generateUrl('univa_pay_admin_config'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertStringContainsString('トークン', $crawler->html());
    }

    public function testSaveConfigSuccessfully()
    {
        $crawler = $this->client->request('GET', $this->generateUrl('univa_pay_admin_config'));
        $form = $crawler->selectButton(trans('univa_pay.admin.config.save'))->form();
        $form['config[app_id]'] = $this->faker->text(20);
        $form['config[app_secret]'] = $this->faker->text(20);
        $form['config[widget_url]'] = Constants::UNIVAPAY_WIDGET_URL;
        $form['config[api_url]'] = Constants::UNIVAPAY_API_URL;
        $form['config[customer_url]'] = Constants::UNIVAPAY_CUSTOMER_URL;
        $crawler = $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirection($this->generateUrl('univa_pay_admin_config')));
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString(trans('univa_pay.admin.save.success'), $crawler->html());
    }
}
