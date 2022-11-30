<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

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
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        string $serverUrl
    ) {
        parent::__construct($templating, $dispatcher);

        $this->serverUrl = $serverUrl;
    }

    /**
     * Build options for WSACCROCHE Sofinco module.
     *
     * @method buildOfferVerifyOptions
     */
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

    /**
     * Build gateway form options.
     *
     * @method buildOptions
     */
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

    /**
     * Check if the sofinco offer exists according to transaction amount.
     *
     * @method verifyIfOfferExist
     *
     * @throws \UnexpectedValueException If the offer doesn't exists
     */
    private function verifyIfOfferExist(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): bool {
        $options = $this->buildOfferVerifyOptions($paymentGatewayConfiguration, $transaction);

        $response = (new Client())->request('GET', $this->serverUrl, [
            'query' => $options,
        ]);

        $data = json_decode(json_encode(new \SimpleXMLElement($response->getBody()->getContents())), true);

        if ('00' !== $data['C_RETOUR']) {
            throw new \UnexpectedValueException(sprintf('Error code %s: No offer exists for the contract code "%s" and the amount "%s". Result of the request: %s', $data['C_RETOUR'], $options['q6'], $options['p4'], json_encode($data)));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

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
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/sofinco.html.twig', [
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
     * @throws \UnexpectedValueException If the request method is not GET
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod(Request::METHOD_GET)) {
            throw new \UnexpectedValueException('Sofinco : Payment Gateway error (Request method should be GET)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setTransactionUuid($request->query->get('ti'))
            ->setAmount($request->query->get('s3'))
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setRaw($request->query->all())
        ;

        if (1 == $request->query->get('c3')) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
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
                'site_id',
            ]
        );
    }
}
