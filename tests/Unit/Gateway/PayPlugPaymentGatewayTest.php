<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\PayPlugPaymentGateway;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayPlugPaymentGatewayTest extends PaymentGatewayTestCase
{
    /**
     * UrlGeneratorInterface.
     */
    private $router;

    public function setUp()
    {
        parent::setUp();

        $this->router = $this->getMockBuilder(UrlGeneratorInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->gateway = new PayPlugPaymentGateway($this->twig, $this->router);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dumy_uri', Request::METHOD_GET);

        $this->gateway->getCallbackResponse($request, $this->paymentGatewayConfiguration);
    }

    public function testGetCallbackResponseEmptyPostDataRequest()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST
        );

        $gatewayResponse = $this->gateway->getCallbackResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals('The request do not contains required post data', $gatewayResponse->getMessage());
    }

    public function testUnauthorizedTransactionResponse()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'metadata' => [
                    'transaction_id' => 'dummy_transaction_id',
                ],
                'amount' => 20,
                'currency' => 'EUR',
                'is_paid' => false,
            ]
        );

        $gatewayResponse = $this->gateway->getCallbackResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals('Transaction unauthorized', $gatewayResponse->getMessage());
    }

    public function testGetCallbackResponseApproved()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'metadata' => [
                    'transaction_id' => 'dummy_transaction_id',
                ],
                'amount' => 20,
                'currency' => 'EUR',
                'is_paid' => true,
            ]
        );

        $gatewayResponse = $this->gateway->getCallbackResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals(PaymentStatus::STATUS_APPROVED, $gatewayResponse->getStatus());
    }
}
