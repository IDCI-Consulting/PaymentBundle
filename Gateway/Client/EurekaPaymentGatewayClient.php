<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Client;

use GuzzleHttp\Client;
use Payum\ISO4217\ISO4217;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class EurekaPaymentGatewayClient
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

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(Environment $templating, string $serverHostName)
    {
        $this->templating = $templating;
        $this->client = new Client(['defaults' => ['verify' => false, 'timeout' => 5]]);
        $this->serverHostName = $serverHostName;
    }

    public function getSTSConnectionUrl(): string
    {
        return sprintf('https://paymentsts.%s/Users/soapIssue.svc', $this->serverHostName);
    }

    public function getMerchantUrl(): string
    {
        return sprintf('https://paymentservices.%s/MerchantGatewayFrontService.svc', $this->serverHostName);
    }

    public function getScoreV3Url(): string
    {
        return sprintf('https://services.%s/Cb4xFrontService.svc', $this->serverHostName);
    }

    public function getScoreCclUrl(): string
    {
        return sprintf('https://services.%s/CclFrontService.svc', $this->serverHostName);
    }

    public function getPaymentFormUrl(): string
    {
        return sprintf('https://payment.%s/V4/GenericRD/Redirect.aspx', $this->serverHostName);
    }

    public function getSTSTokenResponse(string $username, string $password)
    {
        $wsdl = $this->client->request('GET', sprintf('%s?singleWsdl', $this->getSTSConnectionUrl()));
        $soapAction = (new Crawler((string) $wsdl->getBody()))
            ->filterXPath('//wsdl:operation[@name="Issue"]//soap:operation')
            ->attr('soapAction')
        ;

        return $this->client->request('POST', $this->getSTSConnectionUrl(), [
            'body' => $this->templating->render('@IDCIPayment/Gateway/eureka/sts_token.xml.twig', [
                'username' => $username,
                'password' => $password,
                'merchant_url' => $this->getMerchantUrl(),
            ]),
            'headers' => [
                'Content-Type' => 'text/xml',
                'SOAPAction' => $soapAction,
            ],
        ]);
    }

    public function getSTSToken(string $username, string $password)
    {
        return (new Crawler((string) $this->getSTSTokenResponse($username, $password)->getBody()))->filterXPath('//issueresult')->text();
    }

    public function getScoringTokenResponse(string $type, array $options)
    {
        if (self::SCORE_V3 !== $type && self::SCORE_CCL !== $type) {
            throw new \InvalidArgumentException(
                sprintf('The scoring type "%s" is not supported. Supported values: %s, %s', $type, self::SCORE_V3, self::SCORE_CCL)
            );
        }

        $wsdl = $this->client->request('GET', sprintf('%s?singleWsdl', self::SCORE_V3 === $type ? $this->getScoreV3Url() : $this->getScoreCclUrl()));
        $soapAction = (new Crawler((string) $wsdl->getBody()))
            ->filterXPath('//wsdl:operation[@name="Score"]//soap:operation')
            ->attr('soapAction')
        ;

        return $this->client->request(
            'POST',
            self::SCORE_V3 === $type ? $this->getScoreV3Url() : $this->getScoreCclUrl(),
            [
                'body' => $this->templating->render(
                    '@IDCIPayment/Gateway/eureka/score.xml.twig',
                    $this->resolveScoreOptions($options)
                ),
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'SoapAction' => $soapAction,
                ],
            ]
        );
    }

    public function getScoringToken(string $type, array $options)
    {
        $crawler = (new Crawler((string) $this->getScoringTokenResponse($type, $options)->getBody()));

        if ('false' === $crawler->filterXPath('//paymentagreement')->text()) {
            throw new \UnexpectedValueException(
                sprintf('The scoring token request failed: %s', $crawler->filterXPath('//responsemessage')->text())
            );
        }

        $scoringToken = $crawler->filterXPath('//scoringtoken')->text();

        return $scoringToken;
    }

    public function payOrderRank(array $options)
    {
        $wsdl = $this->client->request('GET', sprintf('%s?singleWsdl', $this->getMerchantUrl()));
        $soapAction = (new Crawler((string) $wsdl->getBody()))
            ->filterXPath('//wsdl:operation[@name="Score"]//soap:operation')
            ->attr('soapAction')
        ;

        $this->client->request('POST', $this->getMerchantUrl(), [
            'body' => $twig->render('@IDCIPayment/Gateway/eureka/pay_order_rank.xml.twig', $this->resolvePayOrderRankOptions($options)),
            'headers' => [
                'Content-Type' => 'text/xml',
                'SoapAction' => $soapAction,
            ],
        ]);
    }

    public function updateOrder(array $options)
    {
        $wsdl = $this->client->request('GET', sprintf('%s?singleWsdl', $this->getMerchantUrl()));
        $soapAction = (new Crawler((string) $wsdl->getBody()))
            ->filterXPath('//wsdl:operation[@name="Score"]//soap:operation')
            ->attr('soapAction')
        ;

        $this->client->request('POST', $this->getMerchantUrl(), [
            'body' => $twig->render('@IDCIPayment/Gateway/eureka/update_order.xml.twig', $this->resolveUpdateOrderOptions($options)),
            'headers' => [
                'Content-Type' => 'text/xml',
                'SoapAction' => $soapAction,
            ],
        ]);
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

    private function resolvePayOrderRankOptions(array $payOrderRankOptions): array
    {
        $payOrderRankResolver = (new OptionsResolver())
            ->setRequired([
                'Header',
                'PayOrderRankRequestMessage',
            ])
            ->setAllowedTypes('Header', ['array'])
                ->setNormalizer('Header', function (Options $options, $value) {
                    return $this->resolveHeaderOptions($value);
                })
            ->setAllowedTypes('PayOrderRankRequestMessage', ['array'])
                ->setNormalizer('PayOrderRankRequestMessage', function (Options $options, $value) {
                    return $this->resolvePayOrderRankRequestMessageOptions($value);
                })
        ;

        return $payOrderRankResolver->resolve($payOrderRankOptions);
    }

    private function resolveUpdateOrderOptions(array $payOrderRankOptions): array
    {
        $payOrderRankResolver = (new OptionsResolver())
            ->setRequired([
                'Header',
                'UpdateOrderRequestMessage',
            ])
            ->setAllowedTypes('Header', ['array'])
                ->setNormalizer('Header', function (Options $options, $value) {
                    return $this->resolveHeaderOptions($value);
                })
            ->setAllowedTypes('UpdateOrderRequestMessage', ['array'])
                ->setNormalizer('UpdateOrderRequestMessage', function (Options $options, $value) {
                    return $this->resolveUpdateOrderRequestMessageOptions($value);
                })
        ;

        return $payOrderRankResolver->resolve($payOrderRankOptions);
    }

    private function resolveHeaderOptions(array $headerOptions): array
    {
        $headerResolver = (new OptionsResolver())
            ->setRequired([
                'Context',
                'Localization',
                'SecurityContext',
            ])
            ->setDefaults([
                'Version' => 1,
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

        return $headerResolver->resolve($headerOptions);
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
                'Language',
            ])
            ->setDefaults([
                'DecimalPosition' => 2,
            ])
            ->setAllowedTypes('Country', ['string'])
            ->setAllowedTypes('Currency', ['string'])
                ->setAllowedValues('Currency', array_map(function ($currency) {
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

    private function resolvePayOrderRankRequestMessageOptions(array $payOrderRankRequestMessageOptions): array
    {
        $payOrderRankRequestMessageResolver = (new OptionsResolver())
            ->setRequired([
                'Amount',
                'OrderRef',
            ])
            ->setDefaults([
                'Attempt' => 1,
                'Rank' => 1,
            ])
            ->setAllowedTypes('Amount', ['int'])
            ->setAllowedTypes('OrderRef', ['string'])
            ->setAllowedTypes('Attempt', ['int'])
            ->setAllowedTypes('Rank', ['int'])
        ;

        return $payOrderRankRequestMessageResolver->resolve($payOrderRankRequestMessageOptions);
    }

    private function resolveUpdateOrderRequestMessageOptions(array $updateOrderRequestMessageOptions): array
    {
        $updateOrderRequestMessageResolver = (new OptionsResolver())
            ->setRequired([
                'NewAmount',
                'OrderRef',
                'ScoringToken',
            ])
            ->setAllowedTypes('NewAmount', ['int'])
            ->setAllowedTypes('OrderRef', ['string'])
            ->setAllowedTypes('ScoringToken', ['string'])
        ;

        return $updateOrderRequestMessageResolver->resolve($updateOrderRequestMessageOptions);
    }

    private function resolveRequestOptions(array $contextOptions): array
    {
        // TODO: Resolve CclInformation & MerchantUrls in case of CCL Score
        $contextResolver = (new OptionsResolver())
            ->setRequired([
                'Customer',
                'Order',
            ])
            ->setDefaults([
                'OptionalCustomerHistory' => [],
                'OptionalTravelDetails' => [],
                'OptionalStayDetails' => [],
                'OptionalProductDetails' => [],
                'OptionalPreScoreInformation' => [],
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
                    return $this->resolveOptionalCustomerHistoryOptions($value);
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
            ->setAllowedTypes('OptionalPreScoreInformation', ['array'])
                ->setNormalizer('OptionalPreScoreInformation', function (Options $options, $value) {
                    return $this->resolveOptionalPreScoreInformationOptions($value);
                })
            ->setAllowedTypes('AdditionalNumericFieldList', ['null', 'array'])
                ->setNormalizer('AdditionalNumericFieldList', function (Options $options, $value) {
                    return $this->resolveAdditionalFieldListOptions($value);
                })
            ->setAllowedTypes('AdditionalTextFieldList', ['null', 'array'])
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
            ->setDefaults([
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
                    if (strlen((string) $value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.CustomerRef" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('LastName', ['string'])
                ->setNormalizer('LastName', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 64) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.LastName" max length is 64, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('FirstName', ['string'])
                ->setNormalizer('FirstName', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 64) {
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
                    strtolower(self::CIVILITY_MISTER),
                    strtolower(self::CIVILITY_MISS),
                    strtolower(self::CIVILITY_MISSTRESS),
                ])
                ->setNormalizer('Civility', function (Options $options, $value) {
                    return ucfirst($value);
                })
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

                    if (1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException(
                            'The "Customer.BirthDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })

            ->setAllowedTypes('BirthZipCode', ['int', 'string'])
                ->setNormalizer('BirthZipCode', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.BirthZipCode" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('PhoneNumber', ['string'])
                ->setNormalizer('PhoneNumber', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 13) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.PhoneNumber" max length is 13, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('CellPhoneNumber', ['string'])
                ->setNormalizer('CellPhoneNumber', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 13) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.CellPhoneNumber" max length is 13, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Email', ['string'])
                ->setNormalizer('Email', function (Options $options, $value) {
                    if (strlen($value) > 60) {
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
                    if (is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address1" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address2', ['null', 'string'])
                ->setNormalizer('Address2', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address2" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address3', ['null', 'string'])
                ->setNormalizer('Address3', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address3" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Address4', ['null', 'string'])
                ->setNormalizer('Address4', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 32) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.Address4" max length is 32, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ZipCode', ['int', 'string'])
                ->setNormalizer('ZipCode', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.ZipCode" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('City', ['string'])
                ->setNormalizer('City', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "Customer.City" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Country', ['string'])
            ->setAllowedTypes('Nationality', ['null', 'string'])
                ->setAllowedValues('Nationality', [
                    null,
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
            ->setAllowedTypes('WhiteList', ['null', 'string'])
                ->setAllowedValues('WhiteList', [
                    null,
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

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
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

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
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

        $optionalCustomerHistory = $optionalCustomerHistoryResolver->resolve($optionalCustomerHistory);
        if (empty(array_filter($optionalCustomerHistory, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $optionalCustomerHistory;
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
                    if (is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.Insurance" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Type', ['null', 'string'])
                ->setAllowedValues('Type', [
                    null,
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

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
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

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException(
                            'The "OptionalTravelDetails.ReturnDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DestinationCountry', ['null', 'string'])
                ->setNormalizer('DestinationCountry', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 2) {
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
                    null,
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
                    if (is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.MainDepartureCompany" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DepartureAirport', ['null', 'string'])
                ->setNormalizer('DepartureAirport', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.DepartureAirport" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ArrivalAirport', ['null', 'string'])
                ->setNormalizer('ArrivalAirport', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 3) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.ArrivalAirport" max length is 3, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('DiscountCode', ['null', 'string'])
                ->setNormalizer('DiscountCode', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalTravelDetails.DiscountCode" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('LuggageSupplement', ['null', 'string'])
                ->setNormalizer('LuggageSupplement', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 30) {
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

        $optionalTravelDetails = $optionalTravelDetailsResolver->resolve($optionalTravelDetails);
        if (empty(array_filter($optionalTravelDetails, function ($a) { return null !== $a && (is_array($a) && !empty($a)); }))) {
            return [];
        }

        return $optionalTravelDetails;
    }

    private function resolveTravellerPassportListOptions(?array $travellerPassportList): array
    {
        if (null === $travellerPassportList || empty($travellerPassportList)) {
            return [];
        }

        $travellerPassportListResolver = (new OptionsResolver())
            ->setRequired([
                'ExpirationDate',
                'IssuanceCountry',
            ])
            > setAllowedTypes('ExpirationDate', ['null', 'string', \DateTime::class])
                ->setNormalizer('ExpirationDate', function (Options $options, $value) {
                    if (null === $value) {
                        return $value;
                    }

                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException(
                            'The "OptionalTravelDetails.TravellerPassportList[].ExpirationDate" must be formatted as described in documentation "YYYY-MM-DD"'
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('IssuanceCountry', ['null', 'string'])
                ->setNormalizer('IssuanceCountry', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 2) {
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

        return !empty($resolvedTravellerPassportList) ? $resolvedTravellerPassportList : [];
    }

    private function resolveOptionalStayDetailsOptions(array $optionalStayDetails): array
    {
        $optionalStayDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'Company',
                'Destination',
                'NightNumber',
                'RoomRange',
            ])
            ->setAllowedTypes('Company', ['null', 'string'])
                ->setNormalizer('Company', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalStayDetails.Company" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Destination', ['null', 'string'])
                ->setNormalizer('Destination', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 50) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalStayDetails.Destination" max length is 50, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('NightNumber', ['null', 'int'])
            ->setAllowedTypes('RoomRange', ['null', 'int'])
        ;

        $optionalStayDetails = $optionalStayDetailsResolver->resolve($optionalStayDetails);
        if (empty(array_filter($optionalStayDetails, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $optionalStayDetails;
    }

    private function resolveOptionalProductDetailsOptions(array $optionalProductDetails): array
    {
        $optionalProductDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'Categorie1',
                'Categorie2',
                'Categorie3',
            ])
            ->setAllowedTypes('Categorie1', ['null', 'string'])
                ->setNormalizer('Categorie1', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie1" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Categorie2', ['null', 'string'])
                ->setNormalizer('Categorie2', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie2" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('Categorie3', ['null', 'string'])
                ->setNormalizer('Categorie3', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 30) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OptionalProductDetails.Categorie3" max length is 30, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
        ;

        $optionalProductDetails = $optionalProductDetailsResolver->resolve($optionalProductDetails);
        if (empty(array_filter($optionalProductDetails, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $optionalProductDetails;
    }

    private function resolveOptionalPreScoreInformationOptions(array $optionalPreScoreInformation): array
    {
        $optionalPreScoreInformationResolver = (new OptionsResolver())
            ->setRequired([
                'RequestID',
            ])
            ->setAllowedTypes('RequestID', ['null', 'string'])
        ;

        $optionalPreScoreInformation = $optionalPreScoreInformationResolver->resolve($optionalPreScoreInformation);
        if (empty(array_filter($optionalPreScoreInformation, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $optionalPreScoreInformation;
    }

    private function resolveAdditionalFieldListOptions(?array $additionalFieldList): array
    {
        if (null === $additionalFieldList || empty($additionalFieldList)) {
            return [];
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

        return !empty($additionalNumericFieldList) ? $additionalNumericFieldList : [];
    }

    private function resolveOptionalShippingDetailsOptions(array $orderShippingDetails): array
    {
        $orderShippingDetailsResolver = (new OptionsResolver())
            ->setRequired([
                'ShippingAdress1',
                'ShippingAdress2',
                'ShippingAdressCity',
                'ShippingAdressZip',
                'ShippingAdressCountry',
            ])
            ->setAllowedTypes('ShippingAdress1', ['null', 'string'])
                ->setNormalizer('ShippingAdress1', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdress1" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdress2', ['null', 'string'])
                ->setNormalizer('ShippingAdress2', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdress2" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressCity', ['null', 'string'])
                ->setNormalizer('ShippingAdressCity', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 100) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressCity" max length is 100, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressZip', ['null', 'string'])
                ->setNormalizer('ShippingAdressZip', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 5) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressZip" max length is 5, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('ShippingAdressCountry', ['null', 'string'])
                ->setNormalizer('ShippingAdressCountry', function (Options $options, $value) {
                    if (is_string($value) && strlen($value) > 2) {
                        throw new \InvalidArgumentException(
                            sprintf('The "OrderShippingDetails.ShippingAdressCountry" max length is 2, current size given: %s', strlen($value))
                        );
                    }

                    return $value;
                })
        ;

        $orderShippingDetails = $orderShippingDetailsResolver->resolve($orderShippingDetails);
        if (empty(array_filter($orderShippingDetails, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $orderShippingDetails;
    }
}
