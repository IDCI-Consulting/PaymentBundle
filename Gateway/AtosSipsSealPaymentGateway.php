<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\InvalidFormException;
use IDCI\Bundle\PaymentBundle\Exception\InvalidTransactionException;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use PascalDeVink\ShortUuid\ShortUuid;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Worldline\Sips\Common\SipsEnvironment;
use Worldline\Sips\Paypage\InitializationResponse;
use Worldline\Sips\Paypage\PaypageRequest;
use Worldline\Sips\SipsClient;

class AtosSipsSealPaymentGateway extends AbstractPaymentGateway
{
    // Environment

    const SIMU_ENVIRONMENT = 'SIMU';

    const TEST_ENVIRONMENT = 'TEST';

    const PROD_ENVIRONMENT = 'PROD';

    // Status

    const STATUS_SUCCESS = 'SUCCESS';

    const STATUS_3D_SUCCESS = '3D_SUCCESS';

    private function buildClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration): SipsClient
    {
        return new SipsClient(
            new SipsEnvironment(self::SIMU_ENVIRONMENT), // raw / put a var in config
            $paymentGatewayConfiguration->get('merchant_id'),
            $paymentGatewayConfiguration->get('secret'),
            $paymentGatewayConfiguration->get('version')
        );
    }

    private function buildResponse(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): InitializationResponse
    {
        $sipsClient = $this->buildClient($paymentGatewayConfiguration);

        $paypageRequest = new PaypageRequest();
        $paypageRequest->setAmount($transaction->getAmount());
        $paypageRequest->setCurrencyCode($transaction->getCurrencyCode());
        $paypageRequest->setOrderId((new ShortUuid())->encode(Uuid::fromString($transaction->getId())));
        $paypageRequest->setNormalReturnUrl($this->router->generate(
            'idci_payment_paymentgateway_callback',
            ['paymentGatewayConfigurationAlias' => $paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
        $paypageRequest->setAutomaticResponseUrl($this->router->generate(
            'idci_payment_paymentgateway_callback',
            ['paymentGatewayConfigurationAlias' => $paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
        $paypageRequest->setCaptureDay($paymentGatewayConfiguration->get('capture_day'));
        $paypageRequest->setCaptureMode($paymentGatewayConfiguration->get('capture_mode'));
        $paypageRequest->setOrderChannel('INTERNET'); // raw ?

        return $sipsClient->initialize($paypageRequest);
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $sipsResponse = $this->buildResponse($paymentGatewayConfiguration, $transaction);

        if ('00' !== $sipsResponse->getRedirectionStatusCode()) {
            throw new InvalidFormException($sipsResponse);
        }

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/atos_sips_seal.html.twig', [
            'sipsResponse' => $sipsResponse,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        $datas = explode('|', $request->get('Data'));

        $formattedData = [];

        foreach ($datas as $data) {
            $param = explode('=', $data);

            if ('orderId' === $param[0]) {
                return (new ShortUuid())->decode($param[1]);
            }
        }

        return null;
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool {
        $sipsClient = $this->buildClient($paymentGatewayConfiguration);

        $sipsResponse = $sipsClient->finalizeTransaction();

        if (
            '00' !== $sipsResponse->getResponseCode() ||
            '00' !== $sipsResponse->getAcquirerResponseCode() ||
            (
                self::STATUS_SUCCESS !== $sipsResponse->getHolderAuthentStatus() &&
                self::STATUS_3D_SUCCESS !== $sipsResponse->getHolderAuthentStatus()
            )
        ) {
            throw new InvalidTransactionException($sipsResponse->getHolderAuthentStatus());
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
