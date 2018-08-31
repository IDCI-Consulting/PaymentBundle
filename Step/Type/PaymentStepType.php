<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use IDCI\Bundle\StepBundle\Flow\FlowData;
use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;
use IDCI\Bundle\StepBundle\Step\Type\AbstractStepType;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentStepType extends AbstractStepType
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
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var \Twig_Environment
     */
    protected $templating;

    /**
     * @var array
     */
    private $templates;

    public function __construct(
        PaymentManager $paymentManager,
        TransactionManagerInterface $transactionManager,
        EventDispatcher $dispatcher,
        RequestStack $requestStack,
        UrlGeneratorInterface $router,
        \Twig_Environment $templating,
        array $templates
    ) {
        $this->paymentManager = $paymentManager;
        $this->transactionManager = $transactionManager;
        $this->dispatcher = $dispatcher;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->templating = $templating;
        $this->templates = $templates;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired([
                'payment_gateway_configuration_alias',
            ])
            ->setDefaults([
                'amount' => null,
                'item_id' => null,
                'currency_code' => null,
                'callback_url' => null,
                'customer_email' => null,
                'customer_id' => null,
                'description' => null,
                'return_url' => null,
            ])
            ->setNormalizer('amount', function (Options $options, $value) {
                return null !== $value ? intval($value, 10) : -1;
            })
            ->setNormalizer('item_id', function (Options $options, $value) {
                return null !== $value ? $value : -1;
            })
            ->setAllowedTypes('amount', ['null', 'string', 'integer'])
            ->setAllowedTypes('callback_url', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['null', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('customer_id', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedTypes('item_id', ['null', 'string'])
            ->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setAllowedTypes('return_url', ['null', 'string'])
        ;
    }

    public function buildNavigationStepForm(FormBuilderInterface $builder, array $options)
    {
        return;
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

    private function prepareInitializeTransaction(NavigatorInterface $navigator, array $options)
    {
        $options['prevent_next'] = true;

        $request = $this->requestStack->getCurrentRequest();
        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $options['payment_gateway_configuration_alias']
        );

        $transaction = $paymentContext->createTransaction([
            'item_id' => $options['item_id'],
            'amount' => $options['amount'],
            'currency_code' => $options['currency_code'],
            'customer_id' => $options['customer_id'],
            'customer_email' => $options['customer_email'],
            'description' => $options['description'],
        ]);

        $paymentContext
            ->getPaymentGatewayConfiguration()
            ->set('return_url', $this->getReturnUrl($request, $transaction))
        ;
        $options['paymentContext'] = $paymentContext;

        $events = array_map(function ($event) {
            return 'change_transaction_data' === $event['action'];
        }, $options['events']['form.pre_set_data']);

        if (in_array(true, $events)) {
            return $options;
        }

        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            [
                'view' => $paymentContext->buildHTMLView(),
                'transaction' => $transaction,
            ]
        );

        return $options;
    }

    private function prepareReturnTransaction(NavigatorInterface $navigator, array $options)
    {
        $request = $this->requestStack->getCurrentRequest();
        $paymentContext = $this->paymentManager->createPaymentContextByAlias(
            $options['payment_gateway_configuration_alias']
        );

        $transaction = $this->transactionManager->retrieveTransactionByUuid($request->query->get('transaction_id'));
        $paymentContext->setTransaction($transaction);

        if (PaymentStatus::STATUS_CREATED === $transaction->getStatus()) {
            $transaction->setStatus(PaymentStatus::STATUS_PENDING);
            $this->dispatcher->dispatch(TransactionEvent::PENDING, new TransactionEvent($transaction));
        }

        $options['pre_step_content'] = $this->templating->render(
            $this->templates[$transaction->getStatus()],
            [
                'transaction' => $transaction,
            ]
        );

        $navigator
            ->getFlow()
            ->getData()
            ->setStepData(
                $navigator->getCurrentStep()->getName(),
                ['transaction' => $transaction->toArray()],
                FlowData::TYPE_RETRIEVED
            )
        ;

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
