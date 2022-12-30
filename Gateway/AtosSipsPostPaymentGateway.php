<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AtosSipsStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class AtosSipsPostPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        string $serverHostName
    ) {
        parent::__construct($templating, $dispatcher);

        $this->serverHostName = $serverHostName;
    }

    /**
     * Get Atos Sips POST server url.
     *
     * @method getServerUrl
     */
    private function getServerUrl(): string
    {
        return sprintf('https://%s/paymentInit', $this->serverHostName);
    }

    /**
     * Build payment gateway options.
     *
     * @method buildOptions
     */
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
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'normalReturnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'transactionReference' => $transaction->getId(),
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
        ];
    }

    /**
     * Build payment gateway signature hash.
     *
     * @method buildSeal
     */
    private function buildSeal(array $options): string
    {
        return hash('sha256', mb_convert_encoding($options['build'].$options['secret'], 'UTF-8'));
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        $builtOptions = [
            'options' => $options,
            'build' => implode('|', array_map(
                function ($k, $v) {
                    if (null !== $v) {
                        return sprintf('%s=%s', $k, $v);
                    }
                },
                array_keys($options),
                $options
            )),
            'secret' => $paymentGatewayConfiguration->get('secret'),
        ];

        return [
            'url' => $this->getServerUrl(),
            'interfaceVersion' => $paymentGatewayConfiguration->get('interface_version'),
            'build' => $builtOptions['build'],
            'seal' => $this->buildSeal($builtOptions),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/atos_sips_post.html.twig', [
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
            throw new \UnexpectedValueException('Atos SIPS : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (!$request->request->has('Data')) {
            return $gatewayResponse->setMessage('The request do not contains "Data"');
        }

        $seal = hash('sha256', $request->get('Data').$paymentGatewayConfiguration->get('secret'));

        if ($request->request->get('Seal') != $seal) {
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

    /**
     * {@inheritdoc}
     */
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
                'interface_version',
            ]
        );
    }
}
