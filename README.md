# PaymentBundle

This Symfony bundle provide help for integrating payments solutions by the normalization of payment process thanks to gateways. Each used gateway must have a configuration to set its parameters.

Example controller :

```php
$gateway = $this->paymentGatewayManager->getByAlias('stripe_test'); // raw alias

$payment = $gateway->createPayment([
    'item_id' => 5,
    'amount' => 500,
    'currency_code' => 'EUR',
]);

return $this->render('@IDCIPaymentBundle/Resources/views/payment.html.twig', [
    'view' => $gateway->buildHTMLView($payment),
]);
```

A list of [commands](#command) is provided by this bundle to create, retrieve, update or delete gateway configurations.

Supported Gateways
-------

* [Stripe](./Gateway/StripePaymentGateway.php) (for testing purpose)

Command
-------

##### PaymentGatewayConfiguration

```bash
# To create a PaymentGatewayConfiguration
$ php bin/console app:payment-gateway-configuration:create

# To show the list of PaymentGatewayConfiguration
$ php bin/console app:payment-gateway-configuration:list

# To update a PaymentGatewayConfiguration
$ php bin/console app:payment-gateway-configuration:update

# To delete a PaymentGatewayConfiguration
$ php bin/console app:payment-gateway-configuration:delete
```

Resources
---------

##### UML Diagram

![UML Diagram](./Resources/docs/uml-schema.png)
