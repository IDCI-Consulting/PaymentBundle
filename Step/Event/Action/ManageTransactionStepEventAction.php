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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ManageTransactionStepEventAction extends AbstractStepEventAction
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
        $this->templating = $templating;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->dispatcher = $dispatcher;
        $this->templates = $templates;
    }

    private function getReturnUrl(Request $request, Transaction $transaction)
    {
        $currentUrl = $this->router->generate(
            $request->attributes->get('_route'),
            $request->attributes->get('_route_params'),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $parsedUrl = parse_url($currentUrl);

        return sprintf('%s://%s%s?%s',
            $parsedUrl['scheme'],
            $parsedUrl['host'],
            $parsedUrl['path'],
            http_build_query([
                'transaction_id' => $transaction->getId(),
            ])
        );
    }

    private function prepareInitializeTransaction(StepEventInterface $event, array $parameters = array())
    {
        $step = $event->getNavigator()->getCurrentStep();
        $configuration = $step->getConfiguration();
        $options = $configuration['options'];

        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $parameters['payment_gateway_configuration_alias']
        );
        $transaction = $paymentContext->getTransaction();

        $transaction = $paymentContext->createTransaction([
            'item_id' => $parameters['item_id'],
            'amount' => $parameters['amount'],
            'currency_code' => $parameters['currency_code'],
            'customer_id' => $parameters['customer_id'],
            'customer_email' => $parameters['customer_email'],
            'description' => $parameters['description'],
        ]);

        $request = $this->requestStack->getCurrentRequest();

        $paymentContext
            ->getPaymentGatewayConfiguration()
            ->set('return_url', $this->getReturnUrl($request, $transaction))
        ;

        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            [
                'view' => $paymentContext->buildHTMLView(),
                'transaction' => $paymentContext->getTransaction(),
            ]
        );

        $options['transaction'] = $transaction;

        $step->setOptions($options);

        return $transaction->toArray();
    }

    private function prepareReturnTransaction(StepEventInterface $event, array $parameters = array())
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
    protected function doExecute(StepEventInterface $event, array $parameters = array())
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->query->has('transaction_id')) {
            return $this->prepareInitializeTransaction($event, $parameters);
        }

        return $this->prepareReturnTransaction($event, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function setDefaultParameters(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired([
                'payment_gateway_configuration_alias',
                'amount',
                'currency_code',
                'item_id',
            ])
            ->setDefaults([
                'customer_id' => null,
                'customer_email' => null,
                'description' => null,
                'success_message' => 'Your transaction succeeded.',
                'error_message' => 'There was a problem with your transaction, please try again.',
            ])
            ->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setAllowedTypes('amount', ['integer', 'string'])
            ->setAllowedTypes('item_id', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['null', 'string'])
            ->setAllowedTypes('customer_id', ['null', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedTypes('success_message', ['null', 'string'])
            ->setAllowedTypes('error_message', ['null', 'string'])
        ;
    }
}
