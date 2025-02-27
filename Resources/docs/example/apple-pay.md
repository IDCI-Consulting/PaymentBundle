# Setup Apple Pay

Last updated: 02/01/25

## Project prequisites

Make sure to import routing configuration provided by the bundle before:

```yaml
idci_payment_apple_pay:
    resource: '@IDCIPaymentBundle/Resources/config/routing_apple_pay.yml'
    prefix:   /
```

## Setting Up Your Server

Official docs: https://developer.apple.com/documentation/apple_pay_on_the_web/setting_up_your_server

* All pages that include Apple Pay must be served over HTTPS.
* Your domain must have a valid SSL certificate.
* Your server must support the Transport Layer Security (TLS) protocol version 1.2 or later, and one of the cipher suites listed on the page

## Configuring you environment

Official docs: https://developer.apple.com/documentation/apple_pay_on_the_web/configuring_your_environment

* Create a developer account (https://appleid.apple.com/account?appId=632&returnUrl=https%3A%2F%2Fdeveloper.apple.com%2Faccount%2F)
* Enroll developer program (https://developer.apple.com/programs/enroll/)
* Create a merchant identifier (https://developer.apple.com/account/resources/identifiers/add/bundleId > Merchant IDs)
* Create a payment processing certificate (https://developer.apple.com/account/resources/certificates/XXXXXXXXX/add) -> See help in `Certificates generation` section
* Create a merchant identity certificate (https://help.apple.com/developer-account/#/dev1731126fb) -> See help in `Certificates generation` section
* Register your domains -> See help in `Domain registration` section

## Certificates generation

### Merchant Identity certificate

Generate & upload the following file to Apple Pay server:

```shell
$ openssl req -new -newkey rsa:2048 -nodes -keyout ./merchant_identity.key -out ./merchant_identity.csr
```

Then download the `.cer` file from Apple Pay server & transform it to `.pem`

```shell
$ openssl x509 -inform DER -in ./merchant_identity.cer -out ./merchant_identity_tmp.pem -outform PEM
$ openssl pkcs12 -export -out ./merchant_identity.p12 -inkey ./merchant_identity.key -in ./merchant_identity_tmp.pem
$ openssl pkcs12 -in ./merchant_identity.p12 -out ./merchant_identity.pem -nodes -clcerts
# If you want inline cert
$ awk 'NF {sub(/\r/, ""); printf "%s\\n",$0;}' ./merchant_identity.pem > ./merchant_identity.inline.pem
```

You should be able to create a session from Apple Pay Gateway:

```shell
$ curl -gv --data '{"merchantIdentifier":"merchant.example.com", "initiativeContext":"www.domain.com", "initiative":"web", "displayName":"MY WEBSITE"}' --cert ./merchant_identity.pem https://apple-pay-gateway.apple.com/paymentservices/paymentSession
```

Finally, take content from you file & use it for payment gateway configuration `merchant_identity_certificate`.

```yaml
idci_payment:
    gateway_configurations:
        apple_pay:
            gateway_name: apple_pay
            enabled: true
            parameters:
                # ...
                payment_processing_private_key: 'YOUR KEY'
                token_signature_duration: 10000000 # Note: change to few minutes int production context to avoid double spending
                token_self_decrypt: true
```

### Payment processing certificate

Generate & upload the following file to Apple Pay server:

```shell
$ openssl ecparam -out ./payment_processing.key -name prime256v1 -genkey
$ openssl req -new -sha256 -key ./payment_processing.key -nodes -out ./payment_processing.csr
```

Then you have two choices, relay the payment token decryption to your PSP or decrypt the token by your own (becareful you may need to be PCI-Compliant to enable token self decryption)

#### PSP payment token decrypt

Download the `.cer` file from Apple Pay server & you can upload the certificate to your subscribed PSP.

#### Payment token self decrypt

Important note, then package `payu/apple-pay` packages is needed to decrypt the token from server side

```
$ composer require payu/apple-pay
```

##### Apple Root CA

First, download the root public certificate from Apple Pay web server:

```shell
$ curl https://www.apple.com/certificateauthority/AppleRootCA-G3.cer --output ./path/to/AppleRootCA-G3.cer
$ openssl x509 -inform der -in ./path/to/AppleRootCA-G3.cer -out ./path/to/AppleRootCA-G3.pem
```

Finally, configure the `idci_payment.apple_pay.root_ca_file_path` with the CA file path

```yaml
parameters:
    # ...
    idci_payment.apple_pay.root_ca_file_path: '/path/to/AppleRootCA-G3.pem'
```

##### Payment processing

First, generate the payment processing private key from certificate:

```shell
$ openssl x509 -inform DER -in ./payment_processing.cer -out ./payment_processing_tmp.pem -outform PEM
$ openssl pkcs12 -export -out ./payment_processing.p12 -inkey ./payment_processing.key -in ./payment_processing_tmp.pem
$ openssl pkcs12 -in ./payment_processing.p12 -out ./root/private_key.pem -nocerts -nodes
```

Then, take the value in the file between the `-----BEGIN PRIVATE KEY-----` & `-----END PRIVATE KEY-----` lines & configure the payment gateway configuration key `payment_processing_private_key` & enable token self decryption :

```yaml
idci_payment:
    gateway_configurations:
        apple_pay:
            gateway_name: apple_pay
            enabled: true
            parameters:
                # ...
                payment_processing_private_key: 'YOUR KEY'
                token_signature_duration: 10000000 # Note: change to few minutes in production context to avoid double spending (5 minutes should do the job)
                token_self_decrypt: true
```

## Domain registration

First create & configure the directory where merchant domain association files will be stored:

```yaml
parameters:
    idci_payment.apple_pay.domain_verification_directory_path: '%kernel.project_dir%/config/apple_pay/domain_verification'
```

Then add merchant domain & download the association file & store it in the configured directory with the file name corresponding to your domain host, example:

```
domain_verification/
    www.my-domain.com
    www.my-new-domain.com
    ...
```

Then file should be publicly accessible from URL:

https://www.my-domain.com/.well-known/apple-developer-merchantid-domain-association.txt

Finally, complete verification from your Apple Pay dashboard.

## Create one click context

First, make sure to configure the payment gateway configuration `mode` to `one_click`:

```yaml
idci_payment:
    gateway_configurations:
        apple_pay_one_click:
            gateway_name: apple_pay
            enabled: true
            parameters:
                # ...
                mode: one_click
```

Then when creating your transaction you can pass extraData from the `applicationData` or `customData` fields (applicationData will be passed to apple pay for token verfication and customData is never used in apple pay), here is an example to create a transaction in one click context for a cart:

```php
<?php

$paymentContext = $this->paymentManager->createPaymentContextByAlias('apple_pay_one_click');

$payment = $paymentContext->createTransaction([
    'item_id' => $cart['id'],
    'amount' => $cart['amount'] * 100,
    'currency_code' => 'EUR',
]);

$lineItems = [];
foreach ($cart['products'] as $product) {
    $lineItems[] = [
        'label' => sprintf('%s (x%s)', $product['label'], $product['quantity']),
        'amount' => (string) $product['totalAmount'],
    ];
}

$htmlView = $paymentContext->buildHTMLView([
    'shippingMethods' => [
        [
            'label' => 'Click & Collect',
            'amount' => '0.00',
            'identifier' => 'collected',
            'detail' => 'Retrieve your order from our shop',
        ],
        [
            'label' => 'Shipped',
            'amount' => '0.00',
            'identifier' => 'shipped',
            'detail' => 'Delay estimation: 3 days',
        ]
    ],
    'lineItems' => $lineItems,
    'customData' => [
        'cart' => $cart['id'],
    ],
]);
```

Then, observe the one click event in a custom event subscriber:

```php
<?php

namespace App\EventSubscriber;

use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplePayPaymentGatewayEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OneClickContextEvents::APPLE_PAY => [ // Subscribe to the apple pay one click event
                ['createContextFromCart', 0],
            ],
        ];
    }
    private function createContextFromCart(OneClickContextEvent $oneClickContextEvent)
    {
        $customData = json_decode($oneClickContextEvent->getData()['customData'] ?? '{}', true);

        if (!isset($customData['cart'])) {
            return;
        }

        // Then create data from the context (instructions below are just for example, change according to your needs)
        $this->createCustomer([
            // ...
        ]);

        $this->createOrder([
            // ...
        ]);

        $this->createTransaction([
            'reference' => $customData['transaction_id']
            // ...
        ]);
    }
}

```


## Send PSP/MPI data

```php
<?php

namespace App\EventSubscriber;

use ECorp\Bundle\CustomerFetcherBundle\Security\Customer;
use ECorp\Bundle\CustomerFetcherBundle\Source\CustomerSourceRegistry;
use IDCI\Bundle\PaymentBundle\Gateway\Event\ApplePayPaymentGatewayEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Event\ApplePayPaymentGatewayEvents;
use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvents;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApplePayPaymentGatewayEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ApplePayPaymentGatewayEvents::SEND_PSP_DATA => [
                ['sendPSPData'] // self_decrypt = false
            ],
            ApplePayPaymentGatewayEvents::SEND_MPI_DATA => [
                ['sendMPIData'] // self_decrypt = true
            ],
        ];
    }

    public function sendPSPData(ApplePayPaymentGatewayEvent $applePayPaymentGatewayEvent)
    {
        // Use event context to send data to PSP
        $response = $client->request('POST', 'https://your-psp.com', [
            'data' => ['...' => '...']
        ])

        // Finally change the gateway response status according to your PSP response
        $applePayPaymentGatewayEvent->getGatewayResponse()->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public function sendMPIData(ApplePayPaymentGatewayEvent $applePayPaymentGatewayEvent)
    {
        // Use event context to send data to PSP
        $response = $client->request('POST', 'https://your-psp.com', [
            'data' => ['...' => '...']
        ])

        // Finally change the gateway response status according to your PSP response
        $applePayPaymentGatewayEvent->getGatewayResponse()->setStatus(PaymentStatus::STATUS_FAILED);
    }
}
```

## Custom JS events

You can attach to some js events if you want to add some real time changes, here is the list (be careful it will override default one, so make sure to complete every step):

- onpaymentmethodselected
- onshippingcontactselected
- onshippingmethodselected
- onpaymentsuccess
- onpaymentfailed
- oncancel

Example (redirect to order summary on payment success):

```js
document.addEventListener('DOMContentLoaded', function () {
    // Attach to session create event to retrieve initialization elements
    ApplePaySession.onsessioncreate = function (event) {
        let amount = event.amount;
        let request = event.request;
        let session = event.session;

        // Then override the session events
        session.onpaymentsuccess = function (event) {
            let data = event.data;
            if ('approved' === data.status) {
                window.location.href = 'https://my-domain.com/order_summary/' + data.item_id;
            }
        }
    };
})
```