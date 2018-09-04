<?php

namespace IDCI\Bundle\PaymentBundle\Step\Event\Action;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\StepBundle\Step\Event\Action\AbstractStepEventAction;
use IDCI\Bundle\StepBundle\Step\Event\StepEventInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InitializeTransactionStepEventAction extends AbstractStepEventAction
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

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(
        PaymentManager $paymentManager,
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        RequestStack $requestStack,
        array $templates
    ) {
        $this->paymentManager = $paymentManager;
        $this->templating = $templating;
        $this->requestStack = $requestStack;
        $this->router = $router;
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

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepEventInterface $event, array $parameters = array())
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

        return true;
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
            ])
            ->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setAllowedTypes('amount', ['integer', 'string'])
            ->setAllowedTypes('item_id', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['null', 'string'])
            ->setAllowedTypes('customer_id', ['null', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
        ;
    }
}
