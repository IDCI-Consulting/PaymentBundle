TEST PARAMETERS FOR PAYMENT GATEWAYS
------------------------------------

# Atos Sips Post

## Mercanet

```yaml
idci_payment:
    gateway_configurations:
        mercanet_post_test:
            gateway_name: atos_sips_post
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
idci_payment:
    gateway_configurations:
        sogenactif_post_test:
            gateway_name: atos_sips_post
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
idci_payment:
    gateway_configurations:
        sogenactif_bin_test:
            gateway_name: atos_sips_bin
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
idci_payment:
    gateway_configurations:
        scellius_bin_test:
            gateway_name: atos_sips_bin
            enabled: true
            parameters:
                return_url: http://www.example.com/
                callback_url: http://[your server host]/payment-gateway/scellius_bin_test/callback # must be public
                capture_day: 0
                capture_mode: 'AUTHOR_CAPTURE'
                merchant_id: '011223344551111' # 3DSecure = 011223344551112
```

# Atos Sips JSON

## Mercanet

```yaml
idci_payment:
    gateway_configurations:
        mercanet_json_test:
            gateway_name: atos_sips_json
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
idci_payment:
    gateway_configurations:
        sogenactif_json_test:
            gateway_name: atos_sips_json
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
