<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use Symfony\Component\HttpFoundation\Request;
use IDCI\Bundle\PaymentBundle\Gateway\PayboxPaymentGateway;
use Symfony\Component\Filesystem\Filesystem;

class PayboxPaymentGatewayTest extends PaymentGatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new PayboxPaymentGateway(
            $this->twig,
            'dummy_server_host_name',
            sys_get_temp_dir(),
            'dummy_public_key_url'
        );

        $fileSystem = new Filesystem();
        $fileSystem->touch(sys_get_temp_dir().'/dummy_client_site.bin');
    }

    public function tearDown()
    {
        $filePath = sprintf('%s/dummy_client_site.bin', sys_get_temp_dir());

        if (file_exists($filePath)) {
            unlink($filePath);
        }
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
        ;
        $data = $this->gateway->initialize($this->paymentGatewayConfiguration, $this->transaction);

        $this->assertEquals('https://dummy_server_host_name/cgi/MYchoix_pagepaiement.cgi', $data['url']);
    }

    /**
     * @expectedException \IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentCallbackMethodException
     */
    public function testInvalidMethod()
    {
        $request = Request::create('dummy_uri', Request::METHOD_GET);

        $this->gateway->getResponse($request, $this->paymentGatewayConfiguration);
    }
}
