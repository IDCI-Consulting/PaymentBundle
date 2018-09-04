<?php

namespace IDCI\Bundle\PaymentBundle\Step\Event\Action;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use IDCI\Bundle\StepBundle\Step\Event\Action\AbstractStepEventAction;
use IDCI\Bundle\StepBundle\Step\Event\StepEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReturnTransactionStepEventAction extends AbstractStepEventAction
{
    /**
     * @var PaymentManager
     */
    protected $paymentManager;

    /**
     * @var TransactionManagerInterface
     */
    protected $transactionManager;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var array
     */
    private $templates;

    public function __construct(
        PaymentManager $paymentManager,
        TransactionManagerInterface $transactionManager,
        UrlGeneratorInterface $router,
        RequestStack $requestStack,
        EventDispatcher $dispatcher,
        \Twig_Environment $templating,
        array $templates
    ) {
        $this->paymentManager = $paymentManager;
        $this->transactionManager = $transactionManager;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->templates = $templates;
        $this->templating = $templating;
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepEventInterface $event, array $parameters = array())
    {
        $request = $this->requestStack->getCurrentRequest();
        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $parameters['payment_gateway_configuration_alias']
        );

        $transaction = $this->transactionManager->retrieveTransactionByUuid($request->query->get('transaction_id'));
        $paymentContext->setTransaction($transaction);

        if (PaymentStatus::STATUS_CREATED === $transaction->getStatus()) {
            $transaction->setStatus(PaymentStatus::STATUS_PENDING);
            $this->dispatcher->dispatch(TransactionEvent::PENDING, new TransactionEvent($transaction));
        }

        $options = $event->getNavigator()->getCurrentStep()->getOptions();
        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            [
                'transaction' => $transaction,
                'successMessage' => $parameters['success_message'],
                'errorMessage' => $parameters['error_message'],
            ]
        );
        $event->getNavigator()->getCurrentStep()->setOptions($options);

        return $transaction->toArray();
    }

    /**
     * {@inheritdoc}
     */
    protected function setDefaultParameters(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired([
                'payment_gateway_configuration_alias',
            ])
            ->setDefaults([
                'success_message' => 'Your transaction succeeded.',
                'error_message' => 'There was a problem with your transaction, please try again.',
            ])
            ->setAllowedTypes('success_message', ['null', 'string'])
            ->setAllowedTypes('error_message', ['null', 'string'])
            ->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
        ;
    }
}
