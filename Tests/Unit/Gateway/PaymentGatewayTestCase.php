<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Unit\Gateway;

use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\TransactionFactory;
use PHPUnit\Framework\TestCase;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

class PaymentGatewayTestCase extends TestCase
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var PaymentGatewayConfiguration
     */
    protected $paymentGatewayConfiguration;

    /**
     * @var PaymentGatewayInterface
     */
    protected $gateway;

    public function setUp()
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../../..', 'IDCIPaymentBundle');

        $this->twig = new TwigEnvironment($loader);

        $this->paymentGatewayConfiguration = (new PaymentGatewayConfiguration())
            ->setAlias('dummy_gateway_alias')
        ;

        $this->transaction = TransactionFactory::getInstance()->create([
            'gateway_configuration_alias' => $this->paymentGatewayConfiguration->getAlias(),
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadata' => [],
        ]);
    }
}
