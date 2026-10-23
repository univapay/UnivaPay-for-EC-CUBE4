<?php

namespace Plugin\UnivaPay\Util;

class Constants
{
    public const UNIVAPAY_PAYMENT_METHOD = 'UnivaPay';
    public const UNIVAPAY_API_URL = 'https://api.univapay.com';
    public const UNIVAPAY_WIDGET_URL = 'https://widget.univapay.com';
    public const UNIVAPAY_CUSTOMER_URL = 'https://customer.univapay.com';
    public const UNIVAPAY_WEBHOOK_SECRET = '';
    public const MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME = 'UnivaPayサブスクリプション';
    public const MASTER_DATA_UNIVAPAY_CANCEL_NAME = 'UnivaPayサブスクリプション永久停止';
    public const MASTER_DATA_UNIVAPAY_SUSPEND_NAME = 'UnivaPayサブスクリプション一時停止';
    public const MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE = 'UnivaPaySubscription';
    public const MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL = 'UnivaPaySubscription停止';
}
