How to create your own payment gateway
--------------------------------------

## Introduction

Q: What's a payment gateway ?  
A: That's what that prepare the form and the management of the transaction for a specific payment method (ex: paypal, stripe, ...)

Q: How does it work ?  
A: (schema)

## Learn by example

All of the payment gateway extends from [AbstractPaymentGateway](../../Gateway/AbstractPaymentGateway.php) so it must contains the following methods :

```php
<?php
/**
 * Example inspired by PaypalPaymentGateway
 */
namespace MyBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;

class ExemplePaymentGateway extends AbstractPaymentGateway
{

    // Return the data that will be used in payment gateway view
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'clientId' => $paymentGatewayConfiguration->get('client_id'),
            'transaction' => $transaction,
            'callbackUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'returnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'environment' => $paymentGatewayConfiguration->get('environment'),
        ];
    }

    // Return the builded view in HTML format by using twig templating
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paypal.html.twig', [
            'initializationData' => $this->initialize($paymentGatewayConfiguration, $transaction),
        ]);
    }

    // Return the bank response in formalized GatewayResponse format to let PaymentContext verify that the transaction is correct
    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        // ...

        // normalize the return parameters into the GatewayReponse object
        $amount = $paypalPayment->getTransactions()[0]->getAmount();

        $gatewayResponse
            ->setTransactionUuid($request->get('transactionID'))
            ->setAmount($amount->total * 100)
            ->setCurrencyCode($amount->currency)
        ;

        // ...

        // don't forget to set the payment status (failed or approved)
        if ('approved' !== $result->getState()) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    // Return the available parameters used in payment gateway configuration commands and configuration file
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'client_id',
                'client_secret',
                'environment',
            ]
        );
    }

}
```

Now add the following configuration in your ```service.yml``` file:

```yml
# service.yml
YourBundle\YourPath\NewPaymentGateway:
    tags:
        - { name: idci_payment.gateways, alias: your_gateway_name }
```

Warning : The payment gateway ```getResponse()``` method will never be call by the client but by the bank itself in backend controller for security reason.  

## How to create a payment gateway configuration for your own gateway

Use console command and choose your payment gateway:

```bash
$ php bin/console app:payment-gateway-configuration:create
```

You can also add it to your ```config.yml``` file:

```yml
idci_payment:
    gateway_configurations:
        paypal_example:
            gateway_name: paypal
            enabled: true
            parameters:
                client_id: (id)
                client_secret: (secret)
                environment: sandbox
                return_url: www.example.com
                callback_url: www.example.com
```
