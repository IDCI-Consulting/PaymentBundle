<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Controller\PaymentGatewayController;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentGatewayControllerTest extends TestCase
{
    public function setUp()
    {
        $this->om = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->transaction = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->transaction
            ->method('getStatus')
            ->willReturn(PaymentStatus::STATUS_APPROVED)
        ;

        $paymentContext = $this->getMockBuilder(PaymentContextInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $paymentContext
            ->method('handleGatewayCallback')
            ->willReturn($this->transaction)
        ;

        $this->paymentManager = $this->getMockBuilder(PaymentManager::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->paymentManager
            ->method('createPaymentContextByAlias')
            ->willReturn($paymentContext)
        ;

        $this->dispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->paymentGatewayController = new PaymentGatewayController(
            $this->om,
            $this->paymentManager
        );

        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $service = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $container
            ->method('get')
            ->with($this->equalTo('monolog.logger.payment'))
            ->willReturn($service)
        ;

        $this->paymentGatewayController
            ->setContainer($container)
        ;
    }

    public function testCallbackAction()
    {
        $request = Request::create('dummy_uri', Request::METHOD_POST, [
            'transaction_uuid' => 'dummy_transaction_uuid'
        ]);

        $transaction = $this->paymentGatewayController->callbackAction($request, $this->dispatcher, 'dummy_configuration_alias');
        $this->assertEquals(new JsonResponse($this->transaction->toArray()), $transaction);
    }
}
