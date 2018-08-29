<?php

namespace IDCI\Bundle\PaymentBundle\Step\Event\Action;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\StepBundle\Step\Event\Action\AbstractStepEventAction;
use IDCI\Bundle\StepBundle\Step\Event\StepEventInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangeTransactionDataStepEventAction extends AbstractStepEventAction
{
    /**
     * @var PaymentManager
     */
    protected $paymentManager;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var array
     */
    private $templates;

    public function __construct(
        ObjectManager $om,
        PaymentManager $paymentManager,
        \Twig_Environment $templating,
        array $templates
    ) {
        $this->om = $om;
        $this->paymentManager = $paymentManager;
        $this->templating = $templating;
        $this->templates = $templates;
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepEventInterface $event, array $parameters = array())
    {
        $step = $event->getNavigator()->getCurrentStep();
        $configuration = $step->getConfiguration();
        $options = $configuration['options'];

        if (!isset($options['paymentContext'])) {
            return true;
        }

        $paymentContext = $options['paymentContext'];
        $transaction = $paymentContext->getTransaction();

        $builtParameters = [
            'amount' => isset($parameters['amount']) ? $parameters['amount'] : $transaction->getAmount(),
            'item_id' => isset($parameters['item_id']) ? $parameters['item_id'] : $transaction->getItemId(),
            'currency_code' => isset($parameters['currency_code']) ? $parameters['currency_code'] : $transaction->getCurrencyCode(),
            'customer_id' => isset($parameters['customer_id']) ? $parameters['customer_id'] : $transaction->getCustomerId(),
            'customer_email' => isset($parameters['customer_email']) ? $parameters['customer_email'] : $transaction->getCustomerEmail(),
            'description' => isset($parameters['description']) ? $parameters['description'] : $transaction->getDescription(),
        ];

        $transaction
            ->setAmount($builtParameters['amount'])
            ->setItemId($builtParameters['item_id'])
            ->setCurrencyCode($builtParameters['currency_code'])
            ->setCustomerId($builtParameters['customer_id'])
            ->setCustomerEmail($builtParameters['customer_email'])
            ->setDescription($builtParameters['description'])
        ;

        $this->om->flush();

        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            [
                'view' => $paymentContext->buildHTMLView(),
                'transaction' => $paymentContext->getTransaction(),
            ]
        );

        $options['transaction'] = $transaction;

        $step->setOptions($options);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function setDefaultParameters(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'amount' => null,
                'item_id' => null,
                'currency_code' => null,
                'customer_id' => null,
                'customer_email' => null,
                'description' => null,
            ])
            ->setAllowedTypes('amount', ['integer', 'string'])
            ->setAllowedTypes('item_id', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['null', 'string'])
            ->setAllowedTypes('customer_id', ['null', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
        ;
    }
}
