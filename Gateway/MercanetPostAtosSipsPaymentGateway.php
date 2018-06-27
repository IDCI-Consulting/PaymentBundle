<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Exception\UnexpectedAtosSipsResponseCodeException;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MercanetPostAtosSipsPaymentGateway extends AbstractAtosSipsSealPaymentGateway
{
    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        TransactionManagerInterface $transactionManager,
        string $serverHostName
    ) {
        parent::__construct($templating, $router, $transactionManager);

        $this->serverHostName = $serverHostName;
    }

    protected function getServerUrl(): string
    {
        return sprintf('https://%s/paymentInit', $this->serverHostName);
    }

    protected function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackRoute = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());

        return [
            'amount' => $transaction->getAmount(),
            'automaticResponseUrl' => $callbackRoute,
            'captureDay' => $paymentGatewayConfiguration->get('capture_day'),
            'captureMode' => $paymentGatewayConfiguration->get('capture_mode'),
            'currencyCode' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'normalReturnUrl' => $callbackRoute,
            'orderId' => $transaction->getItemId(),
            'transactionReference' => $transaction->getId(),
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
        ];
    }

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

    private function buildSeal(array $options): string
    {
        return hash('sha256', mb_convert_encoding($options['build'].$options['secret'], 'UTF-8'));
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/mercanet_post_atos_sips.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(Transaction::STATUS_FAILED)
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
            ->setRaw($returnParams)
        ;

        if ('00' !== $returnParams['responseCode']) {
            $gatewayResponse->setMessage($returnParams['responseCode']); // To replace with message of the current code (in UnexpectedAtosSipsResponseCodeException)

            if ('17' === $returnParams['responseCode']) {
                return $gatewayResponse->setStatus(Transaction::STATUS_CANCELED);
            }

            return $gatewayResponse;
        }

        if (
            'SUCCESS' !== $returnParams['holderAuthentStatus'] &&
            '3D_SUCCESS' !== $returnParams['holderAuthentStatus']
        ) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        $transaction = $this->transactionManager->retrieveTransactionByUuid($returnParams['transactionReference']);

        if ($transaction->getAmount() != $returnParams['amount']) {
            return $gatewayResponse->setMessage('The amount of the transaction does not match with the initial transaction amount');
        }

        return $gatewayResponse->setStatus(Transaction::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'version',
            'secret',
            'merchant_id',
            'capture_mode',
            'capture_day',
            'interface_version',
        ];
    }
}
