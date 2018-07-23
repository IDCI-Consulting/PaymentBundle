<?php

namespace IDCI\Bundle\PaymentBundle\Test\Unit\Payment;

use PHPUnit\Framework\TestCase;
use IDCI\Bundle\PaymentBundle\Payment\TransactionFactory;

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
}
