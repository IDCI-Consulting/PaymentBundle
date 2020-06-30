<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\EurekaStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class EurekaPaymentGateway extends AbstractPaymentGateway
{
    const SCORE_V3 = 'v3'; // For 3x & 4x payment
    const SCORE_CCL = 'ccl'; // For 10x payment

    const SALE_CHANNEL_DESKTOP = 'DESKTOP';
    const SALE_CHANNEL_TABLET = 'TABLET';
    const SALE_CHANNEL_TABLED_IPAD = 'TABLET_IPAD';
    const SALE_CHANNEL_SMARTPHONE = 'SMARTPHONE';
    const SALE_CHANNEL_SMARTPHONE_ANDROID = 'SMARTPHONE_ANDROID';
    const SALE_CHANNEL_SMARTPHONE_IPHONE = 'SMARTPHONE_IPHONE';

    const SHIPPING_METHOD_COLISSIMO_DIRECT = 'CDS';
    const SHIPPING_METHOD_CHRONOPOST = 'CHR';
    const SHIPPING_METHOD_COLISSIMO = 'COL';
    const SHIPPING_METHOD_CHRONORELAIS = 'CRE';
    const SHIPPING_METHOD_KIALA = 'KIA';
    const SHIPPING_METHOD_IMPRESSION = 'IMP';
    const SHIPPING_METHOD_LIVRAISON_SERVICE_PLUS = 'LSP';
    const SHIPPING_METHOD_MORY = 'MOR';
    const SHIPPING_METHOD_RELAIS_CDISCOUNT = 'RCD';
    const SHIPPING_METHOD_TNT = 'TNT';
    const SHIPPING_METHOD_TRANSPORTEUR = 'TRP';
    const SHIPPING_METHOD_EASYDIS_ERREUR = 'AE1';
    const SHIPPING_METHOD_EASYDIS = 'EA1';
    const SHIPPING_METHOD_KIB = 'KIB';
    const SHIPPING_METHOD_TNT_BELGIQUE = 'TNB';
    const SHIPPING_METHOD_LIVRAISON_EXPRESS = 'EXP';
    const SHIPPING_METHOD_AGEDISS = 'AGE';
    const SHIPPING_METHOD_EMPORTE = 'EMP';
    const SHIPPING_METHOD_EMPORTE_MOINS_30 = 'M30';
    const SHIPPING_METHOD_ADREXO = 'ADX';
    const SHIPPING_METHOD_EMPORTE_MOINS_30_EASYDIS = 'EY1';
    const SHIPPING_METHOD_VIRTUEL = 'VIR';
    const SHIPPING_METHOD_RECOMMANDE = 'REG';
    const SHIPPING_METHOD_NORMAL = 'STD';
    const SHIPPING_METHOD_SUIVI = 'TRK';
    const SHIPPING_METHOD_PREMIUM_EASYDIS = 'PRM';
    const SHIPPING_METHOD_CONFORT_EASYDIS = 'RDO';
    const SHIPPING_METHOD_RELAIS_CESTAS = 'RCO';
    const SHIPPING_METHOD_SO_COLLISIMO_ZONE_1 = 'SO1';
    const SHIPPING_METHOD_SO_COLLISIMO_ZONE_2 = 'SO2';
    const SHIPPING_METHOD_RETRAIT_IMMEDIAT_MAGASIN = 'RIM';
    const SHIPPING_METHOD_LDR = 'LDR';
    const SHIPPING_METHOD_LIVRAISON_EN_MAGASIN = 'MAG';
    const SHIPPING_METHOD_ECO_EASYDIS = 'RDE';
    const SHIPPING_METHOD_MODIAL_RELAY = 'REL';
    const SHIPPING_METHOD_FOURNISSEUR_DIRECT_RELAIS = 'FDR';
    const SHIPPING_METHOD_TNT_EXPRESS_RELAIS = 'TNX';
    const SHIPPING_METHOD_EXPRESS = 'EMX';
    const SHIPPING_METHOD_EMPORTE_CHRONOPOST_RELAI = 'CHX';
    const SHIPPING_METHOD_EMPORTE_CHRONOPOST_CONSIGNE = 'CSX';

    const CIVILITY_MISTER = 'Mr';
    const CIVILITY_MISS = 'Ms';
    const CIVILITY_MISSTRESS = 'Mrs';

    const NATIONALITY_FRANCE = 'FR';
    const NATIONALITY_EUROPEAN_UNION = 'UE';
    const NATIONALITY_OTHER = 'HorsUE';

    const WHITELIST_STATUS_BLACKLIST = 'BLACKLIST';
    const WHITELIST_STATUS_UNKNOWN = 'UNKNOWN';
    const WHITELIST_STATUS_TRUSTED = 'TRUSTED';
    const WHITELIST_STATUS_WHITELIST = 'WHITELIST';

    const UNKNOWN_TRAVEL_TYPE = 'Unknown';
    const ONE_WAY_TRAVEL_TYPE = 'OneWay';
    const TWO_WAY_TRAVEL_TYPE = 'TwoWay';
    const MULTIPLE_TRAVEL_TYPE = 'Multiple';

    const UNKNOWN_TRAVEL_CLASS = 'Unknown';
    const ECONOMY_TRAVEL_CLASS = 'Economy';
    const PREMIUM_ECONOMY_TRAVEL_CLASS = 'PremiumEconomy';
    const BUSINESS_TRAVEL_CLASS = 'Business';
    const FIRST_TRAVEL_CLASS = 'First';
    const OTHER_TRAVEL_CLASS = 'Other';

    const HMAC_TYPE_ENTRY = 'in';
    const HMAC_TYPE_OUT = 'out';

    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        string $serverHostName
    ) {
        parent::__construct($templating);

        $this->serverHostName = $serverHostName;
        $this->client = new Client(['defaults' => ['verify' => false]]);
    }

    private function getSTSConnectionUrl(): string
    {
        return sprintf('https://%s/Users/soapIssue.svc', $this->serverHostName);
    }

    private function getMerchantUrl(): string
    {
        return sprintf('https://%s/MerchantGatewayFrontService.svc', $this->serverHostName);
    }

    private function getScoreV3Url(): string
    {
        return sprintf('https://%s/Cb4xFrontService.svc', $this->serverHostName);
    }

    private function getScoreCclUrl(): string
    {
        return sprintf('https://%s/CclFrontService.svc', $this->serverHostName);
    }

    private function getPaymentFormUrl(): string
    {
        return sprintf('https://%s/V4/GenericRD/Redirect.aspx', $this->serverHostName);
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        foreach ($this->getRequiredTransactionMetadata() as $requiredTransactionMetadata) {
            if (!$transaction->hasMetadata($requiredTransactionMetadata)) {
                throw new \UnexpectedValueException(
                    sprintf('The transaction metadata "%s" must be set', $requiredTransactionMetadata)
                );
            }
        }

        return [
            'version' => $paymentGatewayConfiguration->get('version'),
            'merchantID' => $paymentGatewayConfiguration->get('merchant_id'),
            'merchantSiteID' => $paymentGatewayConfiguration->get('merchant_site_id'),
            'secretKey' => $paymentGatewayConfiguration->get('secret_key'),
            'paymentOptionRef' => $paymentGatewayConfiguration->get('payment_option_reference'),
            'orderRef' => $transaction->getId(),
            'decimalPosition' => 2,
            'currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'country' => $transaction->getMetadata('order.country'),
            'customerRef' => $transaction->getCustomerId(),
            'date' => (new \DateTime('now'))->format('Ymd'),
            'amount' => $transaction->getAmount(),
            'merchantHomeUrl' => $paymentGatewayConfiguration->get('return_url'),
            'merchantReturnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'merchantNotifyUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'scoringToken' => $this->requestScoringToken($paymentGatewayConfiguration, $transaction),
        ];
    }

    private function requestHeaderToken(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration)
    {
        $response = $this->client->request('POST', $this->getSTSConnectionUrl(), [
            'timeout' => 10, // TEMP: for dev purpose only
            'body' => $this->templating->render('@IDCIPayment/Gateway/soap/eureka_sts_token.xml.twig', [
                'username' => $paymentGatewayConfiguration->get('username'),
                'password' => $paymentGatewayConfiguration->get('password'),
                'merchant_url' => $this->getMerchantUrl(),
            ]),
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
        ]);

        return (new Crawler($response->getBody()))->filter('IssueResult');
    }

    private function requestScoringToken(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
        $type = $paymentGatewayConfiguration->get('score_type');

        if (self::SCORE_V3 !== $type && self::SCORE_CCL !== $type) {
            throw new \InvalidArgumentException(
                sprintf('The scoring type "%s" is not supported. Supported values: %s, %s', $type, self::SCORE_V3, self::SCORE_CCL)
            );
        }

        $data = $this->templating->render(
            '@IDCIPayment/Gateway/soap/eureka_score.xml.twig',
            $this->resolveScoreOptions([
                'Header' => [
                    'Context' => [
                        'MerchantId' => $paymentGatewayConfiguration->get('merchant_id'),
                        'MerchantSiteId' => $paymentGatewayConfiguration->get('merchant_site_id'),
                    ],
                    'Localization' => [
                        'Country' => $transaction->getMetadata('Customer.Country'),
                        'Currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
                        'DecimalPosition' => $transaction->getMetadata('Order.DecimalPosition'),
                        'Language' => $transaction->getMetadata('Customer.Country'),
                    ],
                    'SecurityContext' => [
                        'TokenId' => $this->requestHeaderToken($paymentGatewayConfiguration),
                    ],
                    'Version' => $paymentGatewayConfiguration->get('version'),
                ],
                'Request' => [
                    'Customer' => [
                        'CustomerRef' => $transaction->getCustomerId(),
                        'LastName' => $transaction->getMetadata('Customer.LastName'),
                        'FirstName' => $transaction->getMetadata('Customer.FirstName'),
                        'Civility' => $transaction->getMetadata('Customer.Civility'),
                        'MaidenName' => $transaction->getMetadata('Customer.MaidenName'),
                        'BirthDate' => $transaction->getMetadata('Customer.BirthDate'),
                        'BirthZipCode' => $transaction->getMetadata('Customer.BirthZipCode'),
                        'PhoneNumber' => $transaction->getMetadata('Customer.PhoneNumber'),
                        'CellPhoneNumber' => $transaction->getMetadata('Customer.CellPhoneNumber'),
                        'Email' => $transaction->getCustomerEmail(),
                        'Address1' => $transaction->getMetadata('Customer.Address1'),
                        'Address2' => $transaction->getMetadata('Customer.Address2'),
                        'Address3' => $transaction->getMetadata('Customer.Address3'),
                        'Address4' => $transaction->getMetadata('Customer.Address4'),
                        'ZipCode' => $transaction->getMetadata('Customer.ZipCode'),
                        'City' => $transaction->getMetadata('Customer.City'),
                        'Country' => $transaction->getMetadata('Customer.Country'),
                        'Nationality' => $transaction->getMetadata('Customer.Nationality'),
                        'IpAddress' => $transaction->getMetadata('Customer.IpAddress'),
                        'WhiteList' => $transaction->getMetadata('Customer.WhiteList'),
                    ],
                    'Order' => [
                        'OrderDate' => (new \DateTime('now'))->format('Y-m-d'),
                        'SaleChannel' => $transaction->getMetadata('Order.SaleChannel'),
                        'ShippingMethod' => $transaction->getMetadata('Order.ShippingMethod'),
                        'ShoppingCartItemCount' => $transaction->getMetadata('Order.ShoppingCartItemCount'),
                        'ShoppingCartRef' => $transaction->getItemId(),
                        'TotalAmount' => $transaction->getAmount(),
                    ],
                    'OptionalCustomerHistory' => [
                        'CanceledOrderAmount' => $transaction->getMetadata('OptionalCustomerHistory.CanceledOrderAmount'),
                        'CanceledOrderCount' => $transaction->getMetadata('OptionalCustomerHistory.CanceledOrderCount'),
                        'FirstOrderDate' => $transaction->getMetadata('OptionalCustomerHistory.FirstOrderDate'),
                        'FraudAlertCount' => $transaction->getMetadata('OptionalCustomerHistory.FraudAlertCount'),
                        'LastOrderDate' => $transaction->getMetadata('OptionalCustomerHistory.LastOrderDate'),
                        'PaymentIncidentCount' => $transaction->getMetadata('OptionalCustomerHistory.PaymentIncidentCount'),
                        'RefusedManyTimesOrderCount' => $transaction->getMetadata('OptionalCustomerHistory.RefusedManyTimesOrderCount'),
                        'UnvalidatedOrderCount' => $transaction->getMetadata('OptionalCustomerHistory.UnvalidatedOrderCount'),
                        'ValidatedOneTimeOrderCount' => $transaction->getMetadata('OptionalCustomerHistory.ValidatedOneTimeOrderCount'),
                        'ValidatedOrderCount' => $transaction->getMetadata('OptionalCustomerHistory.ValidatedOrderCount'),
                        'ClientIpAddressRecurrence' => $transaction->getMetadata('OptionalCustomerHistory.ClientIpAddressRecurrence'),
                        'OngoingLitigationOrderAmount' => $transaction->getMetadata('OptionalCustomerHistory.OngoingLitigationOrderAmount'),
                        'PaidLitigationOrderAmount24Month' => $transaction->getMetadata('OptionalCustomerHistory.PaidLitigationOrderAmount24Months'),
                        'ScoreSimulationCount7Days' => $transaction->getMetadata('OptionalCustomerHistory.ScoreSimulationCount7Days'),
                    ],
                    'OptionalTravelDetails' => [
                        'Insurance' => $transaction->getMetadata('OptionalTravelDetails.Insurance'),
                        'Type' => $transaction->getMetadata('OptionalTravelDetails.Type'),
                        'DepartureDate' => $transaction->getMetadata('OptionalTravelDetails.DepartureDate'),
                        'ReturnDate' => $transaction->getMetadata('OptionalTravelDetails.ReturnDate'),
                        'DestinationCountry' => $transaction->getMetadata('OptionalTravelDetails.DestinationCountry'),
                        'TicketCount' => $transaction->getMetadata('OptionalTravelDetails.TicketCount'),
                        'TravellerCount' => $transaction->getMetadata('OptionalTravelDetails.TravellerCount'),
                        'Class' => $transaction->getMetadata('OptionalTravelDetails.Class'),
                        'OwnTicket' => $transaction->getMetadata('OptionalTravelDetails.OwnTicket'),
                        'MainDepartureCompany' => $transaction->getMetadata('OptionalTravelDetails.MainDepartureCompany'),
                        'DepartureAirport' => $transaction->getMetadata('OptionalTravelDetails.DepartureAirport'),
                        'ArrivalAirport' => $transaction->getMetadata('OptionalTravelDetails.ArrivalAirport'),
                        'DiscountCode' => $transaction->getMetadata('OptionalTravelDetails.DiscountCode'),
                        'LuggageSupplement' => $transaction->getMetadata('OptionalTravelDetails.LuggageSupplement'),
                        'ModificationAnnulation' => $transaction->getMetadata('OptionalTravelDetails.ModificationAnnulation'),
                        'TravellerPassportList' => $transaction->getMetadata('OptionalTravelDetails.TravellerPassportList'),
                    ],
                    'OptionalStayDetails' => [
                        'Company' => $transaction->getMetadata('OptionalStayDetails.Company'),
                        'Destination' => $transaction->getMetadata('OptionalStayDetails.Destination'),
                        'NightNumber' => $transaction->getMetadata('OptionalStayDetails.NightNumber'),
                        'RoomRange' => $transaction->getMetadata('OptionalStayDetails.RoomRange'),
                    ],
                    'OptionalProductDetails' => [
                        'Categorie1' => $transaction->getMetadata('OptionalProductDetails.Categorie1'),
                        'Categorie2' => $transaction->getMetadata('OptionalProductDetails.Categorie2'),
                        'Categorie3' => $transaction->getMetadata('OptionalProductDetails.Categorie3'),
                    ],
                    'PreScoreInformation' => [
                        'RequestID' => $transaction->getMetadata('PreScoreInformation.RequestID')
                    ],
                    'AdditionalNumericFieldList' => $transaction->getMetadata('AdditionalNumericFieldList'),
                    'AdditionalTextFieldList' => $transaction->getMetadata('AdditionalTextFieldList'),
                    'OptionalShippingDetails' => [
                        'ShippingAdress1' => $transaction->getMetadata('OptionalShippingDetails.ShippingAdress1'),
                        'ShippingAdress2' => $transaction->getMetadata('OptionalShippingDetails.ShippingAdress2'),
                        'ShippingAdressCity' => $transaction->getMetadata('OptionalShippingDetails.ShippingAdressCity'),
                        'ShippingAdressZip' => $transaction->getMetadata('OptionalShippingDetails.ShippingAdressZip'),
                        'ShippingAdressCountry' => $transaction->getMetadata('OptionalShippingDetails.ShippingAdressCountry'),
                    ],
                ],
            ])
        );

        $response = $this->client->request(
            'POST',
            $type === self::SCORE_V3 ? $this->getScoreV3Url() : $this->getScoreCclUrl(),
            [
                'timeout' => 10, // TEMP: for dev purpose only
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
            ]
        );

        $crawler = (new Crawler($response->getBody()));

        if ('Success' !== $crawler->filter('ResponseCode') || !$crawler->filter('PaymentAgreement')) {
            throw new \UnexpectedValueException('The scoring token request failed');
        }

        return $crawler->filter('ScoringToken');
    }

    private function buildHmac(array $options, string $hmacType): string
    {
        $hmacData = '';

        foreach ($this->getHmacBuildParameters($hmacType) as $parameterName) {
            if (isset($options[$parameterName])) {
                $hmacData = sprintf('%s*', $options[$parameterName]);
            }
        }

        return hash_hmac('sha1', utf8_encode($hmacData), $options['secretKey']);
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return array_merge($options, [
            'hmac' => $this->buildHmac($options, self::HMAC_TYPE_ENTRY),
        ]);
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/eureka.html.twig', [
            'url' => $this->getPaymentFormUrl(),
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('POST')) {
            throw new \UnexpectedValueException('Eureka : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setRaw($request->request->all())
        ;

        if (!$request->request->has('hmac')) {
            return $gatewayResponse->setMessage('The request do not contains "hmac"');
        }

        $hmac = $this->buildHmac($request->request->all(), self::HMAC_TYPE_OUT);

        if ($request->request->get('hmac') !== $hmac) {
            return $gatewayResponse->setMessage('Hmac check failed');
        }


        $gatewayResponse
            ->setTransactionUuid($request->request->get('orderRef'))
            ->setAmount($request->request->get('amount'))
            ->setCurrencyCode((new ISO4217())->findByNumeric($request->request->get('currency'))->getAlpha3())
        ;

        if ('0' !== $request->request->get('returnCode')) {
            $gatewayResponse->setMessage(EurekaStatusCode::getStatusMessage($returnParams['responseCode']));

            if ('6' === $returnParams['responseCode']) {
                $gatewayResponse->setStatus(PaymentStatus::STATUS_CANCELED);
            }

            return $gatewayResponse;
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'version',
                'merchant_id',
                'merchant_site_id',
                'payment_option_reference',
                'score_type',
                'username',
                'password',
                'secret_key',
            ]
        );
    }

    private function getHmacBuildParameters(string $hmacType): array
    {
        if (self::HMAC_TYPE_OUT === $hmacType) {
            return [
                'version',
                'merchantID',
                'merchantSiteID',
                'paymentOptionRef',
                'orderRef',
                'freeText',
                'decimalPosition',
                'currency',
                'country',
                'invoiceID',
                'customerRef',
                'date',
                'amount',
                'returnCode',
                'merchantAccountRef',
                'reportDelayInDays',
            ];
        }

        return [
            'version',
            'merchantID',
            'merchantSiteID',
            'paymentOptionRef',
            'orderRef',
            'freeText',
            'decimalPosition',
            'currency',
            'country',
            'invoiceID',
            'customerRef',
            'date',
            'amount',
            'orderRowsAmount',
            'orderFeesAmount',
            'orderDiscountAmount',
            'orderShippingCost',
            'allowCardStorage',
            'passwordRequired',
            'merchantAuthenticateUrl',
            'storedCardID1',
            'storedCardLabel1',
            'storedCardIDN',
            'storedCardLabelN',
            'merchantHomeUrl',
            'merchantBackUrl',
            'merchantReturnUrl',
            'merchantNotifyUrl',
        ];
    }

    private function getRequiredTransactionMetadata(): array
    {
        return [
            'Customer.LastName',
            'Customer.FirstName',
            'Customer.Civility',
            'Customer.BirthDate',
            'Customer.BirthZipCode',
            'Customer.PhoneNumber',
            'Customer.Address1',
            'Customer.ZipCode',
            'Customer.City',
            'Customer.Country',
            'Order.SaleChannel',
            'Order.ShippingMethod',
        ];
    }

    private function resolveScoreOptions(array $scoreOptions): array
    {
        $scoreResolver = (new OptionsResolver())
            ->setRequired([
                'Header',
                'Request',
            ])
            ->setAllowedTypes('Header', ['array'])
                ->setNormalizer('Header', function (Options $options, $value) {
                    return $this->resolveHeaderOptions($value);
                })
            ->setAllowedTypes('Request', ['array'])
                ->setNormalizer('Request', function (Options $options, $value) {
                    return $this->resolveRequestOptions($value);
                })
        ;

        return $scoreResolver->resolve($scoreOptions);
    }

    private function resolveHeaderOptions(array $headerOptions): array
    {
        $scoreResolver = (new OptionsResolver())
            ->setRequired([
                'Context',
                'Localization',
                'SecurityContext',
                'Version',
            ])
            ->setAllowedTypes('Context', ['array'])
                ->setNormalizer('Context', function (Options $options, $value) {
                    return $this->resolveContextOptions($value);
                })
            ->setAllowedTypes('Localization', ['array'])
                ->setNormalizer('Localization', function (Options $options, $value) {
                    return $this->resolveLocalizationOptions($value);
                })
            ->setAllowedTypes('SecurityContext', ['array'])
                ->setNormalizer('SecurityContext', function (Options $options, $value) {
                    return $this->resolveSecurityContextOptions($value);
                })
            ->setAllowedTypes('Version', ['int', 'string'])
        ;

        return $scoreResolver->resolve($scoreOptions);
    }

    private function resolveContextOptions($contextOptions): array
    {
        $contextResolver = (new OptionsResolver())
            ->setRequired([
                'MerchantId',
                'MerchantSiteId',
            ])
            ->setAllowedTypes('MerchantId', ['int', 'string'])
            ->setAllowedTypes('MerchantSiteId', ['int', 'string'])
        ;

        return $contextResolver->resolve($contextOptions);
    }

    private function resolveLocalizationOptions($localizationOptions): array
    {
        $localizationResolver = (new OptionsResolver())
            ->setRequired([
                'Country',
                'Currency',
                'DecimalPosition',
                'Language',
            ])
            ->setAllowedTypes('Country', ['string'])
            ->setAllowedTypes('Currency', ['string'])
                ->setAllowedValues('currency', array_map(function ($currency) {
                    return $currency->getAlpha3();
                }, (new ISO4217())->findAll()))
            ->setAllowedTypes('DecimalPosition', ['int'])
            ->setAllowedTypes('Language', ['string'])
        ;

        return $localizationResolver->resolve($localizationOptions);
    }

    private function resolveSecurityContextOptions($securityContextOptions): array
    {
        $securityContextResolver = (new OptionsResolver())
            ->setRequired([
                'TokenId',
            ])
            ->setAllowedTypes('TokenId', ['string'])
        ;

        return $securityContextResolver->resolve($securityContextOptions);
    }

    private function resolveRequestOptions(array $contextOptions): array
    {
        $contextResolver = (new OptionsResolver())
            ->setRequired([
                'Context',
                'Customer',
                'Order',
            ])
            ->setDefaults([
                'OptionalCustomerHistory' => [],
                'OptionalTravelDetails' => [],
                'OptionalStayDetails' => [],
                'OptionalProductDetails' => [],
                'PreScoreInformation' => [],
                'AdditionalNumericFieldList' => [],
                'AdditionalTextFieldList' => [],
                'OptionalShippingDetails' => [],
            ])
            ->setAllowedTypes('Customer', ['array'])
                ->setNormalizer('Customer', function (Options $options, $value) {
                    return $this->resolveCustomerOptions($value);
                })
            ->setAllowedTypes('Order', ['array'])
                ->setNormalizer('Order', function (Options $options, $value) {
                    return $this->resolveOrderOptions($value);
                })
            ->setAllowedTypes('OptionalCustomerHistory', ['array'])
                ->setNormalizer('OptionalCustomerHistory', function (Options $options, $value) {
                    return $this->resolveOptionalCustomerDetailsOptions($value);
                })
            ->setAllowedTypes('OptionalTravelDetails', ['array'])
                ->setNormalizer('OptionalTravelDetails', function (Options $options, $value) {
                    return $this->resolveOptionalTravelDetailsOptions($value);
                })
            ->setAllowedTypes('OptionalStayDetails', ['array'])
                ->setNormalizer('OptionalStayDetails', function (Options $options, $value) {
                    return $this->resolveOptionalStayDetailsOptions($value);
                })
            ->setAllowedTypes('OptionalProductDetails', ['array'])
                ->setNormalizer('OptionalProductDetails', function (Options $options, $value) {
                    return $this->resolveOptionalProductDetailsOptions($value);
                })
            ->setAllowedTypes('PreScoreInformation', ['array'])
                ->setNormalizer('PreScoreInformation', function (Options $options, $value) {
                    return $this->resolvePreScoreInformationOptions($value);
                })
            ->setAllowedTypes('AdditionalNumericFieldList', ['array'])
                ->setNormalizer('AdditionalNumericFieldList', function (Options $options, $value) {
                    return $this->resolveAdditionalFieldListOptions($value);
                })
            ->setAllowedTypes('AdditionalTextFieldList', ['array'])
                ->setNormalizer('AdditionalTextFieldList', function (Options $options, $value) {
                    return $this->resolveAdditionalFieldListOptions($value);
                })
            ->setAllowedTypes('OptionalShippingDetails', ['array'])
                ->setNormalizer('OptionalShippingDetails', function (Options $options, $value) {
                    return $this->resolveOptionalShippingDetailsOptions($value);
                })
        ;

        return $contextResolver->resolve($contextOptions);
    }

    private function resolveCustomerOptions(array $customerOptions): array
    {
        $customerResolver = (new OptionsResolver())
            ->setRequired([
                'CustomerRef',
                'LastName',
                'FirstName',
                'Civility',
                'BirthDate',
                'BirthZipCode',
                'PhoneNumber',
                'CellPhoneNumber',
                'Email',
                'Address1',
                'ZipCode',
                'City',
                'Country',
            ])
            ->setDefault([
                'MaidenName' => null,
                'Address2' => null,
                'Address3' => null,
                'Address4' => null,
                'Nationality' => null,
                'IpAddress' => null,
                'WhiteList' => null,
            ])
            ->setAllowedTypes('CustomerRef', ['int', 'string'])
                ->setNormalizer('CustomerRef', function (Options $options, $value) {
                    if(strlen((string) $value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.CustomerRef" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('LastName', ['string'])
                ->setNormalizer('LastName', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 64) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.LastName" max length is 64, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('FirstName', ['string'])
                ->setNormalizer('FirstName', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 64) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.FirstName" max length is 64, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Civility', ['string'])
                ->setAllowedValues('Civility', [
                    self::CIVILITY_MISTER,
                    self::CIVILITY_MISS,
                    self::CIVILITY_MISSTRESS,
                ])
            ->setAllowedTypes('MaidenName', ['null', 'string'])
                ->setNormalizer('MaidenName', function (Options $options, $value) {
                    if (self::CIVILITY_MISSTRESS === $options['Civility'] && null === $value) {
                        throw new \UnexpectedValueException(
                            sprintf(
                                'As the field "Customer.Civility" of the customer equal to "%s", "Customer.MaidenName" mustn\'t be null.',
                                self::CIVILITY_MISSTRESS
                            )
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('BirthDate', ['string', \DateTime::class])
                ->setNormalizer('BirthDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "Customer.BirthDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })

            ->setAllowedTypes('BirthZipCode', ['string'])
                ->setNormalizer('BirthZipCode', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.BirthZipCode" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('PhoneNumber', ['string'])
                ->setNormalizer('PhoneNumber', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 13) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.PhoneNumber" max length is 13, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('CellPhoneNumber', ['string'])
                ->setNormalizer('CellPhoneNumber', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 13) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.CellPhoneNumber" max length is 13, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Email', ['string'])
                ->setNormalizer('Email', function (Options $options, $value) {
                    if(strlen($value) > 60) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Email" max length is 60, current size given: %s', strlen($value))
                        );
                    }

                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException(
                            sprintf('The parameter given in "Customer.Email" is not a valid email (%s).', $value)
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address1', ['string'])
                ->setNormalizer('Address1', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address1" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address2', ['null', 'string'])
                ->setNormalizer('Address2', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address2" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address3', ['null', 'string'])
                ->setNormalizer('Address3', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address3" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address4', ['null', 'string'])
                ->setNormalizer('Address4', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address4" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ZipCode', ['string'])
                ->setNormalizer('ZipCode', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.ZipCode" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('City', ['string'])
                ->setNormalizer('City', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.City" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Country', ['string'])
            ->setAllowedTypes('Nationality', ['null', 'string'])
                ->setAllowedValues('Nationality', [
                    self::NATIONALITY_FRANCE,
                    self::NATIONALITY_EUROPEAN_UNION,
                    self::NATIONALITY_OTHER,
                ])
            ->setAllowedTypes('IpAddress', ['null', 'string'])
                ->setNormalizer('IpAddress', function (Options $options, $value) {
                    if (is_string($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                        throw new \InvalidArgumentException(
                            sprintf('The parameter given in "Customer.IpAddress" is not a IPv4 (%s).', $value)
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Whitelist', ['null', 'string'])
                ->setAllowedValues('Whitelist', [
                    self::WHITELIST_STATUS_BLACKLIST,
                    self::WHITELIST_STATUS_UNKNOWN,
                    self::WHITELIST_STATUS_TRUSTED,
                    self::WHITELIST_STATUS_WHITELIST,
                ])
        ;

        return $customerResolver->resolve($customerOptions);
    }

    private function resolveOrderOptions(array $orderOptions): array
    {
        $orderResolver = (new OptionsResolver())
            ->setRequired([
                'OrderDate',
                'SaleChannel',
                'ShippingMethod',
                'ShoppingCartItemCount',
                'ShoppingCartRef',
                'TotalAmount',
            ])
            ->setAllowedTypes('OrderDate', ['string'])
            ->setAllowedTypes('SaleChannel', ['string'])
                ->setAllowedValues('SaleChannel', [
                    self::SALE_CHANNEL_DESKTOP,
                    self::SALE_CHANNEL_TABLET,
                    self::SALE_CHANNEL_TABLED_IPAD,
                    self::SALE_CHANNEL_SMARTPHONE,
                    self::SALE_CHANNEL_SMARTPHONE_ANDROID,
                    self::SALE_CHANNEL_SMARTPHONE_IPHONE,
                ])
            ->setAllowedTypes('ShippingMethod', ['string'])
                ->setAllowedValues('ShippingMethod', [
                    self::SHIPPING_METHOD_COLISSIMO_DIRECT,
                    self::SHIPPING_METHOD_CHRONOPOST,
                    self::SHIPPING_METHOD_COLISSIMO,
                    self::SHIPPING_METHOD_CHRONORELAIS,
                    self::SHIPPING_METHOD_KIALA,
                    self::SHIPPING_METHOD_IMPRESSION,
                    self::SHIPPING_METHOD_LIVRAISON_SERVICE_PLUS,
                    self::SHIPPING_METHOD_MORY,
                    self::SHIPPING_METHOD_RELAIS_CDISCOUNT,
                    self::SHIPPING_METHOD_TNT,
                    self::SHIPPING_METHOD_TRANSPORTEUR,
                    self::SHIPPING_METHOD_EASYDIS_ERREUR,
                    self::SHIPPING_METHOD_EASYDIS,
                    self::SHIPPING_METHOD_KIB,
                    self::SHIPPING_METHOD_TNT_BELGIQUE,
                    self::SHIPPING_METHOD_LIVRAISON_EXPRESS,
                    self::SHIPPING_METHOD_AGEDISS,
                    self::SHIPPING_METHOD_EMPORTE,
                    self::SHIPPING_METHOD_EMPORTE_MOINS_30,
                    self::SHIPPING_METHOD_ADREXO,
                    self::SHIPPING_METHOD_EMPORTE_MOINS_30_EASYDIS,
                    self::SHIPPING_METHOD_VIRTUEL,
                    self::SHIPPING_METHOD_RECOMMANDE,
                    self::SHIPPING_METHOD_NORMAL,
                    self::SHIPPING_METHOD_SUIVI,
                    self::SHIPPING_METHOD_PREMIUM_EASYDIS,
                    self::SHIPPING_METHOD_CONFORT_EASYDIS,
                    self::SHIPPING_METHOD_RELAIS_CESTAS,
                    self::SHIPPING_METHOD_SO_COLLISIMO_ZONE_1,
                    self::SHIPPING_METHOD_SO_COLLISIMO_ZONE_2,
                    self::SHIPPING_METHOD_RETRAIT_IMMEDIAT_MAGASIN,
                    self::SHIPPING_METHOD_LDR,
                    self::SHIPPING_METHOD_LIVRAISON_EN_MAGASIN,
                    self::SHIPPING_METHOD_ECO_EASYDIS,
                    self::SHIPPING_METHOD_MODIAL_RELAY,
                    self::SHIPPING_METHOD_FOURNISSEUR_DIRECT_RELAIS,
                    self::SHIPPING_METHOD_TNT_EXPRESS_RELAIS,
                    self::SHIPPING_METHOD_EXPRESS,
                    self::SHIPPING_METHOD_EMPORTE_CHRONOPOST_RELAI,
                    self::SHIPPING_METHOD_EMPORTE_CHRONOPOST_CONSIGNE,
                ])
            ->setAllowedTypes('ShoppingCartItemCount', ['int'])
            ->setAllowedTypes('ShoppingCartRef', ['int', 'string'])
            ->setAllowedTypes('TotalAmount', ['int'])
        ;

        return $orderResolver->resolve($orderOptions);
    }

    private function resolveOptionalCustomerHistoryOptions(array $optionalCustomerHistory): array
    {
        $optionalCustomerHistoryResolver = (new OptionsResolver())
            ->setRequired([
                'CanceledOrderAmount',
                'CanceledOrderCount',
                'FirstOrderDate',
                'FraudAlertCount',
                'LastOrderDate',
                'PaymentIncidentCount',
                'RefusedManyTimesOrderCount',
                'UnvalidatedOrderCount',
                'ValidatedOneTimeOrderCount',
                'ValidatedOrderCount',
                'ClientIpAddressRecurrence',
                'OngoingLitigationOrderAmount',
                'PaidLitigationOrderAmount24Month',
                'ScoreSimulationCount7Days',
            ])
            ->setAllowedTypes('CanceledOrderAmount', ['null', 'int'])
            ->setAllowedTypes('CanceledOrderCount', ['null', 'int'])
            ->setAllowedTypes('FirstOrderDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('FirstOrderDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "OptionalCustomerHistory.FirstOrderDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('FraudAlertCount', ['null', 'int'])
            ->setAllowedTypes('LastOrderDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('LastOrderDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "OptionalCustomerHistory.LastOrderDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('PaymentIncidentCount', ['null', 'int'])
            ->setAllowedTypes('RefusedManyTimesOrderCount', ['null', 'int'])
            ->setAllowedTypes('UnvalidatedOrderCount', ['null', 'int'])
            ->setAllowedTypes('ValidatedOneTimeOrderCount', ['null', 'int'])
            ->setAllowedTypes('ValidatedOrderCount', ['null', 'int'])
            ->setAllowedTypes('ClientIpAddressRecurrence', ['null', 'int'])
            ->setAllowedTypes('OngoingLitigationOrderAmount', ['null', 'int'])
            ->setAllowedTypes('PaidLitigationOrderAmount24Month', ['null', 'int'])
            ->setAllowedTypes('ScoreSimulationCount7Days', ['null', 'int'])
        ;

        if (empty(array_diff(array_values($optionalCustomerHistory), ['null']))) {
            return null;
        }

        return $optionalCustomerHistoryResolver->resolve($optionalCustomerHistory);
    }

    private function resolveOptionalTravelDetailsOptions(array $optionalTravelDetails): array
    {
        $optionalTravelDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'Insurance',
                'Type',
                'DepartureDate',
                'ReturnDate',
                'DestinationCountry',
                'TicketCount',
                'TravellerCount',
                'Class',
                'OwnTicket',
                'MainDepartureCompany',
                'DepartureAirport',
                'ArrivalAirport',
                'DiscountCode',
                'LuggageSupplement',
                'ModificationAnnulation',
                'TravellerPassportList',
            ])
            ->setAllowedTypes('Insurance', ['null', 'string'])
                ->setNormalizer('Insurance', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.Insurance" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Type', ['null', 'string'])
                ->setAllowedValues('Type', [
                    self::UNKNOWN_TRAVEL_TYPE,
                    self::ONE_WAY_TRAVEL_TYPE,
                    self::TWO_WAY_TRAVEL_TYPE,
                    self::MULTIPLE_TRAVEL_TYPE,
                ])
            ->setAllowedTypes('DepartureDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('DepartureDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "OptionalTravelDetails.DepartureDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ReturnDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('ReturnDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "OptionalTravelDetails.ReturnDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DestinationCountry', ['null', 'string'])
                ->setNormalizer('DestinationCountry', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 2) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.DestinationCountry" max length is 2, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('TicketCount', ['null', 'int'])
            ->setAllowedTypes('TravellerCount', ['null', 'int'])
            ->setAllowedTypes('Class', ['null', 'string'])
                ->setAllowedValues('Class', [
                    self::UNKNOWN_TRAVEL_CLASS,
                    self::ECONOMY_TRAVEL_CLASS,
                    self::PREMIUM_ECONOMY_TRAVEL_CLASS,
                    self::BUSINESS_TRAVEL_CLASS,
                    self::FIRST_TRAVEL_CLASS,
                    self::OTHER_TRAVEL_CLASS,
                ])
            ->setAllowedTypes('OwnTicket', ['null', 'bool'])
            ->setAllowedTypes('MainDepartureCompany', ['null', 'string'])
                ->setNormalizer('MainDepartureCompany', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.MainDepartureCompany" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DepartureAirport', ['null', 'string'])
                ->setNormalizer('DepartureAirport', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.DepartureAirport" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ArrivalAirport', ['null', 'string'])
                ->setNormalizer('ArrivalAirport', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.ArrivalAirport" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DiscountCode', ['null', 'string'])
                ->setNormalizer('DiscountCode', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.DiscountCode" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('LuggageSupplement', ['null', 'string'])
                ->setNormalizer('LuggageSupplement', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.LuggageSupplement" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ModificationAnnulation', ['null', 'bool'])
            ->setAllowedTypes('TravellerPassportList', ['null', 'array'])
                ->setNormalizer('TravellerPassportList', function (Options $options, $value) {
                    return $this->resolveTravellerPassportListOptions($value);
                })
        ;

        if (empty(array_diff(array_values($optionalTravelDetails), ['null']))) {
            return null;
        }

        return $optionalTravelDetailsResolver->resolve($optionalTravelDetails);
    }

    private function resolveTravellerPassportListOptions(?array $travellerPassportList): array
    {
        if (null === $value || empty($value)) {
            return null;
        }

        $travellerPassportListResolver = (new OptionsResolver())
            ->setRequired([
                'ExpirationDate',
                'IssuanceCountry',
            ])
            >setAllowedTypes('ExpirationDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('ExpirationDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/')) {
                        throw new \InvalidArgumentException(
                            'The "OptionalTravelDetails.TravellerPassportList[].ExpirationDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('IssuanceCountry', ['null', 'string'])
                ->setNormalizer('IssuanceCountry', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 2) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.TravellerPassportList[]IssuanceCountry" max length is 2, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
        ;

        $resolvedTravellerPassportList = [];
        foreach ($value as $travellerPassport) {
            $resolvedTravellerPassportList[] = $travellerPassportListResolver->resolve($travellerPassport);
        }

        return $resolvedTravellerPassportList;
    }

    private function resolveOptionalStayDetailsOptions(array $optionalStayDetails): array
    {
        $optionalTravelDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'Company',
                'Destination',
                'NightNumber',
                'RoomRange',
            ])
            ->setAllowedTypes('Company', ['null', 'string'])
                ->setNormalizer('Company', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalStayDetails.Company" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Destination', ['null', 'string'])
                ->setNormalizer('Destination', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalStayDetails.Destination" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('NightNumber', ['null', 'int'])
            ->setAllowedTypes('RoomRange', ['null', 'int'])
        ;

        if (empty(array_diff(array_values($optionalStayDetails), ['null']))) {
            return null;
        }

        return $optionalStayDetailsResolver->resolve($optionalStayDetails);
    }

    private function resolveOptionalProductDetailsOptions(array $optionalStayDetails): array
    {
        $optionalTravelDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'Categorie1',
                'Categorie2',
                'Categorie3',
            ])
            ->setAllowedTypes('Categorie1', ['null', 'string'])
                ->setNormalizer('Categorie1', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie1" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Categorie2', ['null', 'string'])
                ->setNormalizer('Categorie2', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie2" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Categorie3', ['null', 'string'])
                ->setNormalizer('Categorie3', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie3" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
        ;

        if (empty(array_diff(array_values($optionalStayDetails), ['null']))) {
            return null;
        }

        return $optionalStayDetailsResolver->resolve($optionalStayDetails);
    }

    private function resolvePreScoreInformationOptions(array $preScoreInformation): array
    {
        $preScoreInformationResolver = (new OptionsResolver())
            ->setRequired([
                'RequestID',
            ])
            ->setAllowedTypes('RequestID', ['null', 'string'])
        ;

        if (empty(array_diff(array_values($preScoreInformation), ['null']))) {
            return null;
        }

        return $preScoreInformationResolver->resolve($preScoreInformation);
    }

    private function resolveAdditionalFieldListOptions(?array $additionalFieldList)
    {
        if(null === $additionalFieldList || empty($additionalFieldList)) {
            return null;
        }

        $additionalNumericFieldListResolver = (new OptionsResolver())
            ->setRequired([
                'Index',
                'Value',
            ])
        ;

        $additionalNumericFieldList = [];
        foreach ($additionalFieldList as $additionalNumericField) {
            $additionalNumericFieldList[] = $additionalNumericFieldListResolver->resolve($additionalNumericField);
        }

        return $additionalNumericFieldList;
    }

    private function resolveOptionalShippingDetailsOptions(array $orderShippingDetails): array
    {
        $orderShippingDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'ShippingAdress1',
                'ShippingAdress2',
                'ShippingAdressCity',
                'shipping_address_zip',
                'shipping_address_country',
            ])
            ->setAllowedTypes('ShippingAdress1', ['null', 'string'])
                ->setNormalizer('ShippingAdress1', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdress1" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdress2', ['null', 'string'])
                ->setNormalizer('ShippingAdress2', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdress2" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressCity', ['null', 'string'])
                ->setNormalizer('ShippingAdressCity', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressCity" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressZip', ['null', 'string'])
                ->setNormalizer('ShippingAdressZip', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressZip" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressCountry', ['null', 'string'])
                ->setNormalizer('ShippingAdressCountry', function (Options $options, $value) {
                    if(is_string($value) && strlen($value) > 2) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressCountry" max length is 2, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
        ;

        if (empty(array_diff(array_values($orderShippingDetails), ['null']))) {
            return null;
        }

        return $orderShippingDetailsResolver->resolve($orderShippingDetails);
    }
}
