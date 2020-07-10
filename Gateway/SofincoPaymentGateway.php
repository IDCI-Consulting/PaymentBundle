<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SofincoPaymentGateway extends AbstractPaymentGateway
{
    const OFFER_MODULE = 'WSACCROCHE';

    const PRODUCT_MODULE = 'PRODUCT';

    const CART_MODULE = 'PANIER';

    /**
     * @var string
     */
    private $serverUrl;

    public function __construct(
        \Twig_Environment $templating,
        EventDispatcherInterface $dispatcher,
        string $serverUrl
    ) {
        parent::__construct($templating, $dispatcher);

        $this->serverUrl = $serverUrl;
    }

    private function buildOfferVerifyOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'q6' => $paymentGatewayConfiguration->get('site_id'),
            'p0' => self::OFFER_MODULE,
            'p4' => $transaction->getAmount() / 100,
        ];
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'q6' => $paymentGatewayConfiguration->get('site_id'),
            'p0' => self::CART_MODULE,
            'ti' => $transaction->getId(),
            's3' => $transaction->getAmount() / 100,
            'uret' => $paymentGatewayConfiguration->get('return_url'),
            'p5' => $paymentGatewayConfiguration->get('callback_url'),
        ];
    }

    private function verifyIfOfferExist(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
        $options = $this->buildOfferVerifyOptions($paymentGatewayConfiguration, $transaction);

        $response = (new Client())->request('GET', $this->serverUrl, [
            'query' => $options,
        ]);

        $data = json_decode(json_encode(new \SimpleXMLElement($response->getBody()->getContents())), true);

        if ('00' !== $data['C_RETOUR']) {
            throw new \UnexpectedValueException($data['LL_MSG']);
        }

        return true;
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $this->verifyIfOfferExist($paymentGatewayConfiguration, $transaction);

        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

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

        return $this->templating->render('@IDCIPayment/Gateway/sofinco.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod(Request::METHOD_GET)) {
            throw new \UnexpectedValueException('Sofinco : Payment Gateway error (Request method should be GET)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (1 == $request->query->get('c3')) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        $gatewayResponse
            ->setTransactionUuid($request->query->get('ti'))
            ->setAmount($request->query->get('s3'))
        ;

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_UNVERIFIED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'site_id',
            ]
        );
    }
}
