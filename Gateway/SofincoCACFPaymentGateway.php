<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\Client\SofincoCACFPaymentGatewayClient;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class SofincoCACFPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var SofincoCACFPaymentGatewayClient
     */
    private $client;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        SofincoCACFPaymentGatewayClient $client
    ) {
        parent::__construct($templating, $dispatcher);

        $this->client = $client;
    }

    /**
     * Build gateway form options.
     *
     * @method buildOptions
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options
    ): array {
        return array_replace_recursive([
            'businessContext' => [
                'providerContext' => [
                    'businessProviderId' => $paymentGatewayConfiguration->get('business_provider_id'),
                    'returnUrl' => $paymentGatewayConfiguration->get('return_url'),
                    'exchangeUrl' => $this->buildExchangeUrl($paymentGatewayConfiguration->get('callback_url'), $transaction->getId()),
                ],
                'customerContext' => [
                    'externalCustomerId' => $transaction->getCustomerId(),
                    'emailAddress' => $transaction->getCustomerEmail(),
                ],
                'offerContext' => [
                    'orderId' => $transaction->getId(),
                    'equipmentCode' => $paymentGatewayConfiguration->get('equipment_code'),
                    'amount' => $transaction->getAmount(),
                ],
            ],
        ], $options);
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction, $options);

        if ($paymentGatewayConfiguration->get('use_partner_data_exchange')) {
            $url = $this->client->getPartnerDataExchangeUrl($options);

            $fields = [];
            parse_str(parse_url($url, PHP_URL_QUERY), $fields);

            return [
                'url' => $url,
                'options' => $fields,
            ];
        }

        $url = $this->client->getCreditUrl($options);

        $fields = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $fields);

        return [
            'url' => $url,
            'options' => $fields,
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

        return $this->templating->render('@IDCIPayment/Gateway/sofinco_cacf.html.twig', [
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
        if (!$request->isMethod(Request::METHOD_POST)) {
            throw new \UnexpectedValueException('Sofinco : Payment Gateway error (Request method should be POST)');
        }

        $requestData = json_decode($request->getContent(), true);

        if (json_last_error()) {
            throw new \UnexpectedValueException(sprintf('Sofinco - JSON Error: %s', json_last_error_msg()));
        }

        if (!$request->query->has('transactionId')) {
            throw new \UnexpectedValueException('The query parameter transactionId was not found.');
        }

        if (strtoupper($request->query->get('transactionId')) !== $requestData['ORDER_ID']) {
            throw new \UnexpectedValueException(sprintf('The transaction/order id mismatch in the data (query: %s, body: %s).', $request->query->get('transactionId'), $requestData['ORDER_ID']));
        }

        $gatewayResponse = (new GatewayResponse())
            ->setTransactionUuid($request->query->get('transactionId'))
            ->setAmount($requestData['AMOUNT'] * 100)
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setRaw($requestData)
        ;

        if (in_array(
            $requestData['CONTRACT_STATUS'],
            [
                SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_REFUSED,
                SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_CANCELED,
                SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_NOT_FOUND,
                SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_ERROR,
                SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_ABANDONNED,
            ]
        )) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        if (SofincoCACFPaymentGatewayClient::DOCUMENT_STATUS_FUNDED === $requestData['CONTRACT_STATUS']) {
            return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_UNVERIFIED);
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'business_provider_id',
                'equipment_code',
                'use_partner_data_exchange',
            ]
        );
    }

    /**
     * Build exchange url with transactionId query parameter.
     */
    private function buildExchangeUrl(string $url, string $transactionId): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if ($query) {
            return sprintf('%s&transactionId=%s', $url, $transactionId);
        }

        return sprintf('%s?transactionId=%s', $url, $transactionId);
    }
}
