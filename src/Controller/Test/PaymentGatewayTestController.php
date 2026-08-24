<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Test;

use IDCI\Bundle\PaymentBundle\Form\TransactionFormType;
use IDCI\Bundle\PaymentBundle\Form\Type\PaymentGatewayConfigurationChoiceType;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

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
            'csrf_protection' => false,
            'method' => 'GET',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $transactionModel = $form->getData();
                $transactionModel->setPaymentGatewayConfigurationAlias($configuration_alias);

                return $this->redirectToRoute('idci_payment_test_process_transaction', array_merge(
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

    public function processTransaction(Request $request, $configuration_alias)
    {
        $paymentContext = $this->paymentManager->createPaymentContext($configuration_alias, $request);

        if ($paymentContext->isReturnClientRequest()) {
            $paymentContext->retrieveTransaction();
        } else {
            $paymentContext->initializeTransaction($request->query->all(), []);
        }

        $processedTransactionResult = $paymentContext->processTransaction([]);

        if (ProcessedTransactionResult::TYPE_HTML === $processedTransactionResult->getType()) {
            return $this->render('@IDCIPayment/Test/transaction.html.twig', [
                'view' => $processedTransactionResult->getContent(),
                'transaction' => $paymentContext->getTransaction(),
            ]);
        }

        if (ProcessedTransactionResult::TYPE_REDIRECTION === $processedTransactionResult->getType()) {
            return $this->redirect($processedTransactionResult->getContent());
        }

        throw new \RuntimeException('Wrong processed transaction result type');
    }
}
