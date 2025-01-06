<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Alma\API\Client;
use Alma\API\RequestError;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\AlmaStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class AlmaPaymentGateway extends AbstractPaymentGateway
{
    private $logger;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger
    ) {
        parent::__construct($templating, $dispatcher);

        $this->logger = $logger;
    }

    private function getClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration)
    {
        if (!class_exists(Client::class)) {
            throw new \RuntimeException('AlmaPaymentGateway cache requires "alma/alma-php-client" package');
        }

        return new Client($paymentGatewayConfiguration->get('api_key'), [
            'mode' => $paymentGatewayConfiguration->get('mode'),
        ]);
    }

    private function createPayment(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
        try {
            return $this->getClient($paymentGatewayConfiguration)->payments->create(
                $this->resolveCreatePaymentOptions(
                    $paymentGatewayConfiguration,
                    $transaction,
                    $transaction->getMetadata('payment_options') ?? []
                )
            );
        } catch (RequestError $e) {
            $this->logger->error(sprintf('Error: %s. Context: %s', $e->getMessage(), json_encode($e->response->json)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        $payment = $this->createPayment($paymentGatewayConfiguration, $transaction);

        return [
            'url' => $payment->url,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/alma.html.twig', [
            'url' => $initializationData['url'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = new GatewayResponse();

        if (!$request->query->has('pid')) {
            return $gatewayResponse;
        }

        try {
            $payment = $this->getClient($paymentGatewayConfiguration)->payments->fetch($request->query->get('pid'));

            $gatewayResponse
                ->setTransactionUuid($payment->orders[0]->merchant_reference)
                ->setAmount($payment->purchase_amount)
                ->setRaw(get_object_vars($payment))
            ;

            if (!in_array($payment->state, [AlmaStatusCode::STATUS_IN_PROGRESS, AlmaStatusCode::STATUS_PAID])) {
                return $gatewayResponse->setStatus(PaymentStatus::STATUS_FAILED);
            }

            return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error: %s. Context: %s', $e->getMessage(), json_encode($e->response->json)));
        }

        return $gatewayResponse;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the request method is not POST
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('GET')) {
            throw new \UnexpectedValueException('Alma : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setRaw($request->query->all())
        ;

        if (!$request->query->has('pid')) {
            throw new \UnexpectedValueException('Alma : Payment Gateway error (missing pid query parameter)');
        }

        $payment = $this->getClient($paymentGatewayConfiguration)->payments->fetch($request->query->get('pid'));

        $gatewayResponse
            ->setTransactionUuid($payment->orders[0]->merchant_reference)
            ->setAmount($payment->purchase_amount)
            ->setRaw(get_object_vars($payment))
        ;

        if (!in_array($payment->state, [AlmaStatusCode::STATUS_IN_PROGRESS, AlmaStatusCode::STATUS_PAID])) {
            $gatewayResponse->setMessage(AlmaStatusCode::getStatusMessage($payment->state));

            return $gatewayResponse;
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'api_key',
                'mode',
            ]
        );
    }

    /**
     * Resolve createPayment options.
     *
     * @method resolveCreatePaymentOptions
     */
    private function resolveCreatePaymentOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options
    ): array {
        $resolver = (new OptionsResolver())
            ->setDefault('payment', function (OptionsResolver $paymentResolver) use ($paymentGatewayConfiguration, $transaction) {
                $paymentResolver
                    ->setDefault('purchase_amount', $transaction->getAmount())->setAllowedTypes('purchase_amount', ['int'])
                    ->setDefined('installments_count')->setAllowedTypes('installments_count', ['int'])
                    ->setDefault('billing_address', function (OptionsResolver $billingAddressResolver) {
                        $billingAddressResolver
                            ->setDefined('company')->setAllowedTypes('company', ['string'])
                            ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                            ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                            ->setDefined('email')->setAllowedTypes('email', ['string'])
                            ->setDefined('phone')->setAllowedTypes('phone', ['string'])
                            ->setDefined('line1')->setAllowedTypes('line1', ['string'])
                            ->setDefined('line2')->setAllowedTypes('line2', ['string'])
                            ->setDefined('postal_code')->setAllowedTypes('postal_code', ['string', 'int'])
                            ->setDefined('city')->setAllowedTypes('city', ['string'])
                            ->setDefined('country')->setAllowedTypes('country', ['string'])
                        ;
                    })
                    ->setDefault('customer_cancel_url', $paymentGatewayConfiguration->get('return_url'))->setAllowedTypes('customer_cancel_url', ['string'])
                    ->setDefined('custom_data')->setAllowedTypes('custom_data', ['string', 'array'])
                        ->setNormalizer('custom_data', function (Options $options, $value) {
                            if (is_array($value)) {
                                $value = json_encode($value);
                            }

                            return $value;
                        })
                    ->setDefined('deferred_months')->setAllowedTypes('deferred_months', ['int'])
                    ->setDefined('deferred_days')->setAllowedTypes('deferred_days', ['int'])
                    ->setDefault('ipn_callback_url', $paymentGatewayConfiguration->get('callback_url'))->setAllowedTypes('ipn_callback_url', ['string'])
                    ->setDefined('origin')->setAllowedTypes('origin', ['string'])
                    ->setDefault('return_url', $paymentGatewayConfiguration->get('return_url'))->setAllowedTypes('return_url', ['string'])
                    ->setDefault('shipping_address', function (OptionsResolver $shippingAddressResolver) {
                        $shippingAddressResolver
                            ->setDefined('company')->setAllowedTypes('company', ['string'])
                            ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                            ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                            ->setDefined('email')->setAllowedTypes('email', ['string'])
                            ->setDefined('phone')->setAllowedTypes('phone', ['string'])
                            ->setDefined('line1')->setAllowedTypes('line1', ['string'])
                            ->setDefined('line2')->setAllowedTypes('line2', ['string'])
                            ->setDefined('postal_code')->setAllowedTypes('postal_code', ['string', 'int'])
                            ->setDefined('city')->setAllowedTypes('city', ['string'])
                            ->setDefined('country')->setAllowedTypes('country', ['string'])
                        ;
                    })
                    ->setDefined('locale')->setAllowedTypes('locale', ['string'])
                    ->setDefined('deferred')->setAllowedValues('deferred', ['trigger'])
                    ->setDefined('deferred_description')->setAllowedTypes('deferred_description', ['string'])
                    ->setDefined('expires_after')->setAllowedTypes('expires_after', ['int'])
                ;
            })
            ->setDefault('customer', function (OptionsResolver $customerResolver) use ($transaction) {
                $customerResolver
                    ->setDefined('id')->setAllowedTypes('id', ['string'])
                    ->setDefault('identifier', $transaction->getCustomerId())->setAllowedTypes('identifier', ['string'])
                    ->setDefined('created')->setAllowedTypes('created', [\DateTimeInterface::class, 'string'])
                    ->setNormalizer('created', function (Options $options, $value) {
                        if (is_string($value)) {
                            $value = new \DateTime($value);
                        }

                        return $value->format('U');
                    })
                    ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                    ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                    ->setDefined('addresses')->setAllowedTypes('addresses', ['array'])
                    ->setDefault('email', $transaction->getCustomerEmail())->setAllowedTypes('email', ['string'])
                    ->setDefined('phone')->setAllowedTypes('phone', ['string'])
                    ->setDefined('birth_date')->setAllowedTypes('birth_date', ['string'])
                    ->setDefined('birth_place')->setAllowedTypes('birth_place', ['string'])
                    ->setDefault('card', function (OptionsResolver $cardResolver) {
                        $cardResolver
                            ->setDefined('id')->setAllowedTypes('id', ['string'])
                            ->setDefined('created')->setAllowedTypes('created', [\DateTimeInterface::class, 'string'])
                                ->setNormalizer('created', function (Options $options, $value) {
                                    if (is_string($value)) {
                                        $value = new \DateTime($value);
                                    }

                                    return $value->format('U');
                                })
                            ->setDefined('exp_month')->setAllowedTypes('exp_month', ['int'])
                            ->setDefined('exp_year')->setAllowedTypes('exp_year', ['int'])
                            ->setDefined('last4')->setAllowedTypes('last4', ['string'])
                            ->setDefined('country')->setAllowedTypes('country', ['string'])
                            ->setDefined('funding')->setAllowedValues('funding', ['debit', 'credit', 'unknown'])
                            ->setDefined('brand')->setAllowedValues('brand', ['visa', 'mastercard', 'american express'])
                            ->setDefined('three_d_secure_possible')->setAllowedTypes('three_d_secure_possible', ['bool'])
                            ->setDefined('verified')->setAllowedTypes('verified', ['bool'])
                        ;
                    })
                    ->setDefined('banking_data_collected')->setAllowedTypes('banking_data_collected', ['bool'])
                    ->setDefined('is_business')->setAllowedTypes('is_business', ['bool'])
                    ->setDefined('business_id_number')->setAllowedTypes('business_id_number', ['string'])
                    ->setDefined('business_name')->setAllowedTypes('business_name', ['string'])
                ;
            })
            ->setDefault('order', function (OptionsResolver $orderResolver) use ($transaction) {
                $orderResolver
                    ->setDefault('merchant_reference', $transaction->getId())->setAllowedTypes('merchant_reference', ['string'])
                    ->setDefined('merchant_url')->setAllowedTypes('merchant_url', ['string'])
                    ->setDefined('data')->setAllowedTypes('data', ['string', 'array'])
                        ->setNormalizer('data', function (Options $options, $value) {
                            if (is_string($value)) {
                                $value = json_decode($value, true);
                            }

                            return $value;
                        })
                    ->setDefined('customer_url')->setAllowedTypes('customer_url', ['string'])
                    ->setDefined('comment')->setAllowedTypes('comment', ['string'])
                ;
            })
        ;

        return $resolver->resolve($options);
    }
}
