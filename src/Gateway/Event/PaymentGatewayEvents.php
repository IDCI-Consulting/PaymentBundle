<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

final class PaymentGatewayEvents
{
    const PRE_CONFIGURE_OPTIONS = 'idci_payment.payment_gateway.pre_configure_options';
    const POST_CONFIGURE_OPTIONS = 'idci_payment.payment_gateway.post_configure_options';
}
