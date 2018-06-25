<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/payment-gateway")
 */
class PaymentGatewayController extends Controller
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var PaymentManager
     */
    private $paymentManager;

    public function __construct(
        ObjectManager $om,
        PaymentManager $paymentManager
    ) {
        $this->om = $om;
        $this->paymentManager = $paymentManager;
    }

    /**
     * @Route("/{paymentGatewayConfigurationAlias}/callback")
     * @Method({"GET", "POST"})
     */
    public function callbackAction(Request $request, $paymentGatewayConfigurationAlias)
    {
        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($paymentGatewayConfigurationAlias)
        ;

        $transaction = $paymentContext->handleGatewayCallback($request);

        return new JsonResponse($transaction->toArray());
    }
}
