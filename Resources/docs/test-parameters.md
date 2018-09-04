TEST PARAMETERS FOR PAYMENT GATEWAYS
------------------------------------

# Common parameters

- return_url: ANY (in payment step it is not used)
- callback_url: [your server url]/payment-gateway/[gateway_configuration_name]/callback

# Atos Sips Post
## Mercanet

- secret: 002001000000001_KEY1
- version: 1
- capture_day: 0
- capture_mode: AUTHOR_CAPTURE
- order_channel: INTERNET
- interfaceVersion: HP_2.20
- merchant_id: 002001000000001

## Sogenactif

- secret: 002001000000001_KEY1
- version: 1
- capture_day: 0
- capture_mode: AUTHOR_CAPTURE
- order_channel: INTERNET
- interfaceVersion: HP_2.20
- merchant_id: 002001000000001

# Atos Sips Bin
## Sogenactif

- merchant_id: 014213245611111 (3DSecure: 014213245611112)
- (optional) capture_day: 0
- (optional) capture_mode: AUTHOR_CAPTURE

## Scellius

- merchant_id: 011223344551111 (3DSecure: 011223344551112)
- (optional) capture_day: 0
- (optional) capture_mode: AUTHOR_CAPTURE


# Atos Sips JSON
## Mercanet

- capture_day: 0
- capture_mode: AUTHOR_CAPTURE
- interface_version: IR_WS_2.20
- merchant_id: 002001000000001
- order_channel: INTERNET
- version: 1
- secret: 002001000000001_KEY1

## Sogenactif

- capture_day: 0
- capture_mode: AUTHOR_CAPTURE
- interface_version: IR_WS_2.20
- merchant_id: 002001000000001
- order_channel: INTERNET
- version: 1
- secret: 002001000000001_KEY1

# Paypal
- client_id: [On your account]
- client_secret: [On your account]
- environment: sandbox

# Monetico
- version:
- secret
- TPE:
- societe:

# Ogone

# PayPlug
- secret_key: [on your account]

# PayBox
- client_id: 2
- client_rang: 32
- client_site: 1999888

# Stripe
- public_key: [on your account]
- secret_key: [on your account]
