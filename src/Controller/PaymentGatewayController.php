<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentGatewayController extends AbstractController
{
    private LoggerInterface $paymentLogger;
    private PaymentManager $paymentManager;

    public function __construct(LoggerInterface $paymentLogger, PaymentManager $paymentManager)
    {
        $this->paymentLogger = $paymentLogger;
        $this->paymentManager = $paymentManager;
    }

    public function notifyTransaction(Request $request, EventDispatcherInterface $dispatcher, string $configuration_alias)
    {
        $this->paymentLogger->info('[IDCIPaymentBundle] PaymentGatewayController:notifyTransaction', [
            'request' => $request,
        ]);

        $paymentContext = $this->paymentManager->createPaymentContext($configuration_alias, $request);
        $paymentContext->retrieveTransaction();
        $paymentContext->handleNotification();

        return new JsonResponse([
            'transaction' => [
                'id' => $paymentContext->getTransaction()->getId(),
                'status' => $paymentContext->getTransaction()->getStatus(),
            ],
        ]);
    }
}
