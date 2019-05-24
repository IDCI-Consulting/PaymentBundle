<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;

class SystemPayPaymentGateway extends AbstractPaymentGateway
{
    const SIGNATURE_ALGORITHM_SHA1 = 'SHA-1';

    const SIGNATURE_ALGORITHM_SHA256 = 'SHA-256';

    /**
     * @var string
     */
    private $serverUrl;

    public function __construct(
        \Twig_Environment $templating,
        string $serverUrl
    ) {
        parent::__construct($templating);

        $this->serverUrl = $serverUrl;
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'vads_action_mode' => $paymentGatewayConfiguration->get('action_mode'),
            'vads_amount' => $transaction->getAmount(),
            'vads_ctx_mode' => $paymentGatewayConfiguration->get('ctx_mode'),
            'vads_currency' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'vads_cust_id' => $transaction->getCustomerId(),
            'vads_order_id' => $transaction->getItemId(),
            'vads_page_action' => $paymentGatewayConfiguration->get('page_action'),
            'vads_payment_config' => $paymentGatewayConfiguration->get('payment_config'),
            'vads_site_id' => $paymentGatewayConfiguration->get('site_id'),
            'vads_trans_date' => (new \DateTime())->format('Ymdhis'),
            'vads_trans_id' => 0, // [0, 999999]
            'vads_version' => $paymentGatewayConfiguration->get('version'),
        ];
    }

    private function buildSignature(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array $options
    ): string {
        $key = $paymentGatewayConfiguration->get('site_key');
        $rawSignature = mb_convert_encoding(implode('+', $options).$key, 'UTF-8');

        if (self::SIGNATURE_ALGORITHM_SHA1 === $paymentGatewayConfiguration->get('signature_algorithm')) {
            return sha1($rawSignature);
        }

        return base64_encode(hash_hmac('sha256', $rawSignature, $key, true));
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);
        $options['signature'] = $this->buildSignature($paymentGatewayConfiguration, $options);

        return [
            'url' => $this->serverUrl,
            'options' => $options,
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/systempay.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        throw new \BadMethodCallException('The getResponse method of SystemPay gateway has not yet been implemented');
    }

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
}
