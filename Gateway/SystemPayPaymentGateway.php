<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\SystemPayAuthStatusCode;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\SystemPayTransactionStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class SystemPayPaymentGateway extends AbstractPaymentGateway
{
    const SIGNATURE_ALGORITHM_SHA1 = 'SHA-1';

    const SIGNATURE_ALGORITHM_SHA256 = 'SHA-256';

    /**
     * @var string
     */
    private $serverUrl;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        string $serverUrl
    ) {
        parent::__construct($templating, $dispatcher);

        $this->serverUrl = $serverUrl;
    }

    /**
     * Build gateway options.
     *
     * @method buildOptions
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        return array_merge(
            [
                'vads_action_mode' => $paymentGatewayConfiguration->get('action_mode'),
                'vads_amount' => $transaction->getAmount(),
                'vads_ctx_mode' => $paymentGatewayConfiguration->get('ctx_mode'),
                'vads_currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
                'vads_cust_email' => $transaction->getCustomerEmail(),
                'vads_cust_id' => $transaction->getCustomerId(),
                'vads_order_id' => $transaction->getId(),
                'vads_page_action' => $paymentGatewayConfiguration->get('page_action'),
                'vads_payment_config' => $paymentGatewayConfiguration->get('payment_config'),
                'vads_site_id' => $paymentGatewayConfiguration->get('site_id'),
                'vads_trans_date' => (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->format('YmdHis'),
                'vads_trans_id' => sprintf('%06d', $transaction->getNumber()),
                'vads_url_check' => $paymentGatewayConfiguration->get('callback_url'),
                'vads_url_return' => $paymentGatewayConfiguration->get('return_url'),
                'vads_version' => $paymentGatewayConfiguration->get('version'),
            ],
            $options
        );
    }

    /**
     * Build SystemPay HMAC signature accoding to payment gateway coniguration.
     *
     * @method buildSignature
     */
    private function buildSignature(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array $options
    ): string {
        $key = $paymentGatewayConfiguration->get('site_key');
        $rawSignature = mb_convert_encoding(sprintf(
            '%s+%s',
            implode('+', $this->cleanOptions($options)),
            $key
        ), 'UTF-8');

        if (self::SIGNATURE_ALGORITHM_SHA1 === $paymentGatewayConfiguration->get('signature_algorithm')) {
            return sha1($rawSignature);
        }

        return base64_encode(hash_hmac('sha256', $rawSignature, $key, true));
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
        $options['signature'] = $this->buildSignature($paymentGatewayConfiguration, $options);

        return [
            'url' => $this->serverUrl,
            'options' => $options,
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

        return $this->templating->render('@IDCIPayment/Gateway/systempay.html.twig', [
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
            throw new \UnexpectedValueException('Payment Gateway error (Request method should be POST)');
        }

        $requestData = $request->request;

        $gatewayResponse = (new GatewayResponse())
            ->setTransactionUuid($requestData->get('vads_order_id'))
            ->setAmount($requestData->get('vads_amount'))
            ->setCurrencyCode((new ISO4217())->findByNumeric($requestData->get('vads_currency'))->getAlpha3())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setPaymentMethod($requestData->get('vads_card_brand'))
            ->setRaw($request->request->all());

        if ($requestData->get('vads_ctx_mode') != $paymentGatewayConfiguration->get('ctx_mode')) {
            return $gatewayResponse->setMessage(
                sprintf(
                    'Payment gateway environment mismatch, expected %s, got %s',
                    $paymentGatewayConfiguration->get('ctx_mode'),
                    $requestData->get('vads_ctx_mode')
                )
            );
        }

        if ($requestData->get('vads_site_id') != $paymentGatewayConfiguration->get('site_id')) {
            return $gatewayResponse->setMessage(
                sprintf(
                    'The site_id returned by the request "%s" is not the same as the one configured "%s"',
                    $requestData->get('vads_site_id'),
                    $paymentGatewayConfiguration->get('site_id')
                )
            );
        }

        if (SystemPayTransactionStatusCode::isError($requestData->get('vads_trans_status'))) {
            return $gatewayResponse->setMessage(
                SystemPayTransactionStatusCode::getErrorStatusMessage($requestData->get('vads_trans_status'))
            );
        }

        if (SystemPayAuthStatusCode::hasStatus($requestData->get('vads_auth_result'))) {
            return $gatewayResponse->setMessage(
                SystemPayAuthStatusCode::getStatusMessage($requestData->get('vads_auth_result'))
            );
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
                'action_mode',
                'ctx_mode',
                'page_action',
                'payment_config',
                'site_id',
                'site_key',
                'version',
                'signature_algorithm',
            ]
        );
    }

    /**
     * Clean and sort alphabetically options array.
     *
     * @method cleanOptions
     */
    protected function cleanOptions(array $options = []): array
    {
        $cleanedOptions = array_filter($options, function ($value, $name) {
            return 'vads_' === substr($name, 0, 5);
        }, ARRAY_FILTER_USE_BOTH);

        ksort($cleanedOptions);

        return $cleanedOptions;
    }
}
