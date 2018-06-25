<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\InvalidAtosSipsInitializationException;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SogenactifBinAtosSipsPaymentGateway extends AbstractAtosSipsSealPaymentGateway
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

    public function getServerUrl(): string
    {
        return null;
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackRoute = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());

        return [
            'amount' => $transaction->getAmount(),
            'automatic_response_url' => $callbackRoute,
            'cancel_return_url' => $callbackRoute,
            'capture_day' => $paymentGatewayConfiguration->get('capture_day'),
            'capture_mode' => $paymentGatewayConfiguration->get('capture_mode'),
            'currency_code' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'merchant_id' => $paymentGatewayConfiguration->get('merchant_id'),
            'normal_return_url' => $callbackRoute,
            'order_id' => $transaction->getId(),
            'pathfile' => $this->pathfile,
        ];
    }

    private function initializeGateway(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
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

        return $form;
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $initializationData = $this->initializeGateway($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/sogenactif_bin_atos_sips.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function buildResponseParams(Request $request)
    {
        if (!$request->request->has('DATA')) {
            throw new \InvalidArgumentException("The request do not contains 'Data'");
        }

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

    public function retrieveTransactionUuid(Request $request): ?string
    {
        return $this->buildResponseParams($request)['order_id'];
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool {
        $returnParams = $this->buildResponseParams($request);

        if ('0' !== $returnParams['code'] && '00' !== $returnParams['response_code']) {
            throw new \Exception('Transaction unauthorized');
        }

        if ($transaction->getAmount() != $returnParams['amount']) {
            throw new \Exception('Amount');
        }

        return true;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'version',
            'secret',
            'merchant_id',
            'automatic_response_url',
            'normal_return_url',
            'capture_mode',
            'capture_day',
        ];
    }
}
