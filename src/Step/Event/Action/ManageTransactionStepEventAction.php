<?php

namespace IDCI\Bundle\PaymentBundle\Step\Event\Action;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use IDCI\Bundle\StepBundle\Step\Event\Action\AbstractStepEventAction;
use IDCI\Bundle\StepBundle\Step\Event\StepEventInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class ManageTransactionStepEventAction extends AbstractStepEventAction
{
    public const TRANSACTION_ID_QUERY_PARAMETER = 'transaction_id';

    protected PaymentManager $paymentManager;

    protected RequestStack $requestStack;

    private Environment $templating;

    private array $templates;

    public function __construct(
        PaymentManager $paymentManager,
        RequestStack $requestStack,
        Environment $templating,
        array $templates = [],
    ) {
        $this->paymentManager = $paymentManager;
        $this->templating = $templating;
        $this->requestStack = $requestStack;
        $this->templates = $templates;
    }

    protected function doExecute(StepEventInterface $event, array $parameters = [])
    {
        $paymentContext = $this->paymentManager->createPaymentContext(
            $parameters['payment_gateway_configuration_alias'],
            $this->requestStack->getCurrentRequest()
        );

        if ($paymentContext->isReturnClientRequest()) {
            $paymentContext->retrieveTransaction();
        } else {
            $paymentContext->initializeTransaction([
                'payment_gateway_configuration_alias' => $parameters['payment_gateway_configuration_alias'],
                'payment_method' => $parameters['payment_method'],
                'amount' => $parameters['amount'],
                'currency_code' => $parameters['currency_code'],
                'item_reference' => $parameters['item_reference'],
                'customer_reference' => $parameters['customer_reference'],
                'customer_email' => $parameters['customer_email'],
                'description' => $parameters['description'],
            ], $parameters['gateway_parameters']);
        }

        $processedTransactionResult = $paymentContext->processTransaction([
            'locale' => 'fr',
        ]);

        $options = $event->getNavigator()->getCurrentStep()->getOptions();
        $options['prevent_previous'] = false;
        if ($parameters['allow_skip']) {
            $options['prevent_next'] = false;
        }

        $options['transaction'] = $paymentContext->getTransaction();

        if (ProcessedTransactionResult::TYPE_HTML === $processedTransactionResult->getType()) {
            $options['pre_step_content'] = $processedTransactionResult->getContent();

            /*
            $options['pre_step_content'] = $this->render('@IDCIPayment/Test/transaction.html.twig' / $this->templates[PaymentStatus::STATUS_CREATED], [
                array_merge(
                    $parameters['template_extra_vars'],
                    [
                        // TODO: Call $paymentContext->buildInitializeHTMLView
                        'view' => $processedTransactionResult->getContent(),
                        'transaction' => $transaction,
                    ]
                )
            ]);
            */
        }

        if (ProcessedTransactionResult::TYPE_REDIRECTION === $processedTransactionResult->getType()) {
            dd('ici');
            //return $this->redirect($processedTransactionResult->getContent());
        }

        $event->getNavigator()->getCurrentStep()->setOptions($options);

        return $paymentContext->getTransaction()->toArray();
    }

    protected function setDefaultParameters(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('payment_gateway_configuration_alias')->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setRequired('amount')->setAllowedTypes('amount', ['integer', 'string'])
            ->setRequired('currency_code')->setAllowedTypes('currency_code', ['null', 'string'])
            ->setRequired('item_reference')->setAllowedTypes('item_reference', ['null', 'string'])
            ->setDefault('payment_method', null)->setAllowedTypes('payment_method', ['null', 'string'])
            ->setDefault('allow_skip', false)->setAllowedTypes('allow_skip', ['bool', 'string'])
                ->setNormalizer(
                    'allow_skip',
                    function (OptionsResolver $options, $value) {
                        return (bool) $value;
                    }
                )
            ->setDefault('customer_reference', null)->setAllowedTypes('customer_reference', ['null', 'string'])
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
            ->setDefault('gateway_parameters', [])->setAllowedTypes('gateway_parameters', ['array'])
#            ->setDefault('success_message', 'Your transaction succeeded.')->setAllowedTypes('success_message', ['null', 'string'])
#            ->setDefault('error_message', 'There was a problem with your transaction, please try again.')->setAllowedTypes('error_message', ['null', 'string'])
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
