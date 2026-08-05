<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\PaymentBundle\System\AbstractPaymentSystem;
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
    }

    public function prepareNavigation(NavigatorInterface $navigator, array $options): array
    {
        if (!$navigator->getRequest()->query->has(AbstractPaymentSystem::TRANSACTION_REFERENCE_QUERY_PARAMETER)) {
            $options['prevent_next'] = true;
        } else {
            $options['prevent_previous'] = true;
        }

        return $options;
    }
}
