<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\InvalidAtosSipsInitializationException;
use IDCI\Bundle\PaymentBundle\Exception\UnexpectedAtosSipsResponseCodeException;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SogenactifJsonAtosSipsPaymentGateway extends AbstractAtosSipsSealPaymentGateway
{
    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        string $serverHostName
    ) {
        parent::__construct($templating, $router);

        $this->serverHostName = $serverHostName;
    }

    protected function getServerUrl(): string
    {
        return sprintf('https://%s/rs-services/v2/paymentInit', $this->serverHostName);
    }

    private function buildOptions(
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
            'interfaceVersion' => $paymentGatewayConfiguration->get('interface_version'),
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'normalReturnUrl' => $callbackRoute,
            'orderChannel' => $paymentGatewayConfiguration->get('order_channel'),
            'transactionReference' => $transaction->getId(),
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
        ];
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

    private function initializeGateway(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);
        $options['seal'] = $this->buildSeal($options, $paymentGatewayConfiguration->get('secret'));

        $paymentRequestData = json_encode($options);

        // VOIR GUZZLE
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getServerUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentRequestData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept:application/json'));
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!$result) {
            throw new InvalidAtosSipsInitializationException('No result found');
        }

        if (200 !== $info['http_code']) {
            throw new InvalidAtosSipsInitializationException(sprintf('Got error code : %s', $info['http_code']));
        }

        curl_close($ch);

        if (0 == strlen($result)) {
            throw new InvalidAtosSipsInitializationException('Empty data response');
        }

        $response = json_decode($result);

        if ('00' != $response->redirectionStatusCode) {
            throw new UnexpectedAtosSipsResponseCodeException($response->redirectionStatusCode);
        }

        return $response;
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $initializationData = $this->initializeGateway($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/sogenactif_json_atos_sips.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        if (!$request->request->has('Data')) {
            throw new \InvalidArgumentException("The request do not contains 'Data'");
        }

        $datas = explode('|', $request->get('Data'));

        $formattedData = [];

        foreach ($datas as $data) {
            $param = explode('=', $data);

            if ('transactionReference' === $param[0]) {
                return $param[1];
            }
        }

        return null;
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool {
        if (!$request->request->has('Data')) {
            throw new \InvalidArgumentException("The request do not contains 'Data'");
        }

        $seal = hash('sha256', $request->request->get('Data').$paymentGatewayConfiguration->get('secret'));

        if ($request->request->get('Seal') != $seal) {
            throw new \Exception('Seal check failed');
        }

        $returnParams = [];

        foreach (explode('|', $request->request->get('Data')) as $data) {
            $param = explode('=', $data);
            $returnParams[$param[0]] = $param[1];
        }

        if ('00' !== $returnParams['responseCode']) {
            throw new UnexpectedAtosSipsResponseCodeException($returnParams['responseCode']);
        }

        if ('SUCCESS' !== $returnParams['holderAuthentStatus'] && '3D_SUCCESS' !== $returnParams['holderAuthentStatus']) {
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
            'order_channel',
            'interface_version',
        ];
    }
}
