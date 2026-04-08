<?php

namespace IDCI\Bundle\PaymentBundle\Step\Event\Action;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Exception\Gateway\GatewayException;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use IDCI\Bundle\StepBundle\Step\Event\Action\AbstractStepEventAction;
use IDCI\Bundle\StepBundle\Step\Event\StepEventInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

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
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var Environment
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
        EventDispatcherInterface $dispatcher,
        Environment $templating,
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

        return sprintf(
            '%s://%s%s?%s',
            $parsedUrl['scheme'],
            $parsedUrl['host'],
            $parsedUrl['path'],
            http_build_query(
                array_merge(
                    $request->query->all(),
                    [
                        'transaction_id' => $transaction->getId(),
                    ]
                )
            )
        );
    }

    private function getDefaultCallbackUrl(PaymentGatewayConfiguration $paymentGatewayConfiguration)
    {
        return $this->router->generate(
            'idci_payment_payment_gateway_callback',
            [
                'configuration_alias' => $paymentGatewayConfiguration->getAlias(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function prepareInitializeTransaction(StepEventInterface $event, array $parameters = [])
    {
        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $parameters['payment_gateway_configuration_alias']
        );

        if (isset($event->getStepEventData()['id'])) {
            $transaction = $this->transactionManager->retrieveTransactionByUuid($event->getStepEventData()['id']);

            if (
                null !== $transaction &&
                !in_array($transaction->getStatus(), [PaymentStatus::STATUS_FAILED, PaymentStatus::STATUS_CANCELED]) &&
                $transaction->getItemId() === $parameters['item_id'] &&
                $transaction->getAmount() === $parameters['amount'] &&
                $transaction->getCurrencyCode() === $parameters['currency_code'] &&
                $transaction->getCustomerId() === $parameters['customer_id']
            ) {
                $paymentContext->setTransaction($transaction);
            }
        }

        if (null === $paymentContext->getTransaction()) {
            $transaction = $paymentContext->createTransaction([
                'item_id' => $parameters['item_id'],
                'amount' => $parameters['amount'],
                'currency_code' => $parameters['currency_code'],
                'customer_id' => $parameters['customer_id'],
                'customer_email' => $parameters['customer_email'],
                'description' => $parameters['description'],
                'metadata' => $parameters['metadata'],
            ]);
        }

        $paymentGatewayConfiguration = $paymentContext->getPaymentGatewayConfiguration();

        $paymentGatewayConfiguration
            ->set('return_url', $this->getReturnUrl($this->requestStack->getCurrentRequest(), $transaction))
        ;

        if (!$paymentGatewayConfiguration->get('callback_url')) {
            $paymentGatewayConfiguration
                ->set('callback_url', $this->getDefaultCallbackUrl($paymentGatewayConfiguration))
            ;
        }

        $options = $event->getNavigator()->getCurrentStep()->getOptions();
        $options['prevent_previous'] = false;
        if ($parameters['allow_skip']) {
            $options['prevent_next'] = false;
        }

        $options['transaction'] = $transaction;
        try {
            $options['pre_step_content'] = $this->templating->render(
                $this->templates[PaymentStatus::STATUS_CREATED],
                array_merge(
                    $parameters['template_extra_vars'],
                    [
                        'view' => $paymentContext->buildHTMLView($parameters['gateway_options']),
                        'transaction' => $transaction,
                    ]
                )
            );
        } catch (GatewayException $e) {
            $transaction->setStatus(PaymentStatus::STATUS_FAILED)->addMetadata('gateway_exception', $e->getMessage());
            $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::FAILED);

            $options['pre_step_content'] = $this->templating->render(
                $this->templates[PaymentStatus::STATUS_FAILED],
                array_merge(
                    $parameters['template_extra_vars'],
                    [
                        'gateway_exception' => $e,
                        'error_message' => $e->getMessage(),
                    ]
                )
            );
        }

        $event->getNavigator()->getCurrentStep()->setOptions($options);

        return $transaction->toArray();
    }

    private function prepareReturnTransaction(StepEventInterface $event, array $parameters = [])
    {
        $request = $this->requestStack->getCurrentRequest();
        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $parameters['payment_gateway_configuration_alias']
        );

        $transactionId = $event->getStepEventData()['id'] ?? $request->query->get('transaction_id');

        $transaction = $paymentContext->handleReturnCallback($request);
        if (null === $transaction) {
            $transaction = $this->transactionManager->retrieveTransactionByUuid($transactionId);
        }

        $paymentContext->setTransaction($transaction);

        if (PaymentStatus::STATUS_CREATED === $transaction->getStatus()) {
            $transaction->setStatus(PaymentStatus::STATUS_PENDING);
            $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::PENDING);
        }

        $options = $event->getNavigator()->getCurrentStep()->getOptions();
        $options['prevent_previous'] = false;
        if ($parameters['allow_skip']) {
            $options['prevent_next'] = false;
        }

        if (PaymentStatus::STATUS_APPROVED === $transaction->getStatus() || PaymentStatus::STATUS_UNVERIFIED === $transaction->getStatus()) {
            $options['prevent_next'] = false;
            $options['prevent_previous'] = true;
        }

        if (PaymentStatus::STATUS_CANCELED === $transaction->getStatus() || PaymentStatus::STATUS_FAILED === $transaction->getStatus()) {
            $options['prevent_next'] = true;
        }

        $options['transaction'] = $transaction;
        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            array_merge(
                $parameters['template_extra_vars'],
                [
                    'transaction' => $transaction,
                    'success_message' => $parameters['success_message'],
                    'error_message' => $parameters['error_message'],
                ]
            )
        );
        $event->getNavigator()->getCurrentStep()->setOptions($options);

        return $transaction->toArray();
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepEventInterface $event, array $parameters = [])
    {
        $request = $this->requestStack->getCurrentRequest();

        $transaction = isset($event->getStepEventData()['id']) ?
            $this->transactionManager->retrieveTransactionByUuid($event->getStepEventData()['id']) :
            null
        ;

        if (
            !$request->query->has('transaction_id') &&
            (
                null === $transaction ||
                in_array($transaction->getStatus(), [
                    PaymentStatus::STATUS_CREATED,
                    PaymentStatus::STATUS_PENDING,
                    PaymentStatus::STATUS_FAILED,
                    PaymentStatus::STATUS_CANCELED,
                ])
            )
        ) {
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
            ->setRequired('payment_gateway_configuration_alias')->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setRequired('amount')->setAllowedTypes('amount', ['integer', 'string'])
            ->setRequired('currency_code')->setAllowedTypes('currency_code', ['null', 'string'])
            ->setRequired('item_id')->setAllowedTypes('item_id', ['null', 'string'])
            ->setDefault('allow_skip', false)->setAllowedTypes('allow_skip', ['bool', 'string'])
                ->setNormalizer(
                    'allow_skip',
                    function (OptionsResolver $options, $value) {
                        return (bool) $value;
                    }
                )
            ->setDefault('customer_id', null)->setAllowedTypes('customer_id', ['null', 'string'])
            ->setDefault('customer_email', null)->setAllowedTypes('customer_email', ['null', 'string'])
            ->setDefault('description', null)->setAllowedTypes('description', ['null', 'string'])
            ->setDefault('metadata', [])->setAllowedTypes('metadata', ['array'])
                ->setNormalizer(
                    'metadata',
                    function (OptionsResolver $options, $metadata) {
                        array_walk_recursive($metadata, function (&$value, $key) {
                            $value = json_decode($value, true) ?? $value;
                        });

                        return $metadata;
                    }
                )
            ->setDefault('gateway_options', [])->setAllowedTypes('gateway_options', ['array'])
            ->setDefault('success_message', 'Your transaction succeeded.')->setAllowedTypes('success_message', ['null', 'string'])
            ->setDefault('error_message', 'There was a problem with your transaction, please try again.')->setAllowedTypes('error_message', ['null', 'string'])
            ->setDefault('template_extra_vars', [])->setAllowedTypes('template_extra_vars', ['array'])
                ->setNormalizer(
                    'template_extra_vars',
                    function (OptionsResolver $options, $templateExtraVars) {
                        array_walk_recursive($templateExtraVars, function (&$value, $key) {
                            $value = json_decode($value, true) ?? $value;
                        });

                        return $templateExtraVars;
                    }
                )
        ;
    }
}
