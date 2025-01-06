<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

final class ApplePayPaymentGatewayEvents
{
    const SEND_MPI_DATA = 'idci_payment.apple_pay_payment_gateway.send_mpi_data';
    const SEND_PSP_DATA = 'idci_payment.apple_pay_payment_gateway.send_psp_data';
}
