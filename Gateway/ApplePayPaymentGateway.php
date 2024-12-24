<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use IDCI\Bundle\PaymentBundle\Gateway\Event\ApplePayPaymentGatewayEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Event\ApplePayPaymentGatewayEvents;
use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Event\OneClickContextEvents;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use PayU\ApplePay\ApplePayDecodingServiceFactory;
use PayU\ApplePay\ApplePayValidator;
use PayU\ApplePay\Exception\DecodingFailedException;
use PayU\ApplePay\Exception\InvalidFormatException;
use Payum\ISO4217\ISO4217;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class ApplePayPaymentGateway extends AbstractPaymentGateway
{
    public const MODE_TRANSACTION = 'transaction';
    public const MODE_ONE_CLICK = 'one_click';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $rootCaFilePath;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        RequestStack $requestStack,
        LoggerInterface $logger,
        string $rootCaFilePath
    ) {
        parent::__construct($templating, $dispatcher);

        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->rootCaFilePath = $rootCaFilePath;
    }

    public function createSession(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        string $validationUrl
    ): ?string {
        $merchantIdentityCertificate = tmpfile();
        if (!\is_resource($merchantIdentityCertificate)) {
            $this->logger->error('Unable to create a temporary file for merchant identity certificate.');

            return null;
        }

        try {
            fwrite($merchantIdentityCertificate, str_replace('\n', PHP_EOL, $paymentGatewayConfiguration->get('merchant_identity_certificate')));

            $response = (new Client())->request('POST', $validationUrl, [
                'json' => [
                    'merchantIdentifier' => $paymentGatewayConfiguration->get('merchant_identifier'),
                    'displayName' => $paymentGatewayConfiguration->get('display_name'),
                    'initiative' => 'web',
                    'initiativeContext' => $this->requestStack->getMainRequest()->getHost()
                ],
                'cert' => stream_get_meta_data($merchantIdentityCertificate)['uri']
            ]);
        } catch (RequestException $e) {
            $this->logger->error(null !== $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage());

            fclose($merchantIdentityCertificate);

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            fclose($merchantIdentityCertificate);

            return null;
        }

        fclose($merchantIdentityCertificate);

        return (string) $response->getBody();
    }

    public function decryptPaymentToken(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array $paymentData
    ): ?array {
        if (!class_exists(ApplePayDecodingServiceFactory::class)) {
            throw new \LogicException('ApplePayPaymentGateway requires "payu/apple-pay" package to decrypt payment token');
        }

        if (!$paymentGatewayConfiguration->get('token_self_decrypt')) {
            throw new \LogicException('You must enable the "token_self_decrypt" to allow payment token self decryption. Becareful, you must be PCI-compliant to enable it.');
        }

        $applePayDecodingServiceFactory = new ApplePayDecodingServiceFactory();
        $applePayDecodingService = $applePayDecodingServiceFactory->make();
        $applePayValidator = new ApplePayValidator();

        $decodedPaymentData = [];
        try {
            $applePayValidator->validatePaymentDataStructure($paymentData);
            $decodedToken = $applePayDecodingService->decode(
                $paymentGatewayConfiguration->get('payment_processing_private_key'),
                $paymentGatewayConfiguration->get('merchant_identifier'),
                $paymentData,
                $this->rootCaFilePath,
                $paymentGatewayConfiguration->get('token_signature_duration')
            );

            $decodedPaymentData = [
                'version' => $decodedToken->getVersion(),
                'applicationPrimaryAccountNumber' => $decodedToken->getApplicationPrimaryAccountNumber(),
                'applicationExpirationDate' => $decodedToken->getApplicationExpirationDate(),
                'currencyCode' => $decodedToken->getCurrencyCode(),
                'transactionAmount' => $decodedToken->getTransactionAmount(),
                'deviceManufacturerIdentifier' => $decodedToken->getDeviceManufacturerIdentifier(),
                'paymentDataType' => $decodedToken->getPaymentDataType(),
                'onlinePaymentCryptogram' => $decodedToken->getOnlinePaymentCryptogram(),
                'eciIndicator' => $decodedToken->getEciIndicator(),
            ];
        } catch(DecodingFailedException $e) {
            $this->logger->error('Decoding failed:'. $e->getMessage());

            return null;
        } catch(InvalidFormatException $e) {
            $this->logger->critical('Invalid format:'. $e->getMessage());

            return null;
        }

        return $decodedPaymentData;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        return [
            'version' => $paymentGatewayConfiguration->get('version'),
            'apple_pay_payment_request' => $this->resolveInitializationOptions(
                $paymentGatewayConfiguration,
                $transaction,
                $options
            ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction, $options);

        return $this->templating->render('@IDCIPayment/Gateway/apple_pay.html.twig', $initializationData);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return new GatewayResponse();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the request method is not POST
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('POST')) {
            throw new \UnexpectedValueException('Apple pay : Payment Gateway error (Request method should be POST)');
        }

        $data = json_decode($request->getContent(), true);
        $paymentToken = $data['paymentToken'];
        $applicationData = json_decode($data['paymentRequest']['applicationData'] ?? '{}', true);

        if (self::MODE_ONE_CLICK === $applicationData['mode']) {
            $this->dispatcher->dispatch(new OneClickContextEvent($request, $paymentGatewayConfiguration, $data), OneClickContextEvents::APPLE_PAY);
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setTransactionUuid($applicationData['transaction_id'] ?? null)
            ->setCurrencyCode($data['paymentRequest']['currencyCode'])
            ->setAmount(((float) $data['paymentRequest']['total']['amount']) * 100)
            ->setPaymentMethod($paymentToken['paymentMethod']['network'])
            ->setRaw($data)
        ;

        if (true === $paymentGatewayConfiguration->get('token_self_decrypt')) {
            $data['decodedPaymentData'] = $this->decryptPaymentToken($paymentGatewayConfiguration, $paymentToken['paymentData']);

            $gatewayResponse
                ->setAmount($data['decodedPaymentData']['transactionAmount'])
                ->setCurrencyCode((new ISO4217())->findByNumeric($data['decodedPaymentData']['currencyCode'])->getAlpha3())
            ;

            $this->dispatcher->dispatch(new ApplePayPaymentGatewayEvent($request, $paymentGatewayConfiguration, $gatewayResponse, $data), ApplePayPaymentGatewayEvents::SEND_MPI_DATA);
        } else {
            $this->dispatcher->dispatch(new ApplePayPaymentGatewayEvent($request, $paymentGatewayConfiguration, $gatewayResponse, $data), ApplePayPaymentGatewayEvents::SEND_PSP_DATA);
        }

        return $gatewayResponse;
    }

    private function resolveInitializationOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options
    ): array {
        // @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentrequest
        $resolver = (new OptionsResolver())
            ->setDefault('merchantCapabilities', $paymentGatewayConfiguration->get('merchant_capabilities'))->setAllowedTypes('merchantCapabilities', ['array'])
            ->setDefault('supportedNetworks', $paymentGatewayConfiguration->get('supported_networks'))->setAllowedTypes('supportedNetworks', ['array'])
            ->setDefault('countryCode', $paymentGatewayConfiguration->get('country_code'))->setAllowedValues('countryCode', Countries::getCountryCodes())
            ->setDefault('requiredBillingContactFields', $paymentGatewayConfiguration->get('required_billing_contact_fields'))->setAllowedTypes('requiredBillingContactFields', ['null', 'array'])
            ->setDefault('requiredShippingContactFields', $paymentGatewayConfiguration->get('required_shipping_contact_fields'))->setAllowedTypes('requiredShippingContactFields', ['null', 'array'])
            ->setDefault('billingContact', function (OptionsResolver $billingContactResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['billingContact'])) {
                    return;
                }

                $billingContactResolver
                    ->setDefined('phoneNumber')->setAllowedTypes('phoneNumber', ['string'])
                    ->setDefined('emailAddress')->setAllowedTypes('emailAddress', ['string'])
                    ->setDefined('givenName')->setAllowedTypes('givenName', ['string'])
                    ->setDefined('familyName')->setAllowedTypes('familyName', ['string'])
                    ->setDefined('phoneticGivenName')->setAllowedTypes('phoneticGivenName', ['string'])
                    ->setDefined('phoneticFamilyName')->setAllowedTypes('phoneticFamilyName', ['string'])
                    ->setDefined('addressLines')->setAllowedTypes('addressLines', ['array'])
                    ->setDefined('subLocality')->setAllowedTypes('subLocality', ['string'])
                    ->setDefined('locality')->setAllowedTypes('locality', ['string'])
                    ->setDefined('postalCode')->setAllowedTypes('postalCode', ['string'])
                    ->setDefined('subAdministrativeArea')->setAllowedTypes('subAdministrativeArea', ['string'])
                    ->setDefined('administrativeArea')->setAllowedTypes('administrativeArea', ['string'])
                    ->setDefined('country')->setAllowedTypes('country', ['string'])
                    ->setDefined('countryCode')->setAllowedValues('countryCode', Countries::getCountryCodes())
                ;
            })
            ->setDefault('shippingContact', function (OptionsResolver $shippingContactResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['shippingContact'])) {
                    return;
                }

                $shippingContactResolver
                    ->setDefined('phoneNumber')->setAllowedTypes('phoneNumber', ['string'])
                    ->setDefined('emailAddress')->setAllowedTypes('emailAddress', ['string'])
                    ->setDefined('givenName')->setAllowedTypes('givenName', ['string'])
                    ->setDefined('familyName')->setAllowedTypes('familyName', ['string'])
                    ->setDefined('phoneticGivenName')->setAllowedTypes('phoneticGivenName', ['string'])
                    ->setDefined('phoneticFamilyName')->setAllowedTypes('phoneticFamilyName', ['string'])
                    ->setDefined('addressLines')->setAllowedTypes('addressLines', ['array'])
                    ->setDefined('subLocality')->setAllowedTypes('subLocality', ['string'])
                    ->setDefined('locality')->setAllowedTypes('locality', ['string'])
                    ->setDefined('postalCode')->setAllowedTypes('postalCode', ['string'])
                    ->setDefined('subAdministrativeArea')->setAllowedTypes('subAdministrativeArea', ['string'])
                    ->setDefined('administrativeArea')->setAllowedTypes('administrativeArea', ['string'])
                    ->setDefined('country')->setAllowedTypes('country', ['string'])
                    ->setDefined('countryCode')->setAllowedValues('countryCode', Countries::getCountryCodes())
                ;
            })
            ->setDefault('applicationData', '{}')->setAllowedTypes('applicationData', ['string', 'array'])
                ->setNormalizer('applicationData', function (Options $options, $applicationData) use ($paymentGatewayConfiguration, $transaction) {
                    if (!is_array($applicationData)) {
                        $applicationData = json_decode($applicationData, true);

                        if (null === $applicationData) {
                            throw new \LogicException('The parameter applicationData must be json if a string is passed.');
                        }
                    }

                    $applicationData = array_merge([
                        'transaction_id' => $transaction->getId(),
                        'mode' => $paymentGatewayConfiguration->get('mode')
                    ], $applicationData);

                    return json_encode($applicationData);
                })
            ->setDefined('supportedCountries')->setAllowedValues('supportedCountries', Countries::getCountryCodes())
            ->setDefined('supportsCouponCode')->setAllowedTypes('supportsCouponCode', ['bool'])
            ->setDefined('couponCode')->setAllowedTypes('couponCode', ['string'])
            ->setDefined('shippingContactEditingMode')->setAllowedTypes('shippingContactEditingMode', ['array'])
            ->setDefault('total', function (OptionsResolver $totalResolver) use ($paymentGatewayConfiguration, $transaction) {
                $totalResolver
                    ->setDefined('type')->setAllowedTypes('type', ['string'])
                    ->setDefault('label', $transaction->getItemId())->setAllowedTypes('label', ['string'])
                    ->setDefault('amount', (string) ($transaction->getAmount() / 100))->setAllowedTypes('amount', ['string'])
                    ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                    ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                    ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                    ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                    ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                    ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                    ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                ;
            })
            ->setDefined('lineItems')->setAllowedTypes('lineItems', ['array'])
                ->setNormalizer('lineItems', function (Options $options, $lineItems) {
                    if (!isset($options['lineItems'])) {
                        return;
                    }

                    $lineItemResolver = (new OptionsResolver())
                        ->setDefined('type')->setAllowedTypes('type', ['string'])
                        ->setRequired('label')->setAllowedTypes('label', ['string'])
                        ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                        ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                        ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                        ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                        ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                        ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                        ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                        ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                    ;

                    foreach ($lineItems as &$lineItem) {
                        $lineItem = array_filter($lineItemResolver->resolve($lineItem));
                    }

                    return $lineItems;
                })
            ->setDefault('currencyCode', $transaction->getCurrencyCode())->setAllowedTypes('currencyCode', ['string'])
            ->setDefined('shippingType')->setAllowedTypes('shippingType', ['string'])
            ->setDefined('shippingMethods')->setAllowedTypes('shippingMethods', ['array'])
                ->setNormalizer('shippingMethods', function (Options $options, $shippingMethods) {
                    foreach ($shippingMethods as &$shippingMethod) {
                        $shippingMethodResolver = (new OptionsResolver())
                            ->setDefined('label')->setAllowedTypes('label', ['string'])
                            ->setDefined('detail')->setAllowedTypes('detail', ['string'])
                            ->setDefined('amount')->setAllowedTypes('amount', ['string'])
                            ->setDefined('identifier')->setAllowedTypes('identifier', ['string'])
                            ->setDefault('dateComponentsRange', function (OptionsResolver $dateComponentsRangeResolver) use ($shippingMethod) {
                                if (!isset($shippingMethod['dateComponentsRange'])) {
                                    return;
                                }

                                $dateComponentsRangeResolver
                                    ->setDefault('startDateComponents', function (OptionsResolver $startDateComponentsResolver) {
                                        $startDateComponentsResolver
                                            ->setDefined('years')->setAllowedTypes('years', ['int'])
                                            ->setDefined('months')->setAllowedTypes('months', ['int'])
                                            ->setDefined('days')->setAllowedTypes('days', ['int'])
                                            ->setDefined('hours')->setAllowedTypes('hours', ['int'])
                                        ;
                                    })
                                    ->setDefault('endDateComponents', function (OptionsResolver $startDateComponentsResolver) {
                                        $startDateComponentsResolver
                                            ->setDefined('years')->setAllowedTypes('years', ['int'])
                                            ->setDefined('months')->setAllowedTypes('months', ['int'])
                                            ->setDefined('days')->setAllowedTypes('days', ['int'])
                                            ->setDefined('hours')->setAllowedTypes('hours', ['int'])
                                        ;
                                    })
                                ;
                            })
                        ;

                        $shippingMethod = array_filter($shippingMethodResolver->resolve($shippingMethod));
                    }

                    return $shippingMethods;
                })
            ->setDefault('multiTokenContexts', function (OptionsResolver $multiTokenContextsResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['multiTokenContexts'])) {
                    return;
                }

                $multiTokenContextsResolver
                    ->setRequired('merchantIdentifier')->setAllowedTypes('merchantIdentifier', ['string'])
                    ->setRequired('externalIdentifier')->setAllowedTypes('externalIdentifier', ['string'])
                    ->setRequired('merchantName')->setAllowedTypes('merchantName', ['string'])
                    ->setDefined('merchantDomain')->setAllowedTypes('merchantDomain', ['string'])
                    ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                ;
            })
            ->setDefault('automaticReloadPaymentRequest', function (OptionsResolver $automaticReloadPaymentRequestResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['automaticReloadPaymentRequest'])) {
                    return;
                }

                $automaticReloadPaymentRequestResolver
                    ->setRequired('paymentDescription')->setAllowedTypes('paymentDescription', ['string'])
                    ->setDefault('automaticReloadBilling', function (OptionsResolver $automaticReloadBillingResolver) {
                        $automaticReloadBillingResolver
                            ->setDefined('type')->setAllowedTypes('type', ['string'])
                            ->setRequired('label')->setAllowedTypes('label', ['string'])
                            ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                            ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                            ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                            ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                            ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                            ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                            ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                            ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                        ;
                    })
                    ->setDefined('billingAgreement')->setAllowedTypes('billingAgreement', ['string'])
                    ->setRequired('managementURL')->setAllowedTypes('managementURL', ['string'])
                    ->setDefined('tokenNotificationURL')->setAllowedTypes('tokenNotificationURL', ['string'])
                ;
            })
            ->setDefault('recurringPaymentRequest', function (OptionsResolver $recurringPaymentRequestResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['recurringPaymentRequest'])) {
                    return;
                }

                $recurringPaymentRequestResolver
                    ->setRequired('paymentDescription')->setAllowedTypes('paymentDescription', ['string'])
                    ->setDefault('regularBilling', function (OptionsResolver $regularBillingResolver) { // Required
                        $regularBillingResolver
                            ->setDefined('type')->setAllowedTypes('type', ['string'])
                            ->setRequired('label')->setAllowedTypes('label', ['string'])
                            ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                            ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                            ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                            ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                            ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                            ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                            ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                            ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                        ;
                    })
                    ->setDefault('trialBilling', function (OptionsResolver $trialBillingResolver) use ($options) {
                        if (!isset($options['recurringPaymentRequest']['trialBilling'])) {
                            return;
                        }

                        $trialBillingResolver
                            ->setDefined('type')->setAllowedTypes('type', ['string'])
                            ->setRequired('label')->setAllowedTypes('label', ['string'])
                            ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                            ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                            ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                            ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                            ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                            ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                            ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                            ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                        ;
                    })
                    ->setDefined('billingAgreement')->setAllowedTypes('billingAgreement', ['string'])
                    ->setRequired('managementURL')->setAllowedTypes('managementURL', ['string'])
                    ->setDefined('tokenNotificationURL')->setAllowedTypes('tokenNotificationURL', ['string'])
                ;
            })
            ->setDefault('deferredPaymentRequest', function (OptionsResolver $deferredPaymentRequestResolver) use ($paymentGatewayConfiguration, $transaction) {
                if (!isset($options['deferredPaymentRequest'])) {
                    return;
                }

                $deferredPaymentRequestResolver
                    ->setDefined('billingAgreement')->setAllowedTypes('billingAgreement', ['string'])
                    ->setDefault('deferredBilling', function (OptionsResolver $deferredBillingResolver) {
                        $deferredBillingResolver
                            ->setDefined('type')->setAllowedTypes('type', ['string'])
                            ->setRequired('label')->setAllowedTypes('label', ['string'])
                            ->setRequired('amount')->setAllowedTypes('amount', ['string'])
                            ->setDefined('paymentTiming')->setAllowedTypes('paymentTiming', ['string'])
                            ->setDefined('recurringPaymentStartDate')->setAllowedTypes('recurringPaymentStartDate', ['string'])
                            ->setDefined('recurringPaymentIntervalUnit')->setAllowedTypes('recurringPaymentIntervalUnit', ['string'])
                            ->setDefined('recurringPaymentIntervalCount')->setAllowedTypes('recurringPaymentIntervalCount', ['int'])
                            ->setDefined('recurringPaymentEndDate')->setAllowedTypes('recurringPaymentEndDate', ['string'])
                            ->setDefined('deferredPaymentDate')->setAllowedTypes('deferredPaymentDate', ['string'])
                            ->setDefined('automaticReloadPaymentThresholdAmount')->setAllowedTypes('automaticReloadPaymentThresholdAmount', ['string'])
                        ;
                    })
                    ->setDefined('freeCancellationDate')->setAllowedTypes('freeCancellationDate', ['string']) // Date
                    ->setDefined('freeCancellationDateTimeZone')->setAllowedTypes('freeCancellationDateTimeZone', ['string'])
                    ->setRequired('managementURL')->setAllowedTypes('managementURL', ['string'])
                    ->setRequired('paymentDescription')->setAllowedTypes('paymentDescription', ['string'])
                    ->setDefined('tokenNotificationURL')->setAllowedTypes('tokenNotificationURL', ['string'])
                ;
            })
        ;

        return array_filter($resolver->resolve($options));
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'version',
                'mode',
                'merchant_identifier',
                'display_name',
                'country_code',
                'merchant_capabilities',
                'supported_networks',
                'required_billing_contact_fields',
                'required_shipping_contact_fields',
                'merchant_identity_certificate',
                'payment_processing_private_key',
                'token_signature_duration',
                'token_self_decrypt',
            ]
        );
    }
}
