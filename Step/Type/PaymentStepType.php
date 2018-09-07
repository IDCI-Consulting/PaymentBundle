<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;
use IDCI\Bundle\StepBundle\Step\Type\AbstractStepType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentStepType extends AbstractStepType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
    }

    public function buildNavigationStepForm(FormBuilderInterface $builder, array $options)
    {
        return;
    }

    public function prepareNavigation(NavigatorInterface $navigator, array $options)
    {
        return $options;
    }
}
