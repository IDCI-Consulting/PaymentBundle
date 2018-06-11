# PaymentBundle

This Symfony bundle provide help for integrating payments solutions by the normalization of payment process thanks to gateways.

![UML Diagram](./Resources/docs/uml-schema.png)

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
