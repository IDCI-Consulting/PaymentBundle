<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/stripe-payment-gateway")
 */
class StripePaymentGatewayController extends Controller
{
    /**
     * @var PaymentManager
     */
    private $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * @Route("/proxy/{configuration_alias}")
     * @Method({"POST"})
     */
    public function proxyAction(Request $request, $configuration_alias)
    {
        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($configuration_alias)
        ;

        $paymentGatewayConfiguration = $paymentContext->getPaymentGatewayConfiguration();

        $data = [
            'transactionId' => $request->get('transactionId'),
            'amount' => $request->get('amount'),
            'currencyCode' => $request->get('currencyCode'),
        ];

        try {
            Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));

            $charge = Stripe\Charge::create([
                'amount' => $request->get('amount'),
                'currency' => $request->get('currencyCode'),
                'source' => $request->get('stripeToken'),
            ]);

            $data['raw'] = $charge->__toArray();
        } catch (Stripe\Error\Base $e) {
            $data['error'] = $e->getJsonBody()['error'];
        }

        try {
            $client = new Client();

            $response = $client->post($request->get('callbackUrl'), [
                'form_params' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->redirect($request->get('cancelUrl'));
        }

        if (isset($data['error'])) {
            return $this->redirect($request->get('cancelUrl'));
        }

        return $this->redirect($request->get('returnUrl'));
    }
}
