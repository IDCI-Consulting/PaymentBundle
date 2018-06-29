# PaymentBundle

This Symfony bundle provide help for integrating payments solutions by the normalization of payment process thanks to gateways. Each used gateway must have a configuration to set its parameters.

Example controller :

```php
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

A list of [commands](#command) is provided by this bundle to create, retrieve, update or delete gateway configurations.

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

Supported Gateways
------------------

* [Stripe](./Gateway/StripePaymentGateway.php)
* [Paypal](./Gateway/PaypalPaymentGateway.php)
* [Paybox](./Gateway/PayboxPaymentGateway.php)
* [Monetico](./Gateway/MoneticoPaymentGateway.php) (unfinished)
* [Ogone](./Gateway/MoneticoPaymentGateway.php) (unfinished)
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
