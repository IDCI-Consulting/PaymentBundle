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
    const DOCUMENT_STATUS_UNKNOWN = 'UKW';
    const DOCUMENT_STATUS_ABANDONNED = 'ABD';

    const CONTRACT_STATUS_ACCEPTED_1 = '080';
    const CONTRACT_STATUS_ACCEPTED_2 = '091';
    const CONTRACT_STATUS_PRE_ACCEPTED_1 = '091';
    const CONTRACT_STATUS_PRE_ACCEPTED_2 = '052';
    const CONTRACT_STATUS_PENDING = '021';

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

    /**
     * @var string
     */
    private $contextApplicationId;

    /**
     * @var string
     */
    private $contextPartnerId;

    /**
     * @var string
     */
    private $contextSourceId;

    public function __construct(
        Environment $twig,
        LoggerInterface $logger,
        ?string $clientId,
        ?string $secretId,
        ?string $serverHostName,
        ?string $apiHostName,
        ?string $weblongHostName,
        ?string $contextApplicationId,
        ?string $contextPartnerId,
        ?string $contextSourceId
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
        $this->contextApplicationId = $contextApplicationId;
        $this->contextPartnerId = $contextPartnerId;
        $this->contextSourceId = $contextSourceId;
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
                throw new \RuntimeException('SofincoCACFPaymentGatewayClient cache requires "symfony/cache" package');
            }

            if (!$cache instanceof AdapterInterface) {
                throw new \UnexpectedValueException(sprintf('The client\'s cache must implement %s.', AdapterInterface::class));
            }

            $this->cache = $cache;
        }
    }

    /**
     * Access token "API Manager / APIM"
     */

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
            $this->logRequestException($e);

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

        $tokenData = json_decode((string) $tokenResponse->getBody(), true);

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

    /**
     * Simulation API
     */

    public function getLoanSimulationsUrl(): string
    {
        return sprintf('https://%s/loanSimulation/v1/simulations/', $this->apiHostName);
    }

    public function getLoanSimulationsResponse(array $options): ?Response
    {
        $data = $this->resolveLoanSimulationsOptions($options);

        try {
            return $this->client->request('POST', $this->getLoanSimulationsUrl(), [
                'json' => $data,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                    'Context-Applicationid' => $this->contextApplicationId,
                    'Context-Partnerid' => $this->contextPartnerId,
                    'Context-Sourceid' => $this->contextSourceId,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }
    }

    public function getLoanSimulations(array $options): array
    {
        $loanSimulationResponse = $this->getLoanSimulationsResponse($options);

        if (null === $loanSimulationResponse) {
            throw new \UnexpectedValueException('The loan simulations request failed.');
        }

        $loanSimulations = json_decode((string) $loanSimulationResponse->getBody(), true);

        if (!is_array($loanSimulations)) {
            throw new \UnexpectedValueException('The loanSimulationResponse response can\'t be parsed.');
        }

        return $loanSimulations;
    }

    public function getSimulatorUrl(array $options): string
    {
        $resolvedOptions = $this->resolveSimulatorOptions($options);

        return sprintf(
            'https://%s/simulateur/?Q6=%s&X1=simu_vac&s3=%s&a9=%s&n2=%s',
            $this->serverHostName,
            $this->contextPartnerId,
            $resolvedOptions['amount'],
            $resolvedOptions['businessProviderId'],
            $resolvedOptions['equipmentCode']
        );
    }

    /**
     * Business token API
     */

    public function getBusinessTokenUrl(): string
    {
        return sprintf('https://%s/BusinessDataTransfer/V1/businessDataTransferTokens/', $this->apiHostName);
    }

    public function getBusinessTokenResponse(array $options): ?Response
    {
        $data = $this->resolveBusinessTokenOptions($options);

        try {
            return $this->client->request('POST', $this->getBusinessTokenUrl(), [
                'json' => $data,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }
    }

    public function getBusinessTokenData(array $options): array
    {
        $tokenResponse = $this->getBusinessTokenResponse($options);

        if (null === $tokenResponse) {
            throw new \UnexpectedValueException('The business token request failed.');
        }

        $tokenData = json_decode((string) $tokenResponse->getBody(), true);

        if (!is_array($tokenData)) {
            throw new \UnexpectedValueException('The business token response can\'t be parsed.');
        }

        return $tokenData;
    }

    public function getBusinessToken(array $options): string
    {
        return $this->getBusinessTokenData($options)['token'];
    }

    /**
     * Partner data exchange link API
     */

    public function getPartnerDataExchangeLinkUrl(): string
    {
        return sprintf('https://%s/partnerDataExchange/v1/links/', $this->apiHostName);
    }

    public function getPartnerDataExchangeLinkResponse(array $options): ?Response
    {
        $data = $this->resolvePartnerDataExchangeLinkOptions($options);

        try {
            return $this->client->request('POST', $this->getPartnerDataExchangeLinkUrl(), [
                'json' => $data,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                    'Context-Applicationid' => $this->contextApplicationId,
                    'Context-Partnerid' => $this->contextPartnerId,
                    'Context-Sourceid' => $this->contextSourceId,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }
    }

    public function getPartnerDataExchangeLinkData(array $options): array
    {
        $partnerDataExchangeLinkResponse = $this->getPartnerDataExchangeLinkResponse($options);

        if (null === $partnerDataExchangeLinkResponse) {
            throw new \UnexpectedValueException('The partner data exchange link request failed.');
        }

        $partnerDataExchangeLinkData = json_decode((string) $partnerDataExchangeLinkResponse->getBody(), true);

        if (!is_array($partnerDataExchangeLinkData)) {
            throw new \UnexpectedValueException('The business token response can\'t be parsed.');
        }

        return $partnerDataExchangeLinkData;
    }

    public function getPartnerDataExchangeUrl(array $options): string
    {
        return $this->getPartnerDataExchangeLinkData($options)['link'];
    }

    /**
     * Gateway credit URL for customer
     */

    public function getCreditUrl(array $options): string
    {
        return sprintf(
            'https://%s/creditpartner/?q6=%s&x1=%s&token=%s',
            $this->serverHostName,
            $this->contextPartnerId,
            $this->contextSourceId,
            $this->getBusinessToken($options)
        );
    }

    /**
     * Partner Loan Portfolio API
     */

    public function getPartnerLoanPortfolioUrl(): string
    {
        return sprintf('https://%s/partnerLoanPortfolio/v1', $this->apiHostName);
    }

    public function getContractById(string $contractId, array $options): ?array
    {
        $response = null;

        $data = $this->resolveContractByIdOptions($options);
        try {
            $response = $this->client->request('GET', sprintf('%s/contracts/%s', $this->getPartnerLoanPortfolioUrl(), $contractId), [
                'json' => $data,
                'decode_content' => false,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }

        if (null === $response) {
            throw new \UnexpectedValueException('The partner loan portfolio contract by id request failed.');
        }

        $contractData = json_decode((string) $response->getBody(), true);

        if (!is_array($contractData)) {
            throw new \UnexpectedValueException('The contract response can\'t be parsed.');
        }

        return $contractData;
    }

    public function getContractByExternalId(array $options): ?array
    {
        $response = null;

        $data = $this->resolveContractByExternalIdOptions($options);
        try {
            $response = $this->client->request('GET', sprintf('%s/contracts', $this->getPartnerLoanPortfolioUrl()), [
                'json' => $data,
                'decode_content' => false,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }

        if (null === $response) {
            throw new \UnexpectedValueException('The partner loan portfolio contract by external id request failed.');
        }

        $contractData = json_decode((string) $response->getBody(), true);

        if (!is_array($contractData)) {
            throw new \UnexpectedValueException('The contract response can\'t be parsed.');
        }

        return $contractData;
    }

    private function resolveContractByIdOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('businessProviderId')->setAllowedTypes('businessProviderId', ['string'])
            ->setRequired('equipmentCode')->setAllowedTypes('equipmentCode', ['string'])
            ->setRequired('orderId')->setAllowedTypes('orderId', ['string'])
        ;

        return $resolver->resolve($options);
    }

    private function resolveContractByExternalIdOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('partnerExternalId')->setAllowedTypes('partnerExternalId', ['string'])
            ->setRequired('businessProviderId')->setAllowedTypes('businessProviderId', ['string'])
            ->setRequired('equipmentCode')->setAllowedTypes('equipmentCode', ['string'])
        ;

        return $resolver->resolve($options);
    }

    /**
     * Order Status Notification API
     */

    public function getOrderStatusNotificationUrl(): string
    {
        return sprintf('https://%s/orderStatusNotification/v1/', $this->apiHostName);
    }

    public function deliverContract(string $id): ?Response
    {
        $response = null;

        try {
            $response = $this->client->request('POST', sprintf('%s/contracts/%s/order/deliver', $this->getOrderStatusNotificationUrl(), $id), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e);

            return null;
        }

        if (null === $response) {
            throw new \UnexpectedValueException('The order status notification deliver order request failed.');
        }

        return $response;
    }

    public function cancelContract(string $id): ?Response
    {
        $response = null;

        try {
            $response = $this->client->request('POST', sprintf('%s/contracts/%s/order/cancel', $this->getOrderStatusNotificationUrl(), $id), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e);

            return null;
        }

        if (null === $response) {
            throw new \UnexpectedValueException('The order status notification cancel order request failed.');
        }

        return $response;
    }

    public function validateOrder(string $id, array $options): ?Response
    {
        $response = null;

        $data = $this->resolveValidateOrderOptions($options);
        try {
            $response = $this->client->request('POST', sprintf('%s/orders/%s/validate', $this->getOrderStatusNotificationUrl(), $id), [
                'json' => $data,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e, $data);

            return null;
        }

        if (null === $response) {
            throw new \UnexpectedValueException('The order status notification validate order request failed.');
        }

        return $response;
    }

    private function resolveValidateOrderOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('businessProviderId')->setAllowedTypes('businessProviderId', ['string'])
        ;

        return $resolver->resolve($options);
    }

    /**
     * Documents API
     */

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
                    'Context-Applicationid' => $this->contextApplicationId,
                    'Context-Partnerid' => $this->contextPartnerId,
                    'Context-Sourceid' => $this->contextSourceId,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logRequestException($e);

            return null;
        }
    }

    public function getDocuments(array $options): Crawler
    {
        $loanSimulationResponse = $this->getDocumentsResponse($options);

        if (null === $loanSimulationResponse) {
            throw new \UnexpectedValueException('The loan simulations request failed.');
        }

        return new Crawler((string) $loanSimulationResponse->getBody());
    }

    /**
     * Options Resolver
     */

    /**
     * Options Resolver > Simulation API
     */

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

    /**
     * Options Resolver > Business token API
     */

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

                    return null !== $value ? (int) $value : null;
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

                    return null !== $value ? (float) $value : null;
                })
            ->setDefined('orderAmount')->setAllowedTypes('orderAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('orderAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "orderAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
                })
            ->setDefined('personalContributionAmount')->setAllowedTypes('personalContributionAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('personalContributionAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "personalContributionAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
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

    /**
     * Options Resolver > Partner data exchange link API
     */

    private function resolvePartnerDataExchangeLinkOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefault('tokenFormat', self::BUSINESS_TOKEN_FORMAT_OPAQUE)->setAllowedValues('tokenFormat', [
                self::BUSINESS_TOKEN_FORMAT_OPAQUE,
                self::BUSINESS_TOKEN_FORMAT_JWS,
                self::BUSINESS_TOKEN_FORMAT_JWE,
            ])
            ->setDefault('tokenDuration', self::BUSINESS_TOKEN_DURATION)->setAllowedTypes('tokenDuration', ['int'])
            ->setRequired('businessContext')->setAllowedTypes('businessContext', ['string', 'array'])
                ->setNormalizer('businessContext', function (Options $options, $value) {
                    if (is_string($value)) {
                        $value = json_decode($value, true);

                        if (null === $value) {
                            throw new \InvalidArgumentException('The "businessContext" parameter is not a valid json string');
                        }
                    }

                    return json_encode($this->resolvePartnerDataExchangeLinkBusinessContextOptions($value));
                })
            ->setDefined('customer')->setAllowedTypes('customer', ['string', 'array'])
                ->setNormalizer('customer', function (Options $options, $value) {
                    if (is_string($value)) {
                        $value = json_decode($value, true);

                        if (null === $value) {
                            throw new \InvalidArgumentException('The "customer" parameter is not a valid json string');
                        }
                    }

                    return json_encode($this->resolvePartnerDataExchangeLinkCustomerOptions($value));
                })
            ->setDefined('order')->setAllowedTypes('order', ['string', 'array'])
                ->setNormalizer('order', function (Options $options, $value) {
                    if (is_string($value)) {
                        $value = json_decode($value, true);

                        if (null === $value) {
                            throw new \InvalidArgumentException('The "order" parameter is not a valid json string');
                        }
                    }

                    return json_encode($this->resolvePartnerDataExchangeLinkOrderOptions($value));
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty(array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolvePartnerDataExchangeLinkBusinessContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setRequired('providerContext')->setAllowedTypes('providerContext', ['array'])
                ->setNormalizer('providerContext', function (Options $options, $value) {
                    return $this->resolvePartnerDataExchangeLinkBusinessProviderContextOptions($value);
                })
            ->setDefined('customerContext')->setAllowedTypes('customerContext', ['array'])
                ->setNormalizer('customerContext', function (Options $options, $value) {
                    return $this->resolvePartnerDataExchangeLinkBusinessCustomerContextOptions($value);
                })
            ->setDefined('offerContext')->setAllowedTypes('offerContext', ['array'])
                ->setNormalizer('offerContext', function (Options $options, $value) {
                    return $this->resolvePartnerDataExchangeLinkBusinessOfferContextOptions($value);
                })
        ;

        return array_filter($resolver->resolve($options), function ($a) { return !empty($a); });
    }

    private function resolvePartnerDataExchangeLinkBusinessProviderContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('businessProviderId')->setAllowedTypes('businessProviderId', ['null', 'string'])
                ->setNormalizer('businessProviderId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{11}/', $value)) {
                        throw new \InvalidArgumentException('The "businessProviderId" parameter must be formatted as described in documentation "[A-Z0-9]{11}"');
                    }

                    return $value;
                })
            ->setDefined('returnUrl')->setAllowedTypes('returnUrl', ['null', 'string'])
                ->setNormalizer('returnUrl', function (Options $options, $value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('The "returnUrl" parameter is not a valid URL');
                    }

                    return $value;
                })
            ->setDefined('exchangeUrl')->setAllowedTypes('exchangeUrl', ['null', 'string'])
                ->setNormalizer('exchangeUrl', function (Options $options, $value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('The "exchangeUrl" parameter is not a valid URL');
                    }

                    return $value;
                })
            ->setDefined('homeReturnUrl')->setAllowedTypes('homeReturnUrl', ['null', 'string'])
                ->setNormalizer('homeReturnUrl', function (Options $options, $value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('The "homeReturnUrl" parameter is not a valid URL');
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

    private function resolvePartnerDataExchangeLinkBusinessCustomerContextOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('externalCustomerId')->setAllowedTypes('externalCustomerId', ['null', 'string'])
                ->setNormalizer('externalCustomerId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "externalCustomerId" parameter must be formatted as described in documentation "[A-Z0-9]{0,16}"');
                    }

                    return $value;
                })
            ->setDefined('memberAccountId')->setAllowedTypes('memberAccountId', ['null', 'string'])
                ->setNormalizer('memberAccountId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "memberAccountId" parameter must be formatted as described in documentation "[A-Z0-9]{0,16}"');
                    }

                    return $value;
                })
            ->setDefined('member')->setAllowedTypes('member', ['bool'])
            ->setDefined('memberAccountDate')->setAllowedTypes('memberAccountDate', ['null', 'string'])
                ->setNormalizer('memberAccountDate', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "memberAccountDate" parameter must be formatted as described in documentation "[0-9]{4}-[0-9]{2}-[0-9]{2}"');
                    }

                    return $value;
                })
            ->setDefined('lastUncancelledPurchaseDate')->setAllowedTypes('lastUncancelledPurchaseDate', ['null', 'string'])
                ->setNormalizer('lastUncancelledPurchaseDate', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
                        throw new \InvalidArgumentException('The "lastUncancelledPurchaseDate" parameter must be formatted as described in documentation "[0-9]{4}-[0-9]{2}-[0-9]{2}"');
                    }

                    return $value;
                })
            ->setDefined('totalPurchasesNumber')->setAllowedTypes('totalPurchasesNumber', ['null', 'int', 'string'])
                ->setNormalizer('totalPurchasesNumber', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "totalPurchasesNumber" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('uncancelledPurchasesNumber')->setAllowedTypes('uncancelledPurchasesNumber', ['null', 'int', 'string'])
                ->setNormalizer('uncancelledPurchasesNumber', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "uncancelledPurchasesNumber" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('scoringCancelledPurchasesNumber')->setAllowedTypes('scoringCancelledPurchasesNumber', ['null', 'int', 'string'])
                ->setNormalizer('scoringCancelledPurchasesNumber', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "scoringCancelledPurchasesNumber" parameter must be formatted as described in documentation "[0-9]{5}"');
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

                    return null !== $value ? (int) $value : null;
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
            ->setDefined('birthZipCode')->setAllowedTypes('birthZipCode', ['null', 'int', 'string'])
                ->setNormalizer('birthZipCode', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "birthZipCode" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('birthCity')->setAllowedTypes('birthCity', ['null', 'string'])
                ->setNormalizer('birthCity', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "city" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
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

    private function resolvePartnerDataExchangeLinkBusinessOfferContextOptions(array $options): array
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
            ->setDefined('scaleIds')->setAllowedTypes('scaleIds', ['array'])
            ->setDefined('awaitingFunding')->setAllowedTypes('awaitingFunding', ['bool'])
            ->setDefined('deliveryChoice')->setAllowedTypes('deliveryChoice', ['null', 'string'])
            ->setDefined('paymentChoice')->setAllowedTypes('paymentChoice', ['null', 'string'])
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

                    return null !== $value ? (float) $value : null;
                })
            ->setDefined('orderAmount')->setAllowedTypes('orderAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('orderAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "orderAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
                })
            ->setDefined('duration')->setAllowedTypes('duration', ['null', 'string'])
                ->setNormalizer('duration', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,3}/', $value)) {
                        throw new \InvalidArgumentException('The "duration" parameter must be formatted as described in documentation "[0-9]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('suggestedDueNumber')->setAllowedTypes('suggestedDueNumber', ['null', 'float', 'int', 'string'])
                ->setNormalizer('suggestedDueNumber', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,3}/', (string) $value)) {
                        throw new \InvalidArgumentException('The "suggestedDueNumber" parameter must be formatted as described in documentation "[0-9]{0,3}"');
                    }

                    return $value;
                })
            ->setDefined('suggestedScaleId')->setAllowedTypes('suggestedScaleId', ['null', 'string'])
                ->setNormalizer('suggestedScaleId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{8}/', $value)) {
                        throw new \InvalidArgumentException('The "suggestedScaleId" parameter must be formatted as described in documentation "[A-Z0-9]{8}"');
                    }

                    return $value;
                })
            ->setDefined('deliveryAdditionalStreet')->setAllowedTypes('deliveryAdditionalStreet', ['null', 'string'])
                ->setNormalizer('deliveryAdditionalStreet', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "deliveryAdditionalStreet" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('deliveryStreet')->setAllowedTypes('deliveryStreet', ['null', 'string'])
                ->setNormalizer('deliveryStreet', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "deliveryStreet" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('deliveryCity')->setAllowedTypes('deliveryCity', ['null', 'string'])
                ->setNormalizer('deliveryCity', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "deliveryCity" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('deliveryZipCode')->setAllowedTypes('deliveryZipCode', ['null', 'int', 'string'])
                ->setNormalizer('deliveryZipCode', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "deliveryZipCode" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('deliveryDistributerOffice')->setAllowedTypes('deliveryDistributerOffice', ['null', 'string'])
                ->setNormalizer('deliveryDistributerOffice', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "deliveryDistributerOffice" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('billingAdditionalStreet')->setAllowedTypes('billingAdditionalStreet', ['null', 'string'])
                ->setNormalizer('billingAdditionalStreet', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "billingAdditionalStreet" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('billingStreet')->setAllowedTypes('billingStreet', ['null', 'string'])
                ->setNormalizer('billingStreet', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "billingStreet" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('billingCity')->setAllowedTypes('billingCity', ['null', 'string'])
                ->setNormalizer('billingCity', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "billingCity" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('billingZipCode')->setAllowedTypes('billingZipCode', ['null', 'int', 'string'])
                ->setNormalizer('billingZipCode', function (Options $options, $value) {
                    if (is_int($value)) {
                        $value = (string) $value;
                    }

                    if (is_string($value) && 1 !== preg_match('/[0-9]{5}/', $value)) {
                        throw new \InvalidArgumentException('The "billingZipCode" parameter must be formatted as described in documentation "[0-9]{5}"');
                    }

                    return $value;
                })
            ->setDefined('billingDistributerOffice')->setAllowedTypes('billingDistributerOffice', ['null', 'string'])
                ->setNormalizer('billingDistributerOffice', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,50}/', $value)) {
                        throw new \InvalidArgumentException('The "billingDistributerOffice" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,50}"');
                    }

                    return $value;
                })
            ->setDefined('Cart')->setAllowedTypes('Cart', ['array'])
                ->setNormalizer('Cart', function (Options $options, $value) {
                    return $this->resolvePartnerDataExchangeLinkBusinessOfferContextCartOptions($value);
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolvePartnerDataExchangeLinkBusinessOfferContextCartOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('products')->setAllowedTypes('products', ['array'])
                ->setNormalizer('products', function (Options $options, $value) {
                    $products = [];
                    foreach ($value as $product) {
                        $products[] = $this->resolvePartnerDataExchangeLinkBusinessOfferContextCartProductOptions($product);
                    }

                    return $products;
                })
            ->setDefined('totalAmount')->setAllowedTypes('totalAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('totalAmount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "totalAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolvePartnerDataExchangeLinkBusinessOfferContextCartProductOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('index')->setAllowedTypes('totalAmount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('index', function (Options $options, $value) {
                    return null !== $value ? (int) $value : null;
                })
            ->setDefined('label')->setAllowedTypes('label', ['null', 'string'])
                ->setNormalizer('label', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "label" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('family')->setAllowedTypes('family', ['null', 'string'])
                ->setNormalizer('family', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z \'-]{0,32}/', $value)) {
                        throw new \InvalidArgumentException('The "family" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,32}"');
                    }

                    return $value;
                })
            ->setDefined('amount')->setAllowedTypes('amount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('amount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "amount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
                })
            ->setDefined('quantity')->setAllowedTypes('quantity', ['null', 'float', 'int', 'string'])
                ->setNormalizer('quantity', function (Options $options, $value) {
                    return null !== $value ? (int) $value : null;
                })
            ->setDefined('engagementDuration')->setAllowedTypes('engagementDuration', ['null', 'float', 'int', 'string'])
                ->setNormalizer('engagementDuration', function (Options $options, $value) {
                    return null !== $value ? (int) $value : null;
                })
            ->setDefined('ean')->setAllowedTypes('ean', ['bool'])
                ->setNormalizer('ean', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[a-zA-Z0-9]{13}/', $value)) {
                        throw new \InvalidArgumentException('The "ean" parameter must be formatted as described in documentation "[A-Z0-9]{13}"');
                    }

                    return $value;
                })
            ->setDefined('isTradeForward')->setAllowedTypes('isTradeForward', ['bool'])
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolvePartnerDataExchangeLinkCustomerOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
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
            ->setDefined('mobilePhoneNumber')->setAllowedTypes('mobilePhoneNumber', ['null', 'string'])
                ->setNormalizer('mobilePhoneNumber', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9 -]{0,16}/', $value)) {
                        throw new \InvalidArgumentException('The "lastName" parameter must be formatted as described in documentation "[a-zA-Z \'-]{0,20}"');
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
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }

    private function resolvePartnerDataExchangeLinkOrderOptions(array $options): array
    {
        $resolver = (new OptionsResolver())
            ->setDefined('id')->setAllowedTypes('id', ['null', 'string'])
            ->setDefined('businessProviderId')->setAllowedTypes('businessProviderId', ['null', 'string'])
                ->setNormalizer('businessProviderId', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[A-Z0-9]{11}/', $value)) {
                        throw new \InvalidArgumentException('The "businessProviderId" parameter must be formatted as described in documentation "[A-Z0-9]{11}"');
                    }

                    return $value;
                })
            ->setDefined('amount')->setAllowedTypes('amount', ['null', 'float', 'int', 'string'])
                ->setNormalizer('amount', function (Options $options, $value) {
                    if (is_string($value) && 1 !== preg_match('/[0-9]{0,9}/', $value)) {
                        throw new \InvalidArgumentException('The "totalAmount" parameter must be formatted as described in documentation "[0-9]{0,9}"');
                    }

                    return null !== $value ? (float) $value : null;
                })
        ;

        $resolvedOptions = $resolver->resolve($options);
        if (empty($resolvedOptions = array_filter($resolvedOptions, function ($a) { return null !== $a; }))) {
            return [];
        }

        return $resolvedOptions;
    }


    /**
     * Options Resolver > Documents API
     */

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

    private function logRequestException(RequestException $e, array $data = []): void
    {
        $this->logger->error(
            sprintf(
                'Method : %s | URL : %s | Status : %s | Data : %s | Message : %s',
                $e->getRequest()->getMethod(),
                $e->getRequest()->getUri(),
                null != $e->getResponse() ? (string) $e->getResponse()->getStatusCode() : null,
                json_encode($data),
                null != $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage()
            )
        );
    }
}
