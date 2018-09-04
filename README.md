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

return $this->render('@IDCIPaymentBundle/Resources/views/payment.html.twig', [
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
    "idci/payment-bundle": "dev-master",
}
```

Install this new dependency in your application using composer:

```bash
$ composer update
```

Enable bundle in your application kernel :

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new IDCI\Bundle\PaymentBundle\IDCIPaymentBundle(),
    );
}
```

Add this to your ```config.yml``` file

```yaml

imports:
    - {resource: '@IDCIPaymentBundle/Resources/config/config.yml'}

...
idci_payment:
    enabled_doctrine_subscriber: true
    enabled_logger_subscriber: true
    enabled_redis_subscriber: true
```

These tutorials may help you to personalize yourself this bundle:

- [Create a new payment gateway](./Resources/docs/create-your-payment-gateway.md): incorporate new payment method to this bundle
- [Create your own transaction manager](./Resources/docs/create-your-transaction-manager.md) : help you to retrieve transaction from other stockages methods (default: Doctrine)
- [Use this bundle with step bundle](./Resources/docs/how-to-work-with-step-bundle.md): simple configuration to make this bundle work with step bundle
- [Create your own event subscriber](./Resources/docs/create-your-event-subscriber.md): learn to work with transaction event

Supported Gateways
------------------

* [Stripe](./Gateway/StripePaymentGateway.php)
* [Paypal](./Gateway/PaypalPaymentGateway.php)
* [Paybox](./Gateway/PayboxPaymentGateway.php)
* [Monetico](./Gateway/MoneticoPaymentGateway.php) (unfinished)
* [Ogone](./Gateway/OgonePaymentGateway.php) (unfinished)
* [PayPlug](./Gateway/PayPlugPaymentGateway.php)
* [Atos Sips Bin](./Gateway/AtosSipsBinPaymentGateway.php)
    * Scellius
    * Sogenactif
* [Atos Sips POST](./Gateway/AtosSipsPostPaymentGateway.php)
    * Mercanet
    * Sogenactif
* [Atos Sips JSON](./Gateway/AtosSipsJsonPaymentGateway.php)
    * Mercanet
    * Sogenactif

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
# app/config/routing_dev.php

_test_payment:
    resource: '@IDCIPaymentBundle/Resources/config/routing.yml'
    prefix:   /_test/

```

You can now test gateways on ```/_test/payment-gateway/select``` (be sure to have created one or more gateway configuration)

Resources
---------

##### UML Diagram

![UML Diagram](./Resources/docs/uml-schema.png)
