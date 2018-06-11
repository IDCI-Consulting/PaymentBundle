<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayFactory;
use IDCI\Bundle\PaymentBundle\Payment\PaymentFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/payment")
 */
class PaymentController extends Controller
{
    private $om;

    private $paymentFactory;

    private $paymentGatewayFactory;

    public function __construct(
        ObjectManager $om,
        PaymentFactory $paymentFactory,
        PaymentGatewayFactory $paymentGatewayFactory
    ) {
        $this->om = $om;
        $this->paymentFactory = $paymentFactory;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
    }

    /**
     * @Route("/create")
     * @Method({"GET", "POST"})
     */
    public function createAction(Request $request)
    {
        $gateway = $this->paymentGatewayFactory->buildFromAlias('stripe_test'); // raw alias

        $payment = $gateway->createPayment([
            'item_id' => 5,
            'amount' => 500,
            'currency_code' => 'EUR',
        ]);

        // To move ? >
        $this->om->persist($payment);
        $this->om->flush();
        // < To move ?

        $request->getSession()->set('payment_id', $payment->getId());
        $view = $gateway->buildHTMLView();

        return $this->render('@IDCIPaymentBundle/Resources/views/payment.html.twig', [
            'view' => $view,
        ]);
    }

    /**
     * @Route("/process")
     * @Method({"GET", "POST"})
     */
    public function process(Request $request)
    {
        $gateway = $this->paymentGatewayFactory->buildFromPaymentUuid($request->getSession()->get('payment_id'));

        try {
            $gateway->preProcess($request);
            $gateway->postProcess($request);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($this->generateUrl('idci_payment_payment_done'));
    }

    /**
     * @Route("/done")
     * @Method({"GET", "POST"})
     */
    public function done()
    {
        return $this->render('@IDCIPaymentBundle/Resources/views/done.html.twig');
    }
}
