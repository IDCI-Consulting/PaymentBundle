<?php

namespace IDCI\Bundle\PaymentBundle\Form;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Payum\ISO4217\ISO4217;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('data_class', Transaction::class)
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $currencyChoices = [];
        foreach ((new ISO4217())->findAll() as $currencyCodes) {
            $currencyChoices[$currencyCodes->getAlpha3()] = $currencyCodes->getAlpha3();
        }

        $builder
            ->add('payment_method', Type\TextType::class, [
                'required' => false,
            ])
            ->add('item_reference', Type\TextType::class, [
                'required' => true,
            ])
            ->add('customer_reference', Type\TextType::class, [
                'required' => false,
            ])
             ->add('customer_email', Type\EmailType::class, [
                 'required' => false,
             ])
            ->add('amount', Type\IntegerType::class, [
                'required' => true,
            ])
            ->add('currency_code', Type\ChoiceType::class, [
                'required' => true,
                'choices' => $currencyChoices,
                'preferred_choices' => ['EUR'],
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
    }
}
