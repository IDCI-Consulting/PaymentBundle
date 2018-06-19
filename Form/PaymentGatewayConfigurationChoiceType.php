<?php

namespace IDCI\Bundle\PaymentBundle\Form;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
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
            ->setDefaults([
                'method' => 'GET',
            ])
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = [];

        foreach ($this->paymentManager->getAllPaymentGatewayConfiguration() as $paymentGatewayConfiguration) {
            $choices[$paymentGatewayConfiguration->getAlias()] = $paymentGatewayConfiguration->getAlias();
        }

        $builder
            ->add('gateway_configuration_alias', Type\ChoiceType::class, [
                'choices' => $choices,
                'required' => true,
            ])
            ->add('submit', Type\SubmitType::class)
        ;
    }
}
