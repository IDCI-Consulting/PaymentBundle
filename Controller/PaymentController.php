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
class PaymentController extends Controller
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
        $paymentContext = $this->paymentManager->createPaymentContextByAlias('stripe_test'); // raw alias

        $payment = $paymentContext->createPayment([
            'item_id' => 5,
            'amount' => 500,
            'currency_code' => 'EUR',
        ]);

        // passing payment id in session for testing
        $request->getSession()->set('payment_id', $payment->getId());

        return $this->render('@IDCIPaymentBundle/Resources/views/payment.html.twig', [
            'view' => $paymentContext->buildHTMLView(),
        ]);
    }

    /**
     * @Route("/process")
     * @Method({"GET", "POST"})
     */
    public function process(Request $request)
    {
        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByPaymentUuid($request->getSession()->get('payment_id'))
        ;

        try {
            $isValidated = $paymentContext->executePayment($request);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $paymentContext->getPayment()->setStatus(
            (isset($isValidated) && $isValidated) ? Payment::STATUS_VALIDATED : Payment::STATUS_CANCELED
        );

        $this->om->flush();

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
