<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use IDCI\Bundle\PaymentBundle\Gateway\PayPlugPaymentGateway;

class PayPlugPaymentGatewayTest extends PaymentGatewayTestCase
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

        $this->gateway = new PayPlugPaymentGateway($this->twig, $this->router);
    }

    /**
     * @expectedException IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentCallbackMethodException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dumy_uri', Request::METHOD_GET);

        $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
    }

    public function getResponse()
    {
        $request = Request::create('dumy_uri', Request::METHOD_POST);

        $data = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        var_dump($data);die;
    }
}
