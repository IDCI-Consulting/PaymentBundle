<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;
use IDCI\Bundle\StepBundle\Step\Type\AbstractStepType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentStepType extends AbstractStepType
{
    /**
     * @var PaymentManager
     */
    protected $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired([
                'amount',
                'currency_code',
                'item_id',
                'payment_gateway_configuration_alias',
            ])
            ->setDefaults([
                'callback_url' => null,
                'customer_email' => null,
                'customer_id' => null,
                'description' => null,
                'return_url' => null,
            ])
            ->setNormalizer('amount', function (Options $options, $value) {
                return intval($value, 10);
            })
            ->setAllowedTypes('amount', ['string', 'integer'])
            ->setAllowedTypes('callback_url', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('customer_id', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedTypes('item_id', ['null', 'string'])
            ->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setAllowedTypes('return_url', ['null', 'string'])
        ;
    }

    public function buildNavigationStepForm(FormBuilderInterface $builder, array $options)
    {
        return;
    }

    public function prepareNavigation(NavigatorInterface $navigator, array $options)
    {
        $paymentContext = $this->paymentManager->createPaymentContextByAlias($options['payment_gateway_configuration_alias']);

        $transaction = $paymentContext->createTransaction([
            'item_id' => $options['item_id'],
            'amount' => $options['amount'],
            'currency_code' => $options['currency_code'],
            'customer_id' => $options['customer_id'],
            'customer_email' => $options['customer_email'],
            'description' => $options['description'],
        ]);

        $options['pre_step_content'] = $paymentContext->buildHTMLView();

        return $options;
    }
}
