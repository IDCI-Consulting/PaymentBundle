<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

final class ApplePayPaymentGatewayEvents
{
    public const SEND_MPI_DATA = 'idci_payment.apple_pay_payment_gateway.send_mpi_data';
    public const SEND_PSP_DATA = 'idci_payment.apple_pay_payment_gateway.send_psp_data';

    public const CREATE_SESSION = 'idci_payment.apple_pay_payment_gateway.create_session';

    public const PRE_BUILD_REQUEST = 'idci_payment.apple_pay_payment_gateway.pre_build_request';
    public const POST_BUILD_REQUEST = 'idci_payment.apple_pay_payment_gateway.post_build_request';
}
