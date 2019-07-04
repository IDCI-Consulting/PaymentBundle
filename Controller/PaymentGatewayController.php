<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/payment-gateway")
 */
class PaymentGatewayController extends AbstractController
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
     * @Route("/{configuration_alias}/callback", name="idci_payment_payment_gateway_callback", methods={"GET", "POST"})
     */
    public function callbackAction(Request $request, EventDispatcherInterface $dispatcher, $configuration_alias)
    {
        $logger = $this->container->get('monolog.logger.payment');

        $data = $request->isMethod(Request::METHOD_POST) ? $request->request->all() : $request->query->all();

        $logger->info(
            sprintf(
                '[gateway configuration alias: %s, data: %s, ip: %s]',
                $configuration_alias,
                json_encode($data),
                json_encode($request->getClientIps())
            )
        );

        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($configuration_alias)
        ;

        $transaction = $paymentContext->handleGatewayCallback($request);

        $event = [
            PaymentStatus::STATUS_APPROVED => TransactionEvent::APPROVED,
            PaymentStatus::STATUS_CANCELED => TransactionEvent::CANCELED,
            PaymentStatus::STATUS_FAILED => TransactionEvent::FAILED,
        ];

        $dispatcher->dispatch($event[$transaction->getStatus()], new TransactionEvent($transaction));

        return new JsonResponse($transaction->toArray());
    }
}
