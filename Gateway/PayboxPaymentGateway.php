<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Phaybox\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayboxPaymentGateway extends AbstractPaymentGateway
{
    private function buildClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): Client
    {
        return new Client(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret'),
            $paymentGatewayConfiguration->get('client_rang'),
            $paymentGatewayConfiguration->get('client_site'),
            []
        );
    }

    private function buildOptions(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): array
    {
        $returnUrl = $this->router->generate('idci_payment_payment_process', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this
            ->buildClient($paymentGatewayConfiguration, $transaction)
            ->getTransaction([
                'PBX_TOTAL' => $transaction->getAmount(),
                'PBX_DEVISE' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
                'PBX_CMD' => $transaction->getId(),
                'PBX_PORTEUR' => 'me@mail.com',
                'PBX_EFFECTUE' => $returnUrl,
                'PBX_REFUSE' => $returnUrl,
                'PBX_ANNULE' => $returnUrl,
            ])
            ->getFormattedParams()
        ;
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paybox.html.twig', [
            'options' => $options,
        ]);
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
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
