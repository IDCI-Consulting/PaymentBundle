<?php

namespace IDCI\Bundle\PaymentBundle\Test\Unit\Payment;

use PHPUnit\Framework\TestCase;
use IDCI\Bundle\PaymentBundle\Payment\TransactionFactory;
use IDCI\Bundle\PaymentBundle\Model\Transaction;

class TransactionFactoryTest extends TestCase
{
    /**
     * @expectedException \Exception
     */
    public function testCreateWithWrongCurrencyCode()
    {
        TransactionFactory::getInstance()->create([
            'gateway_configuration_alias' => 'dummy_gateway_alias',
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 100,
            'currency_code' => 'wrong_currency_code',
            'description' => 'Dummy description',
            'metadatas' => [],
        ]);
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateWithWrongAmount()
    {
        TransactionFactory::getInstance()->create([
            'gateway_configuration_alias' => 'dummy_gateway_alias',
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 'wrong_amount',
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ]);
    }

    public function testCreate()
    {
        $transaction = TransactionFactory::getInstance()->create([
            'gateway_configuration_alias' => 'dummy_gateway_alias',
            'item_id' => 'dummy_item_id',
            'customer_id' => 'dummy_customer_id',
            'customer_email' => 'dummy_customer_email',
            'amount' => 10,
            'currency_code' => 'EUR',
            'description' => 'Dummy description',
            'metadatas' => [],
        ]);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('dummy_gateway_alias', $transaction->getGatewayConfigurationAlias());
        $this->assertEquals('dummy_item_id', $transaction->getItemId());
        $this->assertEquals('dummy_customer_id', $transaction->getCustomerId());
        $this->assertEquals('dummy_customer_email', $transaction->getCustomerEmail());
        $this->assertEquals(10, $transaction->getAmount());
        $this->assertEquals('EUR', $transaction->getCurrencyCode());
        $this->assertEquals('Dummy description', $transaction->getDescription());
        $this->assertInternalType('array', $transaction->getMetadatas());
        $this->assertEquals(0, count($transaction->getMetadatas()));
    }
}
