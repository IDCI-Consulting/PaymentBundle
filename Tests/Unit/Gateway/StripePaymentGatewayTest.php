<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use IDCI\Bundle\PaymentBundle\Gateway\StripePaymentGateway;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;

class StripePaymentGatewayTest extends PaymentGatewayTestCase
{
    /**
     * UrlGeneratorInterface
     */
    private $router;

    public function setUp()
    {
        parent::setUp();

        $this->router = $this->getMockBuilder(UrlGeneratorInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->gateway = new StripePaymentGateway($this->twig, $this->router);
    }

    public function testInitialize(){
        $this->paymentGatewayConfiguration
            ->set('callback_url', 'dummy_callback_url')
            ->set('return_url', 'dummy_return_url')
            ->set('public_key', 'dummy_public_key')
        ;

        $data = $this->gateway->initialize($this->paymentGatewayConfiguration, $this->transaction);
        $proxyUrl = $this->router->generate(
            'idci_payment_stripepaymentgateway_proxy',
            ['configuration_alias' => $this->paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->assertEquals($this->paymentGatewayConfiguration->get('callback_url'), $data['callbackUrl']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('return_url'), $data['cancelUrl']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('public_key'), $data['publicKey']);
        $this->assertEquals($proxyUrl, $data['proxyUrl']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('return_url'), $data['returnUrl']);
        $this->assertEquals($this->transaction, $data['transaction']);
    }

    /**
     * @expectedException IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentCallbackMethodException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dummy_uri', Request::METHOD_GET);

        $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
    }

    public function testRequestError()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'transactionId' => 'dummy_transaction_id',
                'amount' => 20,
                'currencyCode'=> 'EUR',
                'error' => [
                    'message' => 'dummy_error_message'
                ]
            ]
        );

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals($request->get('error')['message'], $gatewayResponse->getMessage());
    }

    public function testRequestApproved()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'transactionId' => 'dummy_transaction_id',
                'amount' => 20,
                'currencyCode' => 'EUR',
                'raw' => 'dummy_raw'
            ]
        );

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals(PaymentStatus::STATUS_APPROVED, $gatewayResponse->getStatus());
    }
}
