<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/payment")
 */
class BackendPaymentController extends Controller
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
     * @Route("/{paymentGatewayConfigurationAlias}/return")
     * @Method({"GET", "POST"})
     */
    public function return(Request $request, $paymentGatewayConfigurationAlias)
    {
        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($paymentGatewayConfigurationAlias)
        ;

        $paymentContext->loadPayment($request);

        try {
            $isValidated = $paymentContext->executePayment($request);
        } catch (\Exception $e) {
            dump($e);
        }

        return 0;
    }
}
