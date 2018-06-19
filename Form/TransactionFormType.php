<?php

namespace IDCI\Bundle\PaymentBundle\Form;

use Payum\ISO4217\ISO4217;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFormType extends AbstractType
{
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

        foreach ((new ISO4217())->findAll() as $currencyCodes) {
            $choices[$currencyCodes->getAlpha3()] = $currencyCodes->getAlpha3();
        }

        $builder
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
            ->add('submit', Type\SubmitType::class)
        ;
    }
}
