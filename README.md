# PaymentBundle

This Symfony bundle provide help for integrating payments solutions by the normalization of payment process thanks to gateways. Each used gateway must have a configuration to set its parameters. For exemple to set an API key :

```php
    public function preProcess(Request $request)
    {
        Stripe\Stripe::setApiKey($this->paymentGatewayConfiguration->getParameters()['secret_key']);
    }
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
$ php bin/console app:payment-gateway-configuration:update
```

Resources
---------

##### UML Diagram

![UML Diagram](./Resources/docs/uml-schema.png)
