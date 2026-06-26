<?php

namespace IDCI\Bundle\PaymentBundle\Form\Type;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentGatewayConfigurationChoiceType extends AbstractType
{
    /**
     * @var PaymentManager
     */
    private $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setNormalizer('choices', function (Options $options, $value) {
                $choices = [];

                foreach ($this->paymentManager->getAllPaymentGatewayConfiguration() as $paymentGatewayConfiguration) {
                    $choices[$paymentGatewayConfiguration->getAlias()] = $paymentGatewayConfiguration->getAlias();
                }

                return $choices;
            })
        ;
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
