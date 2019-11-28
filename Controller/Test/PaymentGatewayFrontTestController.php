<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Test;

use IDCI\Bundle\PaymentBundle\Form\TransactionFormType;
use IDCI\Bundle\PaymentBundle\Form\Type\PaymentGatewayConfigurationChoiceType;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/payment-gateway")
 */
class PaymentGatewayFrontTestController extends AbstractController
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
     * @Route("/select", methods={"GET"})
     */
    public function selectAction(Request $request)
    {
        $form = $this
            ->createFormBuilder(null, [
                'csrf_protection' => false,
                'method' => 'GET',
            ])
            ->add('payment_gateway_configuration_alias', PaymentGatewayConfigurationChoiceType::class)
            ->add('submit', SubmitType::class)
            ->getForm()
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                return $this->redirectToRoute('idci_payment_test_paymentgatewayfronttest_configure', [
                    'configuration_alias' => $data['payment_gateway_configuration_alias'],
                ]);
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPaymentBundle/Resources/views/Test/select.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{configuration_alias}/transaction/configure", methods={"GET"})
     */
    public function configureAction(Request $request, string $configuration_alias)
    {
        $form = $this->createForm(TransactionFormType::class, null, [
            'csrf_protection' => false,
            'method' => 'GET',
            'payment_gateway_configuration_alias' => $configuration_alias,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $transactionData = $form->getData();

                return $this->redirectToRoute('idci_payment_test_paymentgatewayfronttest_initialize', [
                    'configuration_alias' => $configuration_alias,
                    'item_id' => $transactionData['item_id'],
                    'amount' => $transactionData['amount'],
                    'currency_code' => $transactionData['currency_code'],
                    'customer_id' => $transactionData['customer_id'],
                    'customer_email' => $transactionData['customer_email'],
                    'description' => $transactionData['description'],
                ]);
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPaymentBundle/Resources/views/Test/configuration.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{configuration_alias}/transaction/initialize", methods={"GET"})
     */
    public function initializeAction(Request $request, $configuration_alias)
    {
        $paymentContext = $this->paymentManager->createPaymentContextByAlias($configuration_alias);

        $transaction = $paymentContext->createTransaction($request->query->all());

        return $this->render('@IDCIPaymentBundle/Resources/views/Test/create.html.twig', [
            'view' => $paymentContext->buildHTMLView(),
            'transaction' => $transaction,
        ]);
    }

    /**
     * @Route("/{configuration_alias}/transaction/done", methods={"GET", "POST"})
     */
    public function doneAction(Request $request, $configuration_alias)
    {
        return $this->render('@IDCIPaymentBundle/Resources/views/Test/done.html.twig');
    }

    public function cancelAction(Request $request, $configuration_alias)
    {
        return $this->render('@IDCIPaymentBundle/Resources/views/Test/cancel.html.twig');
    }
}
