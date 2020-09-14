<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

class MoneticoPaymentGateway extends AbstractPaymentGateway
{
    /**
     * Get Monetico server url.
     *
     * @method getServerUrl
     *
     * @return string
     */
    private function getServerUrl(): string
    {
        return 'https://p.monetico-services.com/test/paiement.cgi'; //raw
    }

    /**
     * Build payment gateway options.
     *
     * @method buildOptions
     *
     * @param PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
     * @param Transaction                          $transaction
     *
     * @return array
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'TPE' => $paymentGatewayConfiguration->get('TPE'),
            'date' => (new \DateTime())->format('d/m/Y:H:i:s'),
            'montant' => sprintf('%s%s', $transaction->getAmount() / 100, $transaction->getCurrencyCode()),
            'reference' => $transaction->getId(),
            'text-libre' => 'test',
            'version' => $paymentGatewayConfiguration->get('version'),
            'lgue' => 'FR',
            'societe' => $paymentGatewayConfiguration->get('societe'),
            'mail' => 'john.doe@example.com',
            'url_retour' => $paymentGatewayConfiguration->get('callback_url'),
            'url_retour_ok' => $paymentGatewayConfiguration->get('return_url'),
            'url_retour_err' => $paymentGatewayConfiguration->get('return_url'),
        ];
    }

    /**
     * Build payment gateway HMAC signature.
     *
     * @method buildMAC
     *
     * @param array  $options   [description]
     * @param string $secretKey [description]
     *
     * @return [type] [description]
     */
    private function buildMAC(array $options, string $secretKey)
    {
        $hexStrKey = substr($secretKey, 0, 38);
        $hexFinal = sprintf('%s00', substr($secretKey, 38, 2));

        $char = ord($hexFinal);

        if ($char > 70 && $char < 97) {
            $hexStrKey .= sprintf('%s%s', chr($char - 23), substr($hexFinal, 1, 1));
        } elseif ('M' == substr($hexFinal, 1, 1)) {
            $hexStrKey .= sprintf('%s0', substr($hexFinal, 0, 1));
        } else {
            $hexStrKey .= substr($hexFinal, 0, 2);
        }

        $usableKey = pack('H*', $hexStrKey);

        unset($options['url_retour']);
        unset($options['url_retour_ok']);
        unset($options['url_retour_err']);

        $sData = implode('*', array_map(function ($value) {
            return $value;
        }, $options));

        return strtolower(hash_hmac('sha1', $sData, $usableKey));
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);
        $options['MAC'] = $this->buildMAC($options, $paymentGatewayConfiguration->get('secret'));

        return [
            'url' => $this->getServerUrl(),
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

        return $this->templating->render('@IDCIPayment/Gateway/monetico.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'version',
                'secret',
                'TPE',
                'societe',
            ]
        );
    }
}
