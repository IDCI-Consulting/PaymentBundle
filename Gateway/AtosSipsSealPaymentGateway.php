<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Exception\AtosSipsSealFormException;
use IDCI\Bundle\PaymentBundle\Exception\AtosSipsSealInvalidPaymentException;
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
    private function buildClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration): SipsClient
    {
        return new SipsClient(
            new SipsEnvironment('SIMU'),
            $paymentGatewayConfiguration->get('merchant_id'),
            $paymentGatewayConfiguration->get('secret'),
            $paymentGatewayConfiguration->get('version')
        );
    }

    private function buildResponse(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): InitializationResponse
    {
        $sipsClient = $this->buildClient($paymentGatewayConfiguration);

        $paypageRequest = new PaypageRequest();
        $paypageRequest->setAmount($payment->getAmount());
        $paypageRequest->setCurrencyCode($payment->getCurrencyCode());
        $paypageRequest->setOrderId((new ShortUuid())->encode(Uuid::fromString($payment->getId())));
        $paypageRequest->setNormalReturnUrl($this->router->generate(
            'idci_payment_backendpayment_return',
            ['paymentGatewayConfigurationAlias' => $paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
        $paypageRequest->setAutomaticResponseUrl($this->router->generate(
            'idci_payment_backendpayment_return',
            ['paymentGatewayConfigurationAlias' => $paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
        $paypageRequest->setCaptureDay($paymentGatewayConfiguration->get('capture_day'));
        $paypageRequest->setCaptureMode($paymentGatewayConfiguration->get('capture_mode'));
        $paypageRequest->setOrderChannel('INTERNET');

        return $sipsClient->initialize($paypageRequest);
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string
    {
        $sipsResponse = $this->buildResponse($paymentGatewayConfiguration, $payment);

        if ('00' !== $sipsResponse->getRedirectionStatusCode()) {
            throw new AtosSipsSealFormException($sipsResponse);
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

    public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool {
        $sipsClient = $this->buildClient($paymentGatewayConfiguration);

        $sipsResponse = $sipsClient->finalizeTransaction();

        if ('3D_SUCCESS' !== $sipsResponse->getHolderAuthentStatus()) {
            throw new AtosSipsSealInvalidPaymentException($sipsResponse->getHolderAuthentStatus());
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
            'bypass_receipt_page',
        ];
    }
}
