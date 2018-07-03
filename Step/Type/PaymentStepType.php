<?php

namespace IDCI\Bundle\PaymentBundle\Step\Type;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\StepBundle\Navigation\NavigatorInterface;
use IDCI\Bundle\StepBundle\Step\Type\AbstractStepType;
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
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    public function __construct(
        PaymentManager $paymentManager,
        RequestStack $requestStack,
        UrlGeneratorInterface $router
    ) {
        $this->paymentManager = $paymentManager;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired([
                'amount',
                'currency_code',
                'item_id',
                'payment_gateway_configuration_alias',
            ])
            ->setDefaults([
                'callback_url' => null,
                'customer_email' => null,
                'customer_id' => null,
                'description' => null,
                'return_url' => null,
            ])
            ->setNormalizer('amount', function (Options $options, $value) {
                return intval($value, 10);
            })
            ->setAllowedTypes('amount', ['string', 'integer'])
            ->setAllowedTypes('callback_url', ['null', 'string'])
            ->setAllowedTypes('currency_code', ['string'])
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

    public function prepareNavigation(NavigatorInterface $navigator, array $options)
    {
        $request = $this->requestStack->getCurrentRequest();

        $paymentContext = $this->paymentManager->createPaymentContextByAlias($options['payment_gateway_configuration_alias']);

        if (!$request->query->has('transaction_id')) {
            $transaction = $paymentContext->createTransaction([
                'item_id' => $options['item_id'],
                'amount' => $options['amount'],
                'currency_code' => $options['currency_code'],
                'customer_id' => $options['customer_id'],
                'customer_email' => $options['customer_email'],
                'description' => $options['description'],
            ]);
        } else {
            // transaction manager pour récupérer la transaction ? retrieveTransactionByUuid()
            die('done');
        }

        $paymentContext
            ->getPaymentGatewayConfiguration()
            ->set('return_url', $this->getReturnUrl($request, $transaction))
        ;

        $options['pre_step_content'] = $paymentContext->buildHTMLView();

        return $options;
    }
}
