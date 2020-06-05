<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Exception\UnexpectedAtosSipsResponseCodeException;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AtosSipsStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;

class AtosSipsJsonPaymentGateway extends AbstractPaymentGateway
{
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
    }

    private function buildSeal(array $options, string $secretKey)
    {
        $dataForSeal = '';

        foreach ($options as $key => $value) {
            if ('keyVersion' !== $key && 'sealAlgorithm' !== $key) {
                $dataForSeal .= $value;
            }
        }

        $dataToSend = utf8_encode($dataForSeal);

        return hash_hmac('sha256', $dataToSend, $secretKey);
    }

    protected function getServerUrl(): string
    {
        return sprintf('https://%s/rs-services/v2/paymentInit', $this->serverHostName);
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'amount' => $transaction->getAmount(),
            'automaticResponseUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'captureDay' => $paymentGatewayConfiguration->get('capture_day'),
            'captureMode' => $paymentGatewayConfiguration->get('capture_mode'),
            'currencyCode' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'interfaceVersion' => $paymentGatewayConfiguration->get('interface_version'),
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'normalReturnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'orderChannel' => $paymentGatewayConfiguration->get('order_channel'),
            'transactionReference' => $transaction->getId(),
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
        ];
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);
        $options['seal'] = $this->buildSeal($options, $paymentGatewayConfiguration->get('secret'));

        $client = new Client(['defaults' => ['verify' => false]]);

        $response = $client->request('POST', $this->getServerUrl(), ['json' => $options]);

        $returnParams = json_decode($response->getBody(), true);

        if (0 == count($returnParams)) {
            throw new \UnexpectedValueException('Atos SIPS : Initialization error (empty data response)');
        }

        if ('00' != $returnParams['redirectionStatusCode']) {
            throw new UnexpectedAtosSipsResponseCodeException(
                AtosSipsStatusCode::getStatusMessage($returnParams['redirectionStatusCode'])
            );
        }

        return $returnParams;
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/atos_sips_json.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('POST')) {
            throw new \UnexpectedValueException('Atos SIPS : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (!$request->request->has('Data')) {
            return $gatewayResponse->setMessage('The request do not contains "Data"');
        }

        $gatewayResponse->setRaw($request->get('Data'));

        $seal = hash('sha256', $request->get('Data').$paymentGatewayConfiguration->get('secret'));

        if ($request->request->get('Seal') !== $seal) {
            return $gatewayResponse->setMessage('Seal check failed');
        }

        $returnParams = [];

        foreach (explode('|', $request->get('Data')) as $data) {
            $param = explode('=', $data);
            $returnParams[$param[0]] = $param[1];
        }

        $gatewayResponse
            ->setTransactionUuid($returnParams['transactionReference'])
            ->setAmount($returnParams['amount'])
            ->setCurrencyCode((new ISO4217())->findByNumeric($returnParams['currencyCode'])->getAlpha3())
            ->setRaw($returnParams)
        ;

        if ('00' !== $returnParams['responseCode']) {
            $gatewayResponse->setMessage(AtosSipsStatusCode::getStatusMessage($returnParams['responseCode']));

            if ('17' === $returnParams['responseCode']) {
                $gatewayResponse->setStatus(PaymentStatus::STATUS_CANCELED);
            }

            return $gatewayResponse;
        }

        if (
            'SUCCESS' !== $returnParams['holderAuthentStatus'] &&
            '3D_SUCCESS' !== $returnParams['holderAuthentStatus']
        ) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'version',
                'secret',
                'merchant_id',
                'capture_mode',
                'capture_day',
                'order_channel',
                'interface_version',
            ]
        );
    }
}
