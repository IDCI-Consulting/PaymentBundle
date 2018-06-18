<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/transaction")
 */
class FrontPaymentController extends Controller
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
     * @Route("/create")
     * @Method({"GET", "POST"})
     */
    public function createAction(Request $request)
    {
        $paymentContext = $this->paymentManager->createPaymentContextByAlias('atos_sips_seal_test'); // raw alias

        $paymentContext->createTransaction([
            'item_id' => 5,
            'amount' => 500,
            'currency_code' => 'EUR',
        ]);

        return $this->render('@IDCIPaymentBundle/Resources/views/create.html.twig', [
            'view' => $paymentContext->buildHTMLView(),
        ]);
    }
}
