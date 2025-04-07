<?php

namespace Plugin\UnivaPay\Util;

class Constants {
    const UNIVAPAY_PAYMENT_METHOD = "UnivaPay";
    const UNIVAPAY_API_URL = "https://api.univapay.com";
    const UNIVAPAY_WIDGET_URL = "https://widget.univapay.com";
    const UNIVAPAY_WEBHOOK_SECRET = "";
    const MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME = "UnivaPayサブスクリプション";
    const MASTER_DATA_UNIVAPAY_CANCEL_NAME = "UnivaPayサブスクリプション永久停止";
    const MASTER_DATA_UNIVAPAY_SUSPEND_NAME = "UnivaPayサブスクリプション一時停止";
    const MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE = "UnivaPaySubscription";
    const MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL = "UnivaPaySubscription停止";
}
