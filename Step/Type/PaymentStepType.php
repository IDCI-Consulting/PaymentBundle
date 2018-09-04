<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;
use IDCI\Bundle\StepBundle\Step\Type\AbstractStepType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentStepType extends AbstractStepType
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
    }

    public function buildNavigationStepForm(FormBuilderInterface $builder, array $options)
    {
        return;
    }

    private function prepareInitializeTransaction(NavigatorInterface $navigator, array $options)
    {
        $options['prevent_next'] = true;

        foreach ($options['events']['form.pre_set_data'] as $id => $event) {
            if ('return_transaction' === $event['action']) {
                unset($options['events']['form.pre_set_data'][$id]);
            }
        }

        return $options;
    }

    private function prepareReturnTransaction(NavigatorInterface $navigator, array $options)
    {
        foreach ($options['events']['form.pre_set_data'] as $id => $event) {
            if ('initialize_transaction' === $event['action']) {
                unset($options['events']['form.pre_set_data'][$id]);
            }
        }

        return $options;
    }

    public function prepareNavigation(NavigatorInterface $navigator, array $options)
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->query->has('transaction_id')) {
            return $this->prepareInitializeTransaction($navigator, $options);
        }

        return $this->prepareReturnTransaction($navigator, $options);
    }
}
