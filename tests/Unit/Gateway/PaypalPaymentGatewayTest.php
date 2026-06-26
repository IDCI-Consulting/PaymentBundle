<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\PaypalPaymentGateway;
use Symfony\Component\HttpFoundation\Request;

class PaypalPaymentGatewayTest extends PaymentGatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new PaypalPaymentGateway($this->twig);
    }

    public function testInitialize()
    {
        $this->paymentGatewayConfiguration
            ->set('client_id', 'dummy_client_id')
            ->set('callback_url', 'dummy_callback_url')
            ->set('environment', 'dummy_environment')
        ;

        $data = $this->gateway->initialize($this->paymentGatewayConfiguration, $this->transaction);

        $this->assertEquals($this->paymentGatewayConfiguration->get('client_id'), $data['clientId']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('callback_url'), $data['url']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('environment'), $data['environment']);
        $this->assertEquals($this->transaction, $data['transaction']);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dumy_uri', Request::METHOD_GET);

        $this->gateway->getCallbackResponse($request, $this->paymentGatewayConfiguration);
    }
}
