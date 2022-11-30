<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Gateway\Client\EurekaPaymentGatewayClient;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\EurekaStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EurekaPaymentGateway extends AbstractPaymentGateway
{
    const HMAC_TYPE_ENTRY = 'in';
    const HMAC_TYPE_OUT = 'out';

    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        EventDispatcherInterface $dispatcher,
        EurekaPaymentGatewayClient $eurekaPaymentGatewayClient
    ) {
        parent::__construct($templating, $dispatcher);

        $this->eurekaPaymentGatewayClient = $eurekaPaymentGatewayClient;
    }

    /**
     * Build payment gateway options.
     *
     * @method buildOptions
     *
     * @throws \UnexpectedValueException If required transaction metadata is not set
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        foreach ($this->getRequiredTransactionMetadata() as $requiredTransactionMetadata) {
            if (!$transaction->hasMetadata($requiredTransactionMetadata)) {
                throw new \UnexpectedValueException(sprintf('The transaction metadata "%s" must be set', $requiredTransactionMetadata));
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
            'currency' => $transaction->getCurrencyCode(),
            'country' => $transaction->getMetadata('Customer.Country'),
            'customerRef' => $transaction->getCustomerId(),
            'date' => (new \DateTime('now'))->format('Ymd'),
            'amount' => $transaction->getAmount(),
            'merchantHomeUrl' => $paymentGatewayConfiguration->get('return_url'),
            'merchantReturnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'merchantNotifyUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'scoringToken' => $this->requestScoringToken(
                $paymentGatewayConfiguration,
                $transaction
            ),
        ];
    }

    /**
     * Request payment gateway request token.
     *
     * @method requestScoringToken
     */
    private function requestScoringToken(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $scoringToken = $this->eurekaPaymentGatewayClient->getScoringToken(
            $paymentGatewayConfiguration->get('score_type'),
            [
                'Header' => [
                    'Context' => [
                        'MerchantId' => $paymentGatewayConfiguration->get('merchant_id'),
                        'MerchantSiteId' => $paymentGatewayConfiguration->get('merchant_site_id'),
                    ],
                    'Localization' => [
                        'Country' => $transaction->getMetadata('Customer.Country'),
                        'Currency' => $transaction->getCurrencyCode(),
                        'DecimalPosition' => $transaction->getMetadata('Order.DecimalPosition'),
                        'Language' => $transaction->getMetadata('Customer.Language'),
                    ],
                    'SecurityContext' => [
                        'TokenId' => $this->eurekaPaymentGatewayClient->getSTSToken(
                            $paymentGatewayConfiguration->get('username'),
                            $paymentGatewayConfiguration->get('password')
                        ),
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
                        'ShoppingCartRef' => $transaction->getId(),
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
                    'OptionalPreScoreInformation' => [
                        'RequestID' => $transaction->getMetadata('PreScoreInformation.RequestID'),
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
            ]
        );

        $transaction->addMetadata('scoring_token', $scoringToken);

        $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return $scoringToken;
    }

    /**
     * Build payment gateway HMAC signature according to its type (IN|OUT).
     *
     * @method buildHmac
     */
    private function buildHmac(array $options, string $hmacType): string
    {
        $hmacData = '';

        foreach ($this->getHmacBuildParameters($hmacType) as $parameterName) {
            if (1 < count($parameterNames = explode('|', $parameterName))) {
                $i = 0;
                while (isset($options[sprintf('%s%s', $parameterNames[0], ++$i)])) {
                    for ($j = 0; $j < count($parameterNames); ++$j) {
                        $hmacData = sprintf('%s*%s', $hmacData, $options[sprintf('%s%s', $parameterNames[$j], $i)]);
                    }
                }

                continue;
            }

            $realParameterName = '?' !== $parameterName[0] ? $parameterName : substr($parameterName, 1);
            if ('?' !== $parameterName[0] || isset($options[$realParameterName])) {
                $hmacData = sprintf('%s*%s', $hmacData, isset($options[$realParameterName]) ? $options[$realParameterName] : '');
            }
        }

        return hash_hmac('sha1', utf8_encode(sprintf('%s*', substr($hmacData, 1))), utf8_encode($options['secretKey']));
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return array_merge($options, [
            'hmac' => $this->buildHmac($options, self::HMAC_TYPE_ENTRY),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/eureka.html.twig', [
            'url' => $this->eurekaPaymentGatewayClient->getPaymentFormUrl(),
            'initializationData' => $initializationData,
        ]);
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

        $hmac = $this->buildHmac(
            array_merge($request->request->all(), ['secretKey' => $paymentGatewayConfiguration->get('secret_key')]),
            self::HMAC_TYPE_OUT
        );

        if (strtolower($request->request->get('hmac')) !== strtolower($hmac)) {
            return $gatewayResponse->setMessage('Hmac check failed');
        }

        $gatewayResponse
            ->setTransactionUuid($request->request->get('orderRef'))
            ->setAmount($request->request->get('amount'))
            ->setCurrencyCode($request->request->get('currency'))
        ;

        if ('0' !== $request->request->get('returnCode')) {
            $gatewayResponse->setMessage(EurekaStatusCode::getStatusMessage($request->request->get('returnCode')));

            if ('6' === $request->request->get('returnCode')) {
                $gatewayResponse->setStatus(PaymentStatus::STATUS_CANCELED);
            }

            return $gatewayResponse;
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
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

    /**
     * Get the HMAC build parameters names according to its type (IN|OUT).
     *
     * @method getHmacBuildParameters
     */
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
                'scheduleDate|scheduleAmount',
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
            '?allowCardStorage',
            '?passwordRequired',
            '?merchantAuthenticateUrl',
            'storedCardID|storedCardLabel',
            'merchantHomeUrl',
            'merchantBackUrl',
            'merchantReturnUrl',
            'merchantNotifyUrl',
        ];
    }

    /**
     * Get required transaction metadata names.
     *
     * @method getRequiredTransactionMetadata
     */
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
}
