<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Exception\InvalidAtosSipsInitializationException;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AtosSipsStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AtosSipsBinPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var string
     */
    private $pathfile;

    /**
     * @var string
     */
    private $requestBinPath;

    /**
     * @var string
     */
    private $responseBinPath;

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        string $pathfile,
        string $requestBinPath,
        string $responseBinPath
    ) {
        parent::__construct($templating, $router);

        $this->pathfile = $pathfile;
        $this->requestBinPath = $requestBinPath;
        $this->responseBinPath = $responseBinPath;
    }

    private function buildResponseParams(Request $request)
    {
        $shellOptions = array(
            'pathfile' => $this->pathfile,
            'message' => $request->request->get('DATA'),
        );

        $args = implode(' ', array_map(
            function ($k, $v) { return sprintf('%s=%s', $k, $v); },
            array_keys($shellOptions),
            $shellOptions
        ));

        $process = new Process(sprintf('%s %s',
            $this->responseBinPath,
            $args
        ));
        $process->run();

        $keys = [
            '_',
            'code',
            'error',
            'merchant_id',
            'merchant_country',
            'amount',
            'transaction_id',
            'payment_means',
            'transmission_date',
            'payment_time',
            'payment_date',
            'response_code',
            'payment_certificate',
            'authorisation_id',
            'currency_code',
            'card_number',
            'cvv_flag',
            'cvv_response_code',
            'bank_response_code',
            'complementary_code',
            'complementary_info',
            'return_context',
            'caddie',
            'receipt_complement',
            'merchant_language',
            'language',
            'customer_id',
            'order_id',
            'customer_email',
            'customer_ip_address',
            'capture_day',
            'capture_mode',
            'data',
            'order_validity',
            'transaction_condition',
            'statement_reference',
            'card_validity',
            'score_value',
            'score_color',
            'score_info',
            'score_thershold',
            'score_profile',
            '_',
            '_',
            '_',
        ];

        $params = array_combine($keys, explode('!', $process->getOutput()));
        unset($params['_']);

        return $params;
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackUrl = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());
        $returnUrl = $this->getReturnURL($paymentGatewayConfiguration->getAlias(), [
            'transaction_id' => $transaction->getId(),
        ]);

        return [
            'amount' => $transaction->getAmount(),
            'automatic_response_url' => $returnUrl,
            'cancel_return_url' => $callbackUrl,
            'capture_day' => $paymentGatewayConfiguration->get('capture_day'),
            'capture_mode' => $paymentGatewayConfiguration->get('capture_mode'),
            'currency_code' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'merchant_id' => $paymentGatewayConfiguration->get('merchant_id'),
            'normal_return_url' => $callbackUrl,
            'order_id' => $transaction->getId(),
            'pathfile' => $this->pathfile,
        ];
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        ksort($options);

        $builtOptions = implode(' ', array_map(
            function ($k, $v) {
                if (null !== $v) {
                    return sprintf('%s="%s"', $k, $v);
                }
            },
            array_keys($options),
            $options
        ));

        $process = new Process(sprintf('%s %s', $this->requestBinPath, $builtOptions));
        $process->run();

        if (empty($process->getOutput())) {
            throw new InvalidAtosSipsInitializationException('Empty data response');
        }

        list($_, $code, $error, $form) = explode('!', $process->getOutput());
        if ('0' !== $code) {
            throw new InvalidAtosSipsInitializationException($error);
        }

        return [
            'form' => $form,
        ];
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (!$request->request->has('DATA')) {
            return $gatewayResponse->setMessage('The request do not contains "DATA"');
        }

        $returnParams = $this->buildResponseParams($request);

        $gatewayResponse
            ->setRaw($returnParams)
            ->setTransactionUuid($returnParams['order_id'])
            ->setAmount($returnParams['amount'])
            ->setCurrencyCode((new ISO4217())->findByNumeric($returnParams['currency_code'])->getAlpha3())
        ;

        if ('00' !== $returnParams['response_code']) {
            $gatewayResponse->setMessage(AtosSipsStatusCode::STATUS[$returnParams['response_code']]);

            if ('17' === $returnParams['response_code']) {
                $gatewayResponse->setStatus(PaymentStatus::STATUS_CANCELED);
            }

            return $gatewayResponse;
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'merchant_id',
            'capture_mode',
            'capture_day',
        ];
    }
}