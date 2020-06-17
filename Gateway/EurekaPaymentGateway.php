<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\DomCrawler\Crawler;
use  Symfony\Component\HttpFoundation\Request;

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

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'version' => $paymentGatewayConfiguration->get('version'),
            'merchantID' => $paymentGatewayConfiguration->get('merchant_id'),
            'merchantSiteID' => $paymentGatewayConfiguration->get('merchant_site_id'),
            'paymentOptionRef' => $paymentGatewayConfiguration->get('payment_option_reference'),
            'orderRef' => $transaction->getId(),
            'decimalPosition' => '2',
            'currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'country' => 'FR',
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
            sprintf('@IDCIPayment/Gateway/soap/eureka_score_%s.xml.twig', $type),
            $this->resolveContextOptions([
                'version' => $paymentGatewayConfiguration->get('version'),
                'merchant_id' => $paymentGatewayConfiguration->get('merchant_id'),
                'merchant_site_id' => $paymentGatewayConfiguration->get('merchant_site_id'),
                'header_token' => $this->requestHeaderToken($paymentGatewayConfiguration),
                'customer' => [
                    'id' => $transaction->getCustomerId(),
                    'civility' => $transaction->getMetadata('customer.civility'),
                    'first_name' => $transaction->getMetadata('customer.first_name'),
                    'last_name' => $transaction->getMetadata('customer.last_name'),
                    'maiden_name' => $transaction->getMetadata('customer.maiden_name'),
                    'birth_date' => $transaction->getMetadata('customer.birth_date'),
                    'birth_zip_code' => $transaction->getMetadata('customer.birth_zip_code'),
                    'email' => $transaction->getCustomerEmail(),
                    'phone_number' => $transaction->getMetadata('customer.phone_numberss'),
                    'country' => $transaction->getMetadata('customer.country'),
                    'city' => $transaction->getMetadata('customer.city'),
                    'zip_code' => $transaction->getMetadata('customer.zip_code'),
                    'address' => $transaction->getMetadata('customer.address'),
                ],
                'order' => [
                    'id' => $transaction->getItemId(),
                    'item_count' => $transaction->getMetadata('order.item_count'),
                    'country' => 'FR',
                    'amount' => $transaction->getAmount(),
                    'decimal_position' => 2,
                    'currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
                    'sale_channel' => $transaction->getMetadata('customer.sale_channel'),
                    'shipping_method' => $transaction->getMetadata('customer.shipping_method'),
                    'date' => (new \DateTime('now'))->format('Y-m-d'),
                ],
            ])
        );
    }

    private function buildHmac(array $options): string
    {
        // version*merchantID*merchantSiteID*paymentOptionRef*orderRef*freeText*decimalPosition*currency*country*invoiceID*customerRef*date*amount*merchantHomeUrl*merchantBackUrl* merchantReturnUrl*merchantNotifyUrl*
        return hash('sha256', mb_convert_encoding($options['build'].$options['secret'], 'UTF-8'));
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return array_merge($options, [
            'hmac' => $this->buildOptions($options),
        ]);
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/eureka.html.twig', [
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
        ;

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
            ]
        );
    }

    private function resolveContextOptions(array $contextOptions): array
    {
        $contextResolver = (new OptionsResolver())
            ->setRequired([
                'version',
                'merchant_id',
                'merchant_site_id',
                'header_token',
                'customer',
                'order',
            ])
            ->setAllowedTypes('version', ['int', 'string'])
            ->setAllowedTypes('merchant_id', ['int', 'string'])
            ->setAllowedTypes('merchant_site_id', ['int', 'string'])
            ->setAllowedTypes('header_token', ['string'])
            ->setAllowedTypes('customer', ['array'])
                ->setNormalizer('customer', function (Options $options, $value) {
                    return $this->resolveCustomerOptions($value);
                })
            ->setAllowedTypes('order', ['array'])
                ->setNormalizer('order', function (Options $options, $value) {
                    return $this->resolveOrderOptions($value);
                })
        ;

        return $contextResolver->resolve($contextOptions);
    }

    private function resolveCustomerOptions(array $customerOptions): array
    {
        $customerResolver = (new OptionsResolver())
            ->setRequired([
                'id',
                'civility',
                'first_name',
                'last_name',
                'birth_date',
                'birth_zip_code',
                'email',
                'phone_number',
                'country',
                'city',
                'zip_code',
                'address',
            ])
            ->setDefault([
                'maiden_name' => null,
            ])
            ->setDefined([
                'nationality',
                'ip_address',
                'white_list',
            ])
            ->setAllowedTypes('id', ['int', 'string'])
            ->setAllowedTypes('civility', ['string'])
                ->setAllowedValues('civility', [
                    self::CIVILITY_MISTER,
                    self::CIVILITY_MISS,
                    self::CIVILITY_MISSTRESS,
                ])
            ->setAllowedTypes('first_name', ['string'])
            ->setAllowedTypes('last_name', ['string'])
            ->setAllowedTypes('maiden_name', ['null', 'string'])
                ->setNormalizer('maiden_name', function (Options $options, $value) {
                    if (self::CIVILITY_MISSTRESS === $options['civility'] && null === $value) {
                        throw new \UnexpectedValueException(
                            sprintf('As the field "civility" of the customer equal to "%s", "maiden_name" mustn\'t be null.', self::CIVILITY_MISSTRESS)
                        );
                    }

                    return $value;
                })
            ->setAllowedTypes('birth_date', ['string'])
            ->setAllowedTypes('birth_zip_code', ['string'])
            ->setAllowedTypes('email', ['string'])
            ->setAllowedTypes('phone_number', ['string'])
            ->setAllowedTypes('country', ['string'])
            ->setAllowedTypes('city', ['string'])
            ->setAllowedTypes('zip_code', ['string'])
            ->setAllowedTypes('address', ['string'])
            ->setAllowedTypes('nationality', ['null', 'string'])
                ->setAllowedValues('nationality', [
                    self::NATIONALITY_FRANCE,
                    self::NATIONALITY_EUROPEAN_UNION,
                    self::NATIONALITY_OTHER,
                ])
            ->setAllowedTypes('ip_address', ['null', 'string'])
            ->setAllowedTypes('whitelist', ['null', 'string'])
                ->setAllowedValues('whitelist', [
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
                'id',
                'item_count',
                'country',
                'amount',
                'decimal_position',
                'currency',
                'sale_channel',
                'shipping_method',
                'date',
            ])
            ->setAllowedTypes('id', ['int', 'string'])
            ->setAllowedTypes('item_count', ['int'])
            ->setAllowedTypes('country', ['string'])
            ->setAllowedTypes('amount', ['int'])
            ->setAllowedTypes('decimal_position', ['int'])
            ->setAllowedTypes('currency', ['string'])
                ->setAllowedValues('currency', array_map(function ($currency) {
                    return $currency->getAlpha3();
                }, (new ISO4217())->findAll()))
            ->setAllowedTypes('sale_channel', ['string'])
                ->setAllowedValues('sale_channel', [
                    self::SALE_CHANNEL_DESKTOP,
                    self::SALE_CHANNEL_TABLET,
                    self::SALE_CHANNEL_TABLED_IPAD,
                    self::SALE_CHANNEL_SMARTPHONE,
                    self::SALE_CHANNEL_SMARTPHONE_ANDROID,
                    self::SALE_CHANNEL_SMARTPHONE_IPHONE,
                ])
            ->setAllowedTypes('shipping_method', ['string'])
                ->setAllowedValues('sale_channel', [
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
            ->setAllowedTypes('date', ['string'])
        ;

        return $orderResolver->resolve($orderOptions);
    }
}
