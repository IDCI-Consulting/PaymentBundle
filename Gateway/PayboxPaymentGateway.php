<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Phaybox\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayboxPaymentGateway extends AbstractPaymentGateway
{
    private function buildClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): Client
    {
        return new Client(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret'),
            $paymentGatewayConfiguration->get('client_rang'),
            $paymentGatewayConfiguration->get('client_site'),
            []
        );
    }

    private function buildOptions(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): array
    {
        $returnUrl = $this->router->generate('idci_payment_payment_process', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this
            ->buildClient($paymentGatewayConfiguration, $payment)
            ->getTransaction([
                'PBX_TOTAL' => $payment->getAmount(),
                'PBX_DEVISE' => (new ISO4217())->findByAlpha3($payment->getCurrencyCode())->getNumeric(),
                'PBX_CMD' => $payment->getId(),
                'PBX_PORTEUR' => 'me@mail.com',
                'PBX_EFFECTUE' => $returnUrl,
                'PBX_REFUSE' => $returnUrl,
                'PBX_ANNULE' => $returnUrl,
            ])
            ->getFormattedParams()
        ;
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string
    {
        $options = $this->buildOptions($paymentGatewayConfiguration, $payment);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paybox.html.twig', [
            'options' => $options,
        ]);
    }

    public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool {
        dump($request);
        die();

        return null;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
            'client_rang',
            'client_site',
        ];
    }
}
