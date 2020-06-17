<?php

namespace IDCI\Bundle\PaymentBundle\Form;

use IDCI\Bundle\PaymentBundle\Form\Type\PaymentGatewayConfigurationChoiceType;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Payum\ISO4217\ISO4217;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFormType extends AbstractType
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
        $paymentGatewayConfigurationAliases = array_map(function ($paymentGatewayConfiguration) {
            return $paymentGatewayConfiguration->getAlias();
        }, $this->paymentManager->getAllPaymentGatewayConfiguration());

        $paymentGatewayConfigurationAliases[] = null;

        $resolver
            ->setDefaults([
                'payment_gateway_configuration_alias' => null,
            ])
            ->setAllowedValues('payment_gateway_configuration_alias', $paymentGatewayConfigurationAliases)
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = [];

        foreach ((new ISO4217())->findAll() as $currencyCodes) {
            $choices[$currencyCodes->getAlpha3()] = $currencyCodes->getAlpha3();
        }

        $builder
            ->add('payment_gateway_configuration_alias', PaymentGatewayConfigurationChoiceType::class)
            ->add('item_id', Type\IntegerType::class)
            ->add('amount', Type\IntegerType::class)
            ->add('currency_code', Type\ChoiceType::class, [
                'choices' => $choices,
            ])
            ->add('customer_id', Type\IntegerType::class, [
                'required' => false,
            ])
            ->add('customer_email', Type\EmailType::class, [
                'required' => false,
            ])
            ->add('description', Type\TextareaType::class, [
                'required' => false,
            ])
            ->add('metadata', Type\TextareaType::class, [
                'required' => false,
            ])
            ->add('submit', Type\SubmitType::class)
        ;

        $builder->get('metadata')->addModelTransformer(new CallbackTransformer(
            function ($metadata) {
                if (null === $metadata) {
                    $metadata = [];
                }

                return json_encode($metadata);
            },
            function ($metadata) {
                return json_decode($metadata, true);
            }
        ));

        if (null !== $options['payment_gateway_configuration_alias']) {
            $builder->add('payment_gateway_configuration_alias', Type\HiddenType::class, [
                'data' => $options['payment_gateway_configuration_alias'],
            ]);
        }
    }
}
