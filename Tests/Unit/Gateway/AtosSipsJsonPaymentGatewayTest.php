<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use Symfony\Component\HttpFoundation\Request;
use IDCI\Bundle\PaymentBundle\Gateway\AtosSipsJsonPaymentGateway;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AtosSipsStatusCode;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;

class AtosSipsJsonPaymentGatewayTest extends PaymentGatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new AtosSipsJsonPaymentGateway(
            $this->twig,
            'dummy_server_host_name'
        );
    }

    /**
     * @expectedException \IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentCallbackMethodException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dummy_uri', Request::METHOD_GET);

        $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
    }

    public function testRequestNoData()
    {
        $request = Request::create('dummy_uri', Request::METHOD_POST);

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals('The request do not contains "Data"', $gatewayResponse->getMessage());
    }

    public function testRequestSealCheckFail()
    {
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'Data' => 'dummy_data',
            ]
        );

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals('Seal check failed', $gatewayResponse->getMessage());
    }

    public function testRequestResponseCodeError()
    {
        $availableStatusCodes = array_keys(AtosSipsStatusCode::STATUS);
        foreach ($availableStatusCodes as $testedStatusCode) {
            $data = sprintf('dummy_data=data|responseCode=%s|transactionReference=dummy_transaction_reference|amount=20|currencyCode=978', $testedStatusCode);
            $request = Request::create(
                'dummy_uri',
                Request::METHOD_POST,
                [
                    'Data' => $data,
                ]
            );
            $request->request->set('Seal', hash('sha256', $data));

            $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
            $this->assertEquals(AtosSipsStatusCode::getStatusMessage($testedStatusCode), $gatewayResponse->getMessage());
        }
    }

    public function testCancelRequestResponseCode()
    {
        $data = 'dummy_data=data|responseCode=17|transactionReference=dummy_transaction_reference|amount=20|currencyCode=978';
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'Data' => $data,
            ]
        );
        $request->request->set('Seal', hash('sha256', $data));

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals(PaymentStatus::STATUS_CANCELED, $gatewayResponse->getStatus());
    }

    public function testUnauthorizedTransactionRequestResponse()
    {
        $data = 'dummy_data=data|responseCode=00|transactionReference=dummy_transaction_reference|amount=20|currencyCode=978|holderAuthentStatus=wrong_holder_authent_status';
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'Data' => $data,
            ]
        );
        $request->request->set('Seal', hash('sha256', $data));

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals('Transaction unauthorized', $gatewayResponse->getMessage());
    }

    public function testSuccessRequestResponse()
    {
        $data = 'dummy_data=data|responseCode=00|transactionReference=dummy_transaction_reference|amount=20|currencyCode=978|holderAuthentStatus=SUCCESS';
        $request = Request::create(
            'dummy_uri',
            Request::METHOD_POST,
            [
                'Data' => $data,
            ]
        );
        $request->request->set('Seal', hash('sha256', $data));

        $gatewayResponse = $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
        $this->assertEquals(PaymentStatus::STATUS_APPROVED, $gatewayResponse->getStatus());
    }
}
