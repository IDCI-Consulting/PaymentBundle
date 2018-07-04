<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/payment-gateway")
 */
class PaymentGatewayController extends Controller
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
     * @Route("/{paymentGatewayConfigurationAlias}/callback")
     * @Method({"GET", "POST"})
     */
    public function callbackAction(Request $request, EventDispatcher $dispatcher, $paymentGatewayConfigurationAlias)
    {
        $logger = $this->container->get('monolog.logger.payment');

        $logger->info('Gateway configuration alias : '.$paymentGatewayConfigurationAlias);
        $logger->info('GET data: '.json_encode($request->query->all(), JSON_PRETTY_PRINT));
        $logger->info('POST data: '.json_encode($request->request->all(), JSON_PRETTY_PRINT));
        $logger->info('IP SOURCE : '.$request->getClientIp());

        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($paymentGatewayConfigurationAlias)
        ;

        $transaction = $paymentContext->handleGatewayCallback($request);

        $event = [
            PaymentStatus::STATUS_APPROVED => TransactionEvent::APPROVED,
            PaymentStatus::STATUS_CANCELED => TransactionEvent::CANCELED,
            PaymentStatus::STATUS_FAILED => TransactionEvent::FAILED,
        ];

        $this->dispatcher->dispatch($event[$transaction->getStatus()], new TransactionEvent($this->transaction));

        return new JsonResponse($transaction->toArray());
    }
}
