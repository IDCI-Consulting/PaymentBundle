<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MoneticoPaymentGateway extends AbstractAtosSipsSealPaymentGateway
{
    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        TransactionManagerInterface $transactionManager
    ) {
        parent::__construct($templating, $router, $transactionManager);
    }

    protected function getServerUrl(): string
    {
        return 'https://p.monetico-services.com/test/paiement.cgi'; //raw
    }

    protected function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackRoute = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());

        return [
            'TPE' => $paymentGatewayConfiguration->get('TPE'),
            'date' => (new \DateTime())->format('d/m/Y:H:i:s'),
            'montant' => sprintf('%s%s', $transaction->getAmount() / 100, $transaction->getCurrencyCode()),
            'reference' => substr($transaction->getId(), 0, 12), // PROBLEM : <= 12
            'text-libre' => 'test',
            'version' => $paymentGatewayConfiguration->get('version'),
            'lgue' => 'FR',
            'societe' => $paymentGatewayConfiguration->get('societe'),
            'mail' => 'john.doe@example.com',
            'url_retour' => $callbackRoute,
            'url_retour_ok' => $callbackRoute,
            'url_retour_err' => $callbackRoute,
        ];
    }

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

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/monetico.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        return null;
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return null;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'version', // 3.0
            'secret', // 12345678901234567890123456789012345678P0
            'TPE', // 0000001
            'societe', // 0123456789azertyuiop
        ];
    }
}
