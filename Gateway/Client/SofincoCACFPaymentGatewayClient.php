<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class SofincoCACFPaymentGatewayClient
{
    const BUSINESS_TOKEN_DURATION = 30;
    const BUSINESS_TOKEN_FORMAT_OPAQUE = 'OPAQUE';
    const BUSINESS_TOKEN_FORMAT_JWS = 'JWS';
    const BUSINESS_TOKEN_FORMAT_JWE = 'JWE';

    const CONTEXT_APPLICATION_ID = 'creditPartner';
    const CONTEXT_PARTNER_ID = 'web_em';
    const CONTEXT_SOURCE_ID = 'vac';

    const CUSTOMER_CIVILITY_CODE_MR = 1;
    const CUSTOMER_CIVILITY_CODE_MRS = 2;
    const CUSTOMER_CIVILITY_CODE_MS = 3;

    const DOCUMENT_SERVICE_NAME_A1 = 'FLUX_A1';
    const DOCUMENT_SERVICE_NAME_B = 'FLUX_B';
    const DOCUMENT_SERVICE_NAME_C = 'FLUX_C';
    const DOCUMENT_SERVICE_NAME_D = 'FLUX_D';

    const DOCUMENT_STATUS_PENDING_1 = 'ATT';
    const DOCUMENT_STATUS_PENDING_2 = 'AFI';
    const DOCUMENT_STATUS_STUDY_IN_PROGRESS = 'ETU';
    const DOCUMENT_STATUS_WAITING_FOR_CLIENT = 'CLI';
    const DOCUMENT_STATUS_REFUSED = 'REF';
    const DOCUMENT_STATUS_CANCELED = 'ANN';
    const DOCUMENT_STATUS_FUNDED = 'FIN';
    const DOCUMENT_STATUS_NOT_FOUND = 'NFD';
    const DOCUMENT_STATUS_ERROR = 'ERR';

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var AdapterInterface
     */
    private $cache;

    /**
     * @var string|null
     */
    private $clientId;

    /**
     * @var string|null
     */
    private $secretId;

    /**
     * @var string
     */
    private $serverHostName;

    /**
     * @var string
     */
    private $apiHostName;

    /**
     * @var string
     */
    private $weblongHostName;

    public function __construct(
        Environment $twig,
        LoggerInterface $logger,
        ?string $clientId,
        ?string $secretId,
        ?string $serverHostName,
        ?string $apiHostName,
        ?string $weblongHostName
    ) {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->client = new Client(['defaults' => ['verify' => false, 'timeout' => 5]]);
        $this->cache = null;
        $this->clientId = $clientId;
        $this->secretId = $secretId;
        $this->serverHostName = $serverHostName;
        $this->apiHostName = $apiHostName;
        $this->weblongHostName = $weblongHostName;
    }

    /**
     * Set the cache adapter.
     *
     * @method setCache
     *
     * @param AdapterInterface|null $cache
     *
     * @throws \RuntimeException         If the symfony/cache package is not installed
     * @throws \UnexpectedValueException If the cache doesn't implement AdapterInterface
     */
    public function setCache($cache)
    {
        if (null !== $cache) {
            if (!interface_exists(AdapterInterface::class)) {
                throw new \RuntimeException('EurekaPaymentGatewayClient cache requires "symfony/cache" package');
            }

            if (!$cache instanceof AdapterInterface) {
                throw new \UnexpectedValueException(sprintf('The client\'s cache must implement %s.', AdapterInterface::class));
            }

            $this->cache = $cache;
        }
    }

    public function getAccessTokenHash(): string
    {
        return md5(sprintf('idci_payment.sofinco.access_token.%s', $this->clientId));
    }

    public function getAccessTokenUrl(): string
    {
        return sprintf('https://%s/token', $this->apiHostName);
    }

    public function getAccessTokenResponse(): ?Response
    {
        if (null === $this->clientId || null === $this->secretId) {
            throw new \LogicException('You must define "idci_payment.sofinco.client_id" and "idci_payment.sofinco.secret_id" parameters to use SofincoCACFPaymentGatewayClient');
        }

        try {
            return $this->client->request('POST', $this->getAccessTokenUrl(), [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
                'auth' => [
                    $this->clientId,
                    $this->secretId,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->error($e->hasResponse() ? ((string) $e->getResponse()->getBody()) : $e->getMessage());

            return null;
        }
    }

    public function getAccessTokenData(): array
    {
        if (null !== $this->cache && $this->cache->hasItem($this->getAccessTokenHash())) {
            return json_decode($this->cache->getItem($this->getAccessTokenHash())->get(), true);
        }

        $tokenResponse = $this->getAccessTokenResponse();

        if (null === $tokenResponse) {
            throw new \UnexpectedValueException('The access token request failed.');
        }

        $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);

        if (!is_array($tokenData)) {
            throw new \UnexpectedValueException('The access token response can\'t be parsed.');
        }

        if (null !== $this->cache) {
            $item = $this->cache->getItem($this->getAccessTokenHash());
            $item->set(json_encode($tokenData));
            $item->expiresAfter($tokenData['expires_in']);

            $this->cache->save($item);
        }

        return $tokenData;
    }

    public function getAccessToken(): string
    {
        return $this->getAccessTokenData()['access_token'];
    }

    public function getBusinessTokenUrl(): string
    {
        return sprintf('https://%s/BusinessDataTransfer/V1/businessDataTransferTokens/', $this->apiHostName);
    }

    public function getBusinessTokenResponse(array $options): ?Response
    {
        try {
            return $this->client->request('POST', $this->getBusinessTokenUrl(), [
                'json' => $this->resolveBusinessTokenOptions($options),
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->error($e->hasResponse() ? ((string) $e->getResponse()->getBody()) : $e->getMessage());

            return null;
        }
    }

    public function getBusinessTokenData(array $options): array
    {
        $tokenResponse = $this->getBusinessTokenResponse($options);

        if (null === $tokenResponse) {
            throw new \UnexpectedValueException('The business token request failed.');
        }

        $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);

        if (!is_array($tokenData)) {
            throw new \UnexpectedValueException('The business token response can\'t be parsed.');
        }

        return $tokenData;
    }

    public function getBusinessToken(array $options): string
    {
        return $this->getBusinessTokenData($options)['token'];
    }

    public function getCreditUrl(array $options): string
    {
        return sprintf(
            'https://%s/creditpartner/?q6=%s&x1=%s&token=%s',
            $this->serverHostName,
            self::CONTEXT_PARTNER_ID,
            self::CONTEXT_SOURCE_ID,
            $this->getBusinessToken($options)
        );
    }

    public function getLoanSimulationsUrl(): string
    {
        return sprintf('https://%s/loanSimulation/v1/simulations/', $this->apiHostName);
    }

    public function getLoanSimulationsResponse(array $options): ?Response
    {
        try {
            return $this->client->request('POST', $this->getLoanSimulationsUrl(), [
                'json' => $this->resolveLoanSimulationsOptions($options),
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                    'Context-Applicationid' => self::CONTEXT_APPLICATION_ID,
                    'Context-Partnerid' => self::CONTEXT_PARTNER_ID,
                    'Context-Sourceid' => self::CONTEXT_SOURCE_ID,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->error($e->hasResponse() ? ((string) $e->getResponse()->getBody()) : $e->getMessage());

            return null;
        }
    }

    public function getLoanSimulations(array $options): array
    {
        $loanSimulationResponse = $this->getLoanSimulationsResponse($options);

        if (null === $loanSimulationResponse) {
            throw new \UnexpectedValueException('The loan simulations request failed.');
        }

        $loanSimulations = json_decode($loanSimulationResponse->getBody()->getContents(), true);

        if (!is_array($loanSimulations)) {
            throw new \UnexpectedValueException('The loanSimulationResponse response can\'t be parsed.');
        }

        return $loanSimulations;
    }

    public function getSimulatorUrl(array $options): string
    {
        $resolvedOptions = $this->resolveSimulatorOptions($options);

        return sprintf(
            'https://%s/creditpartner/?q6=%s&x1=simu_vac&s3=%s&a9=%s&n2=%s',
            $this->serverHostName,
            self::CONTEXT_PARTNER_ID,
            $resolvedOptions['amount'],
            $resolvedOptions['businessProviderId'],
            $resolvedOptions['equipmentCode']
        );
    }

    public function getDocumentsUrl(): string
    {
        return sprintf('https://%s/websrv/index.asp', $this->weblongHostName);
    }

    public function getDocumentsResponse(array $options): ?Response
    {
        try {
            $resolvedOptions = $this->resolveDocumentsOptions($options);

            $templateName = in_array($resolvedOptions['ServiceName'], [self::DOCUMENT_SERVICE_NAME_A1, self::DOCUMENT_SERVICE_NAME_C]) ?
                'flux_a1_c' :
                'flux_b_d'
            ;

            return $this->client->request('POST', $this->getDocumentsUrl(), [
                'body' => $this->twig->render(sprintf('@IDCIPayment/Gateway/sofinco/%s.html.twig', $templateName), $options),
                'headers' => [
                    'Context-Applicationid' => self::CONTEXT_APPLICATION_ID,
                    'Context-Partnerid' => self::CONTEXT_PARTNER_ID,
                    'Context-Sourceid' => self::CONTEXT_SOURCE_ID,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->error($e->hasResponse() ? ((string) $e->getResponse()->getBody()) : $e->getMessage());

            return null;
        }
    }

    public function getDocuments(array $options): Crawler
    {
        $documentResponse = $this->getDocumentsResponse($options);

        if (null === $documentResponse) {
            throw new \UnexpectedValueException('The loan simulations request failed.');
        }

        return new Crawler($documentResponse->getBody()->getContents());
    }

    private function resolveBusinessTokenOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefault('tokenFormat', self::BUSINESS_TOKEN_FORMAT_OPAQUE)->setAllowedValues('tokenFormat', [
                self::BUSINESS_TOKEN_FORMAT_OPAQUE,
                self::BUSINESS_TOKEN_FORMAT_JWS,
                self::BUSINESS_TOKEN_FORMAT_JWE,
            ])
            ->setDefined('associationKey')->setAllowedTypes('associationKey', ['string', 'null'])
            ->setDefault('tokenDuration', self::BUSINESS_TOKEN_DURATION)->setAllowedTypes('tokenDuration', ['int'])
            ->setRequired('businessContext')->setAllowedTypes('businessContext', ['string', 'array'])
                ->setNormalizer('businessContext', function (Options $options, $value) {
                    if (is_string($value)) {
                        $value = json_decode($value, true);

                        if (null === $value) {
                            throw new \InvalidArgumentException('The "businessContext" parameter is not a valid json string');
                        }
                    }

                    return json_encode($this->resolveBusinessContextOptions($value));
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty(array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolveBusinessContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('providerContext')->setAllowedTypes('providerContext', ['array'])
                ->setNormalizer('providerContext', function (Options $options, $value) {
                    return $this->resolveBusinessProviderContextOptions($value);
                })
            ->setDefined('customerContext')->setAllowedTypes('customerContext', ['array'])
                ->setNormalizer('customerContext', function (Options $options, $value) {
                    return $this->resolveBusinessCustomerContextOptions($value);
                })
            ->setDefined('coBorrowerContext')->setAllowedTypes('coBorrowerContext', ['array'])
                ->setNormalizer('coBorrowerContext', function (Options $options, $value) {
                    return $this->resolveBusinessCustomerContextOptions($value);
                })
            ->setDefined('offerContext')->setAllowedTypes('offerContext', ['array'])
                ->setNormalizer('offerContext', function (Options $options, $value) {
                    return $this->resolveBusinessOfferContextOptions($value);
                })
        ;

        return array_filter($resolver->resolve($options), function ($a) { return !empty($a); });
    }

    private function resolveBusinessProviderContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('businessProviderId')->setAllowedTypes('businessProviderId', ['null', 'string'])
                ->setNormalizer('businessProviderId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{11}/', $value)) {
                        throw new \InvalidArgumentException('The "businessProviderId" parameter must be formatted as described in documentation "[A-Z0-9]{11}"');
                    }

                    return $value;
                })
            ->setRequired('returnUrl')->setAllowedTypes('returnUrl', ['string'])
                ->setNormalizer('returnUrl', function (Options $options, $value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('The "returnUrl" parameter is not a valid URL');
                    }

                    return $value;
                })
            ->setRequired('exchangeUrl')->setAllowedTypes('exchangeUrl', ['string'])
                ->setNormalizer('exchangeUrl', function (Options $options, $value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('The "exchangeUrl" parameter is not a valid URL');
                    }

                    return $value;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolveBusinessCustomerContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('externalCustomerId')->setAllowedTypes('externalCustomerId', ['null', 'string'])
                ->setNormalizer('externalCustomerId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "externalCustomerId" parameter must be formatted as described in documentation "[A-Z0-9]{0,16}"');
                    }

                    return $value;
                })
            ->setDefined('civilityCode')->setAllowedTypes('civilityCode', ['null', 'int', 'string'])
                ->setNormalizer('civilityCode', function (Options $options, $value) {
                    $civilityCodeMapping = [
                        'mr' => self::CUSTOMER_CIVILITY_CODE_MR,
                        'mrs' => self::CUSTOMER_CIVILITY_CODE_MRS,
                        'ms' => self::CUSTOMER_CIVILITY_CODE_MS,
                    ];

                    if (isset($civilityCodeMapping[$value])) {
                        return $civilityCodeMapping[$value];
                    }

                    if (is_string($value) && 1 !== preg_match('/[123]{1}/', $value)) {
                        throw new \InvalidArgumentException('The "civilityCode" parameter must be formatted as described in documentation "[123]{1}"');
                    }

                    return $value !== null ? (int) $value : null;
                })
            ->setDefined('firstName')->setAllowedTypes('firstName', ['null', 'string'])
                ->setNormalizer('firstName', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,20}/', $value)) {
                        throw new \InvalidArgumentException('The "firstName" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,20}"');
                    }

                    return $value;
                })
            ->setDefined('lastName')->setAllowedTypes('lastName', ['null', 'string'])
                ->setNormalizer('lastName', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,20}/', $value)) {
                        throw new \InvalidArgumentException('The "lastName" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,20}"');
                    }

                    return $value;
                })
            ->setDefined('birthName')->setAllowedTypes('birthName', ['null', 'string'])
                ->setNormalizer('birthName', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,20}/', $value)) {
                        throw new \InvalidArgumentException('The "birthName" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,20}"');
                    }

                    return $value;
                })
            ->setDefined('birthDate')->setAllowedTypes('birthDate', ['null', 'string'])
                ->setNormalizer('birthDate', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "birthDate" parameter must be formatted as described in documentation "[0-9]{4}-[0-9]{2}-[0-9]{2}"');
                    }

                    return $value;
                })
            ->setDefined('citizenshipCode')->setAllowedTypes('citizenshipCode', ['null', 'string'])
                ->setNormalizer('citizenshipCode', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z*]{0,3}/', $value)) {
                        throw new \InvalidArgumentException('The "citizenshipCode" parameter must be formatted as described in documentation "[A-Z*]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('birthCountryCode')->setAllowedTypes('birthCountryCode', ['null', 'string'])
                ->setNormalizer('birthCountryCode', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z*]{0,3}/', $value)) {
                        throw new \InvalidArgumentException('The "birthCountryCode" parameter must be formatted as described in documentation "[A-Z*]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('additionalStreet')->setAllowedTypes('additionalStreet', ['null', 'string'])
                ->setNormalizer('additionalStreet', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "additionalStreet" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('street')->setAllowedTypes('street', ['null', 'string'])
                ->setNormalizer('street', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "street" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('city')->setAllowedTypes('city', ['null', 'string'])
                ->setNormalizer('city', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "city" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('zipCode')->setAllowedTypes('zipCode', ['null', 'int', 'string'])
                ->setNormalizer('zipCode', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }
                    
                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "zipCode" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('distributerOffice')->setAllowedTypes('distributerOffice', ['null', 'string'])
                ->setNormalizer('distributerOffice', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "distributerOffice" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('countryCode')->setAllowedTypes('countryCode', ['null', 'string'])
                ->setNormalizer('countryCode', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z*]{0,3}/', $value)) {
                        throw new \InvalidArgumentException('The "countryCode" parameter must be formatted as described in documentation "[A-Z*]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('phoneNumber')->setAllowedTypes('phoneNumber', ['null', 'string'])
                ->setNormalizer('phoneNumber', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/0[1234589]{1}[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "phoneNumber" parameter must be formatted as described in documentation "0[1234589]{1}[0-9]{8}"');
                    }

                    return $value;
                })
            ->setDefined('mobileNumber')->setAllowedTypes('mobileNumber', ['null', 'string'])
                ->setNormalizer('mobileNumber', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/0[67]{1}[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "mobileNumber" parameter must be formatted as described in documentation "0[67]{1}[0-9]{8}"');
                    }

                    return $value;
                })
            ->setDefined('emailAddress')->setAllowedTypes('emailAddress', ['null', 'string'])
                ->setNormalizer('emailAddress', function (Options $options, $value) {
                    if (is_string($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException('The "emailAddress" parameter is not a valid email"');
                    }

                    return $value;
                })
            ->setDefined('loyaltyCardId')->setAllowedTypes('loyaltyCardId', ['null', 'string'])
                ->setNormalizer('loyaltyCardId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,19}/', $value)) {
                        throw new \InvalidArgumentException('The "loyaltyCardId" parameter must be formatted as described in documentation "[A-Z0-9]{0,19}"');
                    }

                    return $value;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolveBusinessOfferContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('orderId')->setAllowedTypes('orderId', ['null', 'string'])
                ->setNormalizer('orderId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "orderId" parameter must be formatted as described in documentation "[A-Z0-9]{0,16}"');
                    }

                    return $value;
                })
            ->setDefined('scaleId')->setAllowedTypes('scaleId', ['null', 'string'])
                ->setNormalizer('scaleId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "scaleId" parameter must be formatted as described in documentation "[A-Z0-9]{0,16}"');
                    }

                    return $value;
                })
            ->setDefined('equipmentCode')->setAllowedTypes('equipmentCode', ['null', 'string'])
                ->setNormalizer('equipmentCode', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{3}/', $value)) {
                        throw new \InvalidArgumentException('The "equipmentCode" parameter must be formatted as described in documentation "[A-Z0-9]{3}"');
                    }

                    return $value;
                })
            ->setDefined('amount')->setAllowedTypes('amount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('amount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "amount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return $value !== null ? (float) $value : null;
                })
            ->setDefined('orderAmount')->setAllowedTypes('orderAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('orderAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "orderAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return $value !== null ? (float) $value : null;
                })
            ->setDefined('personalContributionAmount')->setAllowedTypes('personalContributionAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('personalContributionAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "personalContributionAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return $value !== null ? (float) $value : null;
                })
            ->setDefined('duration')->setAllowedTypes('duration', ['null', 'string'])
                ->setNormalizer('duration', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,3}/', $value)) {
                        throw new \InvalidArgumentException('The "duration" parameter must be formatted as described in documentation "[0-9]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('preScoringCode')->setAllowedTypes('preScoringCode', ['null', 'string'])
                ->setNormalizer('preScoringCode', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{1}/', $value)) {
                        throw new \InvalidArgumentException('The "preScoringCode" parameter must be formatted as described in documentation "[0-9]{1}"');
                    }

                    return $value;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolveLoanSimulationsOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('amount')->setAllowedTypes('amount', ['null', 'int', 'float'])
            ->setDefined('personalContributionAmount')->setAllowedTypes('personalContributionAmount', ['null', 'int', 'float'])
            ->setDefault('dueNumbers', [])->setAllowedTypes('dueNumbers', ['array'])
                ->setNormalizer('dueNumbers', function (Options $options, $value) {
                    foreach ($value as $dueNumber) {
                        if (!is_int($dueNumber)) {
                            throw new \InvalidArgumentException('The "dueNumbers" parameter must be an array of integer');
                        }
                    }

                    return $value;
                })
            ->setDefined('monthlyAmount')->setAllowedTypes('amount', ['int', 'float'])
            ->setDefined('hasBorrowerInsurance')->setAllowedTypes('hasBorrowerInsurance', ['bool'])
            ->setDefined('hasCoBorrowerInsurance')->setAllowedTypes('hasBorrowerInsurance', ['bool'])
            ->setDefined('hasEquipmentInsurance')->setAllowedTypes('hasBorrowerInsurance', ['bool'])
            ->setDefined('borrowerBirthDate')->setAllowedTypes('borrowerBirthDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('borrowerBirthDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "borrowerBirthDate" must be formatted as described in documentation "YYYY-MM-DD"');
                    }

                    return $value;
                })
            ->setDefined('coBorrowerBirthDate')->setAllowedTypes('coBorrowerBirthDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('coBorrowerBirthDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "coBorrowerBirthDate" must be formatted as described in documentation "YYYY-MM-DD"');
                    }

                    return $value;
                })
            ->setDefined('scaleCode')->setAllowedTypes('scaleCode', ['string'])
            ->setRequired('businessProviderId')->setAllowedTypes('businessProviderId', ['string'])
            ->setRequired('equipmentCode')->setAllowedTypes('equipmentCode', ['string'])
            ->setDefined('offerDate')->setAllowedTypes('offerDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('offerDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d');
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "offerDate" must be formatted as described in documentation "YYYY-MM-DD"');
                    }

                    return $value;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty(array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolveSimulatorOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('amount')->setAllowedTypes('amount', ['int', 'float'])
            ->setRequired('businessProviderId')->setAllowedTypes('businessProviderId', ['string'])
            ->setRequired('equipmentCode')->setAllowedTypes('equipmentCode', ['string'])
        ;

        return $resolver->resolve($options);
    }

    private function resolveDocumentsOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined(array_keys($options))
            ->setRequired('ServiceName')->setAllowedValues('ServiceName', [
                self::DOCUMENT_SERVICE_NAME_A1,
                self::DOCUMENT_SERVICE_NAME_B,
                self::DOCUMENT_SERVICE_NAME_C,
                self::DOCUMENT_SERVICE_NAME_D,
            ])
        ;

        $serviceNameResolveOptionsMethodMapping = [
            self::DOCUMENT_SERVICE_NAME_A1 => 'resolveDocumentsServiceNameA1Options',
            self::DOCUMENT_SERVICE_NAME_B => 'resolveDocumentsServiceNameBOptions',
            self::DOCUMENT_SERVICE_NAME_C => 'resolveDocumentsServiceNameCOptions',
            self::DOCUMENT_SERVICE_NAME_D => 'resolveDocumentsServiceNameDOptions',
        ];

        $serviceName = $resolver->resolve($options)['ServiceName'];

        return call_user_func([$this, $serviceNameResolveOptionsMethodMapping[$serviceName]], $options);
    }

    private function resolveDocumentsServiceNameA1Options(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('ServiceName')->setAllowedValues('ServiceName', [
                self::DOCUMENT_SERVICE_NAME_A1,
            ])
            ->setRequired('StartDate')->setAllowedTypes('StartDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('StartDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Ymd');
                    }

                    if (1 !== preg_match('/[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "StartDate" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
            ->setRequired('EndDate')->setAllowedTypes('EndDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('EndDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Ymd');
                    }

                    if (1 !== preg_match('/[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "EndDate" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
            ->setRequired('Vendeur')->setAllowedTypes('Vendeur', ['string'])
                ->setNormalizer('Vendeur', function (Options $options, $value) {
                    if (1 !== preg_match('/991[0-9A-Z-a-z]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "Vendeur" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
        ;

        return $resolver->resolve($options);
    }

    private function resolveDocumentsServiceNameBOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('ServiceName')->setAllowedValues('ServiceName', [
                self::DOCUMENT_SERVICE_NAME_B,
            ])
            ->setDefault('Dossiers', [])->setAllowedTypes('Dossiers', ['array'])
                ->setNormalizer('Dossiers', function (Options $options, $value) {
                    if (empty($value)) {
                        return [];
                    }

                    foreach ($value as &$dossier) {
                        $dossier = $this->resolveDocumentsDossiersOptions($dossier);
                    }

                    return $value;
                })
        ;

        return $resolver->resolve($options);
    }

    private function resolveDocumentsServiceNameCOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('ServiceName')->setAllowedValues('ServiceName', [
                self::DOCUMENT_SERVICE_NAME_C,
            ])
            ->setRequired('StartDate')->setAllowedTypes('StartDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('StartDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Ymd');
                    }

                    if (1 !== preg_match('/[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "StartDate" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
            ->setRequired('EndDate')->setAllowedTypes('EndDate', [\DateTimeInterface::class, 'string'])
                ->setNormalizer('EndDate', function (Options $options, $value) {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Ymd');
                    }

                    if (1 !== preg_match('/[0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "EndDate" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
            ->setRequired('Vendeur')->setAllowedTypes('Vendeur', ['string'])
                ->setNormalizer('Vendeur', function (Options $options, $value) {
                    if (1 !== preg_match('/991[0-9A-Z-a-z]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "Vendeur" parameter must be formatted as described in documentation "YYYYMMDD"');
                    }

                    return $value;
                })
        ;

        return $resolver->resolve($options);
    }

    private function resolveDocumentsServiceNameDOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('ServiceName')->setAllowedValues('ServiceName', [
                self::DOCUMENT_SERVICE_NAME_D,
            ])
            ->setDefault('Dossiers', [])
                ->setNormalizer('Dossiers', function (Options $options, $value) {
                    if (empty($value)) {
                        return [];
                    }

                    foreach ($value as &$dossier) {
                        $dossier = $this->resolveDocumentsDossiersOptions($dossier);
                    }

                    return $value;
                })
        ;

        return $resolver->resolve($options);
    }

    private function resolveDocumentsDossiersOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('DossierNumber')->setAllowedTypes('DossierNumber', ['string'])
                ->setNormalizer('DossierNumber', function (Options $options, $value) {
                    if (11 < strlen($value)) {
                        return new \InvalidArgumentException(
                            sprintf('The "DossierNumber" parameter max length is 11, current size given: %s', strlen($value))
                        );
                    }
                })
            ->setDefined('CommandNumber')->setAllowedTypes('CommandNumber', ['string'])
                ->setNormalizer('CommandNumber', function (Options $options, $value) {
                    if (12 < strlen($value)) {
                        return new \InvalidArgumentException(
                            sprintf('The "CommandNumber" parameter max length is 12, current size given: %s', strlen($value))
                        );
                    }
                })
        ;

        return $resolver->resolve($options);
    }
}
