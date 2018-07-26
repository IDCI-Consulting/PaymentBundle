<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Payment;

use PHPUnit\Framework\TestCase;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\ArrayLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Gateway\AbstractPaymentGateway;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContext;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use IDCI\Bundle\PaymentBundle\Payment\TransactionFactory;

class PaymentContextTest extends TestCase
{
    /**
     * @var PaymentContext
     */
    private $paymentContext;

    /**
     * @var PaymentGatewayConfiguration
     */
    private $paymentGatewayConfiguration;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    /**
     * @var AbstractPaymentGateway
     */
    private $paymentGateway;

    public function setUp()
    {
        $this->twig = new TwigEnvironment(new ArrayLoader());

        $this->paymentGateway = $this->getMockBuilder(AbstractPaymentGateway::class)
            ->setConstructorArgs([$this->twig])
            ->setMethods(['initialize', 'getResponse', 'buildHTMLView'])
            ->getMock()
        ;

        $this->transactionManager = $this->getMockBuilder(TransactionManagerInterface::class)
            ->getMock()
        ;

        $this->dispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->paymentGatewayConfiguration = (new PaymentGatewayConfiguration())
            ->setAlias('dummy_gateway_alias')
        ;

        $this->paymentContext = new PaymentContext(
            $this->dispatcher,
            $this->paymentGatewayConfiguration,
            $this->paymentGateway,
            $this->transactionManager
        );
    }

    public function testCreateTransaction()
    {
        $parameters = [
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ];

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(TransactionEvent::CREATED), $this->isInstanceOf(TransactionEvent::class))
        ;

        $transaction = $this->paymentContext->createTransaction($parameters);

        $this->assertEquals($transaction->getItemId(), $parameters['item_id']);
        $this->assertEquals($transaction->getCustomerId(), $parameters['customer_id']);
        $this->assertEquals($transaction->getCustomerEmail(), $parameters['customer_email']);
        $this->assertEquals($transaction->getAmount(), $parameters['amount']);
        $this->assertEquals($transaction->getCurrencyCode(), $parameters['currency_code']);
        $this->assertEquals($transaction->getDescription(), $parameters['description']);
        $this->assertEquals($transaction->getMetadatas(), $parameters['metadatas']);
        $this->assertEquals($transaction->getStatus(), PaymentStatus::STATUS_CREATED);
        $this->assertEquals(
            $transaction->getGatewayConfigurationAlias(),
            $this->paymentGatewayConfiguration->getAlias()
        );
    }

    /**
     * @expectedException \Exception
     */
    public function testTransactionNotCreated()
    {
        // Required parameter "item_id" is not set to throw an exception.
        $parameters = [
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ];

        $this->dispatcher
            ->expects($this->never())
            ->method('dispatch')
        ;

        $transaction = $this->paymentContext->createTransaction($parameters);
    }

    /**
     * @dataProvider getHandleGatewayCallbackDataProvider
     */
    public function testHandleGatewayCallback(
        array $parameters,
        GatewayResponse $gatewayResponse,
        Request $request,
        Transaction $transaction
    ) {
        $gatewayResponse->setTransactionUuid('dummy_uuid');

        $this->paymentGateway
            ->expects($this->once())
            ->method('getResponse')
            ->with($this->equalTo($request), $this->equalTo($this->paymentGatewayConfiguration))
            ->will($this->returnValue($gatewayResponse))
        ;

        $this->transactionManager
            ->expects($this->once())
            ->method('retrieveTransactionByUuid')
            ->with($this->equalTo($gatewayResponse->getTransactionUuid()))
            ->will($this->returnValue($transaction))
        ;

        $this->assertEquals($transaction, $this->paymentContext->handleGatewayCallback($request));
    }

    /**
     * @dataProvider getHandleGatewayCallbackDataProvider
     */
    public function testHandleGatewayCallbackWithDifferentAmount(
        array $parameters,
        GatewayResponse $gatewayResponse,
        Request $request,
        Transaction $transaction
    ) {
        $gatewayResponse
            ->setTransactionUuid('dummy_uuid')
            ->setAmount(50)
        ;

        $this->paymentGateway
            ->expects($this->once())
            ->method('getResponse')
            ->with($this->equalTo($request), $this->equalTo($this->paymentGatewayConfiguration))
            ->will($this->returnValue($gatewayResponse))
        ;

        $this->transactionManager
            ->expects($this->once())
            ->method('retrieveTransactionByUuid')
            ->with($this->equalTo($gatewayResponse->getTransactionUuid()))
            ->will($this->returnValue($transaction))
        ;

        $transaction = $this->paymentContext->handleGatewayCallback($request);

        $this->assertEquals(PaymentStatus::STATUS_FAILED, $transaction->getStatus());
    }

    /**
     * @dataProvider getHandleGatewayCallbackDataProvider
     */
    public function testHandleGatewayCallbackWithDifferentCurrencyCode(
        array $parameters,
        GatewayResponse $gatewayResponse,
        Request $request,
        Transaction $transaction
    ) {
        $gatewayResponse
            ->setTransactionUuid('dummy_uuid')
            ->setCurrencyCode('USD')
        ;

        $this->paymentGateway
            ->expects($this->once())
            ->method('getResponse')
            ->with($this->equalTo($request), $this->equalTo($this->paymentGatewayConfiguration))
            ->will($this->returnValue($gatewayResponse))
        ;

        $this->transactionManager
            ->expects($this->once())
            ->method('retrieveTransactionByUuid')
            ->with($this->equalTo($gatewayResponse->getTransactionUuid()))
            ->will($this->returnValue($transaction))
        ;

        $transaction = $this->paymentContext->handleGatewayCallback($request);

        $this->assertEquals(PaymentStatus::STATUS_FAILED, $transaction->getStatus());
    }

    /**
     * @dataProvider getHandleGatewayCallbackDataProvider
     * @expectedException \IDCI\Bundle\PaymentBundle\Exception\UndefinedTransactionException
     */
    public function testHandleGatewayCallbackWithNoTransactionUuid(
        array $parameters,
        GatewayResponse $gatewayResponse,
        Request $request,
        Transaction $transaction
    ) {
        $this->paymentGateway
            ->expects($this->once())
            ->method('getResponse')
            ->with($this->equalTo($request), $this->equalTo($this->paymentGatewayConfiguration))
            ->will($this->returnValue($gatewayResponse))
        ;

        $this->paymentContext->handleGatewayCallback($request);
    }

    /**
     * @dataProvider getTransactionDataProvider
     */
    public function testBuildHTMLView(Transaction $transaction)
    {
        $this->paymentGateway
            ->expects($this->once())
            ->method('buildHTMLView')
            ->will($this->returnValue('<html></html>'))
        ;

        $this->paymentContext->setTransaction($transaction);

        $this->assertEquals('<html></html>', $this->paymentContext->buildHTMLView());
    }

    /**
     * @dataProvider getTransactionDataProvider
     * @expectedException \IDCI\Bundle\PaymentBundle\Exception\AlreadyDefinedTransactionException
     */
    public function testTransactionAlreadyDefined(Transaction $transaction)
    {
        $this->paymentContext->setTransaction($transaction);
        $this->paymentContext->setTransaction($transaction);
    }

    public function getHandleGatewayCallbackDataProvider()
    {
        $gatewayResponse = (new GatewayResponse())
            ->setAmount(100)
            ->setCurrencyCode('EUR')
            ->setMessage('dummy_message')
            ->setDate(\DateTime::createFromFormat('Y-m-d', '2018-07-05'))
            ->setRaw(['raw'])
            ->setStatus(PaymentStatus::STATUS_CREATED)
        ;

        $parameters = [
            'gateway_configuration_alias' => 'dummy_gateway_alias',
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ];

        return [
            [
                'parameters' => $parameters,
                'gatewayResponse' => $gatewayResponse,
                'request' => Request::create('dumy_uri', Request::METHOD_GET),
                'transaction' => TransactionFactory::getInstance()->create($parameters),
            ],
        ];
    }

    public function getTransactionDataProvider()
    {
        $parameters = [
            'gateway_configuration_alias' => 'dummy_gateway_alias',
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ];

        return [
            [
                'transaction' => TransactionFactory::getInstance()->create($parameters),
            ],
        ];
    }
}
