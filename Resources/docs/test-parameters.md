TEST PARAMETERS FOR PAYMENT GATEWAYS
------------------------------------

# Atos Sips Post

## Mercanet

```yaml
# if you want to overide the host name used in mercanet gateway (already configured by default)
parameters:
    idci_payment.mercanet.server_host_name: payment-webinit.simu.mercanet.bnpparibas.net # prod: payment-webinit.mercanet.bnpparibas.net

idci_payment:
    gateway_configurations:
        mercanet_post_test:
            gateway_name: mercanet_post_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/mercanet_post_test/callback # must be public
                secret: '002001000000001_KEY1'
                version: 1
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                order_channel: 'INTERNET'
                interfaceVersion: 'HP_2.20'
                merchant_id: '002001000000001'
```

## Sogenactif

```yaml
# if you want to overide the host name used in sogenactif gateway (already configured by default)
parameters:
    idci_payment.sogenactif.server_host_name: payment-webinit.simu.sips-atos.com # prod: payment-webinit-ws.sogenactif.com

idci_payment:
    gateway_configurations:
        sogenactif_post_test:
            gateway_name: sogenactif_post_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/sogenactif_post_test/callback # must be public
                secret: '002001000000001_KEY1'
                version: 1
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                order_channel: 'INTERNET'
                interfaceVersion: 'HP_2.20'
                merchant_id: '002001000000001'
```

# Atos Sips Bin
## Sogenactif

```yaml
# if you want to overide parameters used in sogenactif gateway (already configured by default)
parameters:
    idci_payment.sogenactif_bin.pathfile_path: "%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/param/sogenactif/pathfile.sogenactif"
    idci_payment.atos_sips_bin.request_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/request'
    idci_payment.atos_sips_bin.response_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/response'


idci_payment:
    gateway_configurations:
        sogenactif_bin_test:
            gateway_name: sogenactif_bin_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/sogenactif_bin_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                merchant_id: '014213245611111' # 3DSecure = 014213245611112
```

## Scellius

```yaml
# if you want to overide parameters used in scellius gateway (already configured by default)
parameters:
    idci_payment.scellius_bin.pathfile_path: "%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/param/scellius/pathfile.scellius"
    idci_payment.atos_sips_bin.request_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/request'
    idci_payment.atos_sips_bin.response_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/response'

idci_payment:
    gateway_configurations:
        scellius_bin_test:
            gateway_name: scellius_bin_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/scellius_bin_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                merchant_id: '011223344551111' # 3DSecure = 011223344551112
```

## Mercanet

```yaml
# if you want to overide parameters used in mercanet gateway (already configured by default)
parameters:
    idci_payment.mercanet_bin.pathfile_path: "%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/param/mercanet/pathfile.mercanet"
    idci_payment.atos_sips_bin.request_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/request'
    idci_payment.atos_sips_bin.response_bin_path: '%kernel.project_dir%/vendor/idci/payment-bundle/Resources/sips/atos/bin/static/response'

idci_payment:
    gateway_configurations:
        mercanet_bin_test:
            gateway_name: mercanet_bin_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/mercanet_bin_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                merchant_id: '011223344551111' # 3DSecure = 011223344551112
```

# Atos Sips JSON

## Mercanet

```yaml
# if you want to overide the host name used in mercanet gateway (already configured by default)
parameters:
    idci_payment.mercanet.server_host_name: payment-webinit.simu.mercanet.bnpparibas.net # prod: payment-webinit.mercanet.bnpparibas.net

idci_payment:
    gateway_configurations:
        mercanet_json_test:
            gateway_name: mercanet_json_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/mercanet_json_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                interface_version: 'IR_WS_2.20'
                merchant_id: '002001000000001'
                order_channel: 'INTERNET'
                version: 1
                secret: '002001000000001_KEY1'
```

## Sogenactif

```yaml
# if you want to overide the host name used in sogenactif gateway (already configured by default)
parameters:
    idci_payment.sogenactif.server_host_name: payment-webinit.simu.sips-atos.com # prod: payment-webinit-ws.sogenactif.com

idci_payment:
    gateway_configurations:
        sogenactif_json_test:
            gateway_name: sogenactif_json_atos_sips
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/sogenactif_json_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                interface_version: 'IR_WS_2.20'
                merchant_id: '002001000000001'
                order_channel: 'INTERNET'
                version: 1
                secret: '002001000000001_KEY1'
```

# Paypal

```yaml
idci_payment:
    gateway_configurations:
        paypal_test:
            gateway_name: paypal
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/paypal_test/callback # must be public
                client_id: [Available in your account]
                client_secret: [Available in your account]
                environment: sandbox # in production mode use 'live'
```

# Monetico (Unsupported yet)

# Ogone (Unsupported yet)

# SystemPay

```yaml
# if you want to overide the url used in systempay gateway (already configured by default)
parameters:
    idci_payment.systempay.server_url: https://paiement.systempay.fr/vads-payment/

idci_payment:
    gateway_configurations:
        systempay_test:
            gateway_name: systempay
            enabled: true
            parameters:
                return_url: http://front.maier.docker/
                callback_url: http://front.maier.docker/payment-gateway/sogenactif_bin_test/callback # must be public
                action_mode: INTERACTIVE
                ctx_mode: TEST
                page_action: PAYMENT
                payment_config: SINGLE # MULTI
                site_id: 12345678
                site_key: 20170701130025
                version: 'V2'
                signature_algorithm: 'SHA-1' # HMAC-SHA-256
```

# PayPlug

```yaml
idci_payment:
    gateway_configurations:
        payplug_test:
            gateway_name: payplug
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/payplug_test/callback # must be public
                secret_key: [Available in your account]
```

# PayBox

```yaml
# if you want to overide the parameters name used in paybox gateway (already configured by default)
parameters:
    idci_payment.paybox.server_host_name: preprod-tpeweb.paybox.com # prod: tpeweb.paybox.com
    idci_payment.paybox.key_path: /var/www/html/vendor/idci/payment-bundle/Resources/paybox/keys
    idci_payment.paybox.public_key_url: http://www1.paybox.com/wp-content/uploads/2014/03/pubkey.pem

idci_payment:
    gateway_configurations:
        paybox_test:
            gateway_name: paybox
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/paybox_test/callback # must be public
                client_id: '2'
                client_rang: '32'
                client_site: '1999888'
```

# Stripe

```yaml
idci_payment:
    gateway_configurations:
        stripe_test:
            gateway_name: stripe
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/stripe-payment-gateway/proxy/stripe_test/callback # must be public
                public_key: [Available in your account]
                secret_key: [Available in your account]
```
