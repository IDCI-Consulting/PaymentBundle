# PaymentBundle

This Symfony bundle provide help for integrating payments solutions by the normalization of payment process thanks to gateways. Each used gateway must have a configuration to set its parameters.

Example controller :

```php
<?php

$paymentContext = $this->paymentManager->createPaymentContextByAlias('stripe_test'); // raw alias

$payment = $paymentContext->createPayment([
    'item_id' => 5,
    'amount' => 500,
    'currency_code' => 'EUR',
]);

return $this->render('@IDCIPayment/payment.html.twig', [
    'view' => $paymentContext->buildHTMLView(),
]);
```

A list of [commands](#command) is provided by this bundle to manage gateway configurations & transactions.

Installation
------------

Add dependency in your ```composer.json``` file:

```json
"require": {
    ...,
    "idci/payment-bundle": "^4.0",
}
```

Install this new dependency in your application using composer:

```bash
$ composer update
```

Enable bundle in your application kernel :

```php
<?php
// config/bundles.php
return [
    // ...
    new IDCI\Bundle\PaymentBundle\IDCIPaymentBundle(),
];
```

Add this to your ```config.yml``` file

```yaml
# config/packages/idci_payment.yaml
imports:
    - {resource: '@IDCIPaymentBundle/Resources/config/config.yml'}

# Enable monolog logging using event subscriber plugged on transaction state changes
idci_payment:
    enabled_logger_subscriber: true

```

(Optional) If you want to customize the payment logger, by defaults, it will output into main handler

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        # ...
        payment_log:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            channels: ['payment']
```

Install routes in your ```config/routes/idci_payment.yaml``` file:

```yaml
# config/routes/idci_payment.yaml
idci_payment:
    resource: '@IDCIPaymentBundle/Resources/config/routing.yml'
    prefix:   /

idci_payment_api:
    resource: '@IDCIPaymentBundle/Resources/config/routing_api.yml'
    prefix:   /api
```

These tutorials may help you to personalize yourself this bundle:

- [Create a new payment gateway](./Resources/docs/create-your-payment-gateway.md): incorporate new payment method to this bundle
- [Create your own transaction manager](./Resources/docs/create-your-transaction-manager.md) : help you to retrieve transaction from other stockages methods (default: Doctrine)
- [Use this bundle with step bundle](./Resources/docs/use-step-bundle.md): simple configuration to make this bundle work with step bundle
- [Create your own event subscriber](./Resources/docs/create-your-event-subscriber.md): learn how to work with transaction event

Supported Gateways
------------------

* [Stripe](./Gateway/StripePaymentGateway.php) ([example](./Resources/docs/example/stripe.md))
* [Paypal](./Gateway/PaypalPaymentGateway.php) ([example](./Resources/docs/example/paypal.md))
* [Paybox](./Gateway/PayboxPaymentGateway.php) ([example](./Resources/docs/example/paybox.md))
* [Monetico](./Gateway/MoneticoPaymentGateway.php) (Unsupported for now)
* [Ogone](./Gateway/OgonePaymentGateway.php) (Unsupported for now)
* [PayPlug](./Gateway/PayPlugPaymentGateway.php) ([example](./Resources/docs/example/payplug.md))
* [SystemPay](./Gateway/SystemPayPaymentGateway.php) ([example](./Resources/docs/example/systempay.md))
* [Sofinco](./Gateway/SofincoPaymentGateway.php) ([example](./Resources/docs/example/sofinco.md))
* [Sofinco CACF](./Gateway/SofincoCACFPaymentGateway.php) ([example](./Resources/docs/example/sofinco-cacf.md))
* [Eureka/FloaBank](./Gateway/EurekaPaymentGateway.php) ([example](./Resources/docs/example/eureka.md))
* [Alma](./Gateway/AlmaPaymentGateway.php) ([example](./Resources/docs/example/alma.md))
* [Atos Sips Bin](./Gateway/AtosSipsBinPaymentGateway.php)
    * Mercanet ([example](./Resources/docs/example/mercanet-bin.md))
    * Scellius ([example](./Resources/docs/example/scellius-bin.md))
    * Sogenactif ([example](./Resources/docs/example/sogenactif-bin.md))
* [Atos Sips POST](./Gateway/AtosSipsPostPaymentGateway.php)
    * Mercanet ([example](./Resources/docs/example/mercanet-post.md))
    * Sogenactif ([example](./Resources/docs/example/sogenactif-post.md))
* [Atos Sips JSON](./Gateway/AtosSipsJsonPaymentGateway.php)
    * Mercanet ([example](./Resources/docs/example/mercanet-json.md))
    * Sogenactif ([example](./Resources/docs/example/sogenactif-json.md))

For testing purpose:
- [Parameters](./Resources/docs/test-parameters.md)
- [Cards](./Resources/docs/test-cards.md)

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

##### Transaction

```bash
# Remove all the aborted transaction created 1 day ago
$ php bin/console app:transaction:clean
```

Tests
-----

Add test routing :

```yaml
# config/routes/dev/idci_payment.yaml

_test_payment:
    resource: '@IDCIPaymentBundle/Resources/config/routing_test.yml'
    prefix:   /_test/

```

You can now test gateways on ```/_test/payment-gateway/select``` (be sure to have created one or more gateway configuration)

Resources
---------

##### UML Diagram

![UML Diagram](./Resources/docs/uml-schema.png)
