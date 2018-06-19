<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Test;

use IDCI\Bundle\PaymentBundle\Form\PaymentGatewayConfigurationChoiceType;
use IDCI\Bundle\PaymentBundle\Form\TransactionFormType;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/payment-gateway")
 */
class PaymentGatewayFrontTestController extends Controller
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
     * @Route("/select")
     * @Method({"GET"})
     */
    public function selectAction(Request $request)
    {
        $form = $this->createForm(PaymentGatewayConfigurationChoiceType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                return $this->redirectToRoute('idci_payment_test_paymentgatewayfronttest_configure', [
                    'gateway_configuration_alias' => $data['gateway_configuration_alias'],
                ]);
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPaymentBundle/Resources/views/select.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{gateway_configuration_alias}/transaction/configure")
     * @Method({"GET"})
     */
    public function configureAction(Request $request, string $gateway_configuration_alias)
    {
        $form = $this->createForm(TransactionFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                return $this->redirectToRoute('idci_payment_test_paymentgatewayfronttest_initialize', [
                    'gateway_configuration_alias' => $gateway_configuration_alias,
                    'configuration' => $form->getData(),
                ]);
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPaymentBundle/Resources/views/configuration.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{gateway_configuration_alias}/transaction/initialize")
     * @Method({"GET"})
     */
    public function initializeAction(Request $request, $gateway_configuration_alias)
    {
        $paymentContext = $this->paymentManager->createPaymentContextByAlias($gateway_configuration_alias);

        $configuration = $request->get('configuration');
        $configuration['amount'] = (int) $configuration['amount'];

        $paymentContext->createTransaction($configuration);

        return $this->render('@IDCIPaymentBundle/Resources/views/create.html.twig', [
            'view' => $paymentContext->buildHTMLView(),
        ]);
    }
}
