<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\AtosSipsPostPaymentGateway;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AtosSipsStatusCode;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;

class AtosSipsPostPaymentGatewayTest extends PaymentGatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new AtosSipsPostPaymentGateway(
            $this->twig,
            'dummy_server_host_name'
        );
    }

    public function testInitialize()
    {
        $this->paymentGatewayConfiguration
            ->set('client_id', 'dummy_client_id')
            ->set('client_site', 'dummy_client_site')
            ->set('client_rang', 'dummy_client_rang')
            ->set('callback_url', 'dummy_callback_url')
            ->set('return_url', 'dummy_callback_url')
            ->set('environment', 'dummy_environment')
            ->set('capture_day', 'dummy_capture_day')
            ->set('capture_mode', 'dummy_capture_mode')
            ->set('merchant_id', 'dummy_merchant_id')
            ->set('version', 'dummy_version')
            ->set('interface_version', 'dummy_interface_version')
            ->set('secret', 'dummy_secret')
        ;
        $data = $this->gateway->initialize($this->paymentGatewayConfiguration, $this->transaction);

        $this->assertEquals('https://dummy_server_host_name/paymentInit', $data['url']);
        $this->assertEquals($this->paymentGatewayConfiguration->get('interface_version'), $data['interfaceVersion']);
    }

    /**
     * @expectedException \UnexpectedValueException
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
