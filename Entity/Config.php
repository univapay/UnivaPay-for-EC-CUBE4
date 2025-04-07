<?php
namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_univa_pay_config")
 * @ORM\Entity(repositoryClass="Plugin\UnivaPay\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="widget_url", type="string", length=1024, nullable=true)
     */
    private $widget_url;

    /**
     * @var string
     *
     * @ORM\Column(name="api_url", type="string", length=1024, nullable=true)
     */
    private $api_url;

    /**
     * @var string
     *
     * @ORM\Column(name="app_id", type="string", length=512, nullable=true)
     */
    private $app_id;

    /**
     * @var string
     *
     * @ORM\Column(name="app_secret", type="string", length=32, nullable=true)
     */
    private $app_secret;

    /**
     * @var string
     *
     * @ORM\Column(name="webhook_auth", type="string", length=36, nullable=true)
     */
    private $webhook_auth;

    /**
     * @var boolean
     *
     * @ORM\Column(name="capture", type="boolean", nullable=true)
     */
    private $capture;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getWidgetUrl()
    {
        return $this->widget_url;
    }

    /**
     * @param string $widget_url
     *
     * @return $this;
     */
    public function setWidgetUrl($widget_url)
    {
        $this->widget_url = $widget_url;

        return $this;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }

    /**
     * @param string $api_url
     *
     * @return $this;
     */
    public function setApiUrl($api_url)
    {
        $this->api_url = $api_url;

        return $this;
    }

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->app_id;
    }

    /**
     * @param string $app_id
     *
     * @return $this;
     */
    public function setAppId($app_id)
    {
        $this->app_id = $app_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getAppSecret()
    {
        return $this->app_secret;
    }

    /**
     * @param string $app_secret
     *
     * @return $this;
     */
    public function setAppSecret($app_secret)
    {
        $this->app_secret = $app_secret;

        return $this;
    }

    /**
     * @return string
     */
    public function getWebhookAuth()
    {
        return $this->webhook_auth;
    }

    /**
     * @param string $webhook_auth
     *
     * @return $this;
     */
    public function setWebhookAuth($value)
    {
        $this->webhook_auth = $value;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getCapture()
    {
        return $this->capture;
    }

    /**
     * @param boolean $capture
     *
     * @return $this;
     */
    public function setCapture($capture)
    {
        $this->capture = $capture;

        return $this;
    }

}
