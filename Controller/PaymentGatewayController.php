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
        $logger = $this->container->get('monolog.logger.payment');

        $logger->info('Gateway configuration alias : '.$paymentGatewayConfigurationAlias);
        $logger->info('GET data: '.json_encode($request->query->all(), JSON_PRETTY_PRINT));
        $logger->info('POST data: '.json_encode($request->request->all(), JSON_PRETTY_PRINT));
        $logger->info('IP SOURCE : '.$request->getClientIp());

        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($paymentGatewayConfigurationAlias)
        ;

        $transaction = $paymentContext->handleGatewayCallback($request);

        return new JsonResponse($transaction->toArray());
    }
}
