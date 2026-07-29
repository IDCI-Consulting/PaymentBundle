<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentGatewayController extends AbstractController
{
    private PaymentManager $paymentManager;
    private LoggerInterface $paymentLogger;

    public function __construct(PaymentManager $paymentManager, LoggerInterface $paymentLogger)
    {
        $this->paymentManager = $paymentManager;
        $this->paymentLogger = $paymentLogger;
    }

    public function notifyTransaction(Request $request, EventDispatcherInterface $dispatcher, string $configuration_alias)
    {
        $data = $request->isMethod(Request::METHOD_POST) ? $request->request->all() : $request->query->all();

        $this->paymentLogger->info(
            sprintf(
                '[IDCIPaymentGateway - configuration alias %s] data: %s | ip: %s | content: %s',
                $configuration_alias,
                json_encode($data),
                json_encode($request->getClientIps()),
                $request->getContent()
            )
        );

        //$paymentGateway = $this->paymentGatewayRegistry->get($configuration_alias)

        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($configuration_alias)
        ;

        $paymentContext->handleGatewayCallback($request);
        $transaction = $paymentContext->getTransaction();

        $event = [
            PaymentStatus::STATUS_CREATED => TransactionEvent::CREATED,
            PaymentStatus::STATUS_PENDING => TransactionEvent::PENDING,
            PaymentStatus::STATUS_APPROVED => TransactionEvent::APPROVED,
            PaymentStatus::STATUS_CANCELED => TransactionEvent::CANCELED,
            PaymentStatus::STATUS_FAILED => TransactionEvent::FAILED,
            PaymentStatus::STATUS_UNVERIFIED => TransactionEvent::UNVERIFIED,
        ];

        $dispatcher->dispatch(new TransactionEvent($transaction), $event[$transaction->getStatus()]);

        return new JsonResponse($transaction->toArray());
    }
}
