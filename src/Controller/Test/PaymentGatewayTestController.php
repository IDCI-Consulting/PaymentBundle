<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Test;

use IDCI\Bundle\PaymentBundle\Form\TransactionFormType;
use IDCI\Bundle\PaymentBundle\Form\Type\PaymentGatewayConfigurationChoiceType;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentGatewayTestController extends AbstractController
{
    private PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function selectPaymentGatewayConfiguration(Request $request)
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

                return $this->redirectToRoute('idci_payment_test_configure_transaction', [
                    'configuration_alias' => $data['payment_gateway_configuration_alias'],
                ]);
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPayment/Test/select.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function configureTransaction(Request $request, string $configuration_alias)
    {
        $form = $this->createForm(TransactionFormType::class, null, [
            'payment_gateway_configuration_alias' => $configuration_alias,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $transactionModel = $form->getData();

                return $this->redirectToRoute('idci_payment_test_initialize_transaction', array_merge(
                    [
                        'configuration_alias' => $configuration_alias,
                    ],
                    $transactionModel->toArray()
                ));
            }

            $this->addFlash('error', 'flash.invalid_form');
        }

        return $this->render('@IDCIPayment/Test/configure.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function initializeTransaction(Request $request, $configuration_alias)
    {
        $paymentContext = $this->paymentManager->createPaymentContext($configuration_alias);
        $paymentContext->createTransaction($request->query->all());

        return $this->render('@IDCIPayment/Test/create.html.twig', [
            'view' => $paymentContext->buildInitialHTMLView([
                'client_return_url' => $this->generateUrl(
                    'idci_payment_test_finalize_transaction',
                    [
                        'configuration_alias' => $configuration_alias,
                        'reference' => $paymentContext->getTransaction()->getReference(),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'locale' => 'fr',
            ]),
            'transaction' => $paymentContext->getTransaction(),
        ]);
    }

    public function finalizeTransaction(Request $request, $configuration_alias)
    {
        $paymentContext = $this->paymentManager->createPaymentContext($configuration_alias);
        $paymentContext->retrieveTransactionByReference($request->query->get('reference'));

        dd('finalize', $paymentContext, $request);
    }

    public function done(Request $request, $configuration_alias)
    {
        return $this->render('@IDCIPayment/Test/done.html.twig');
    }

    public function cancel(Request $request, $configuration_alias)
    {
        return $this->render('@IDCIPayment/Test/cancel.html.twig');
    }
}
