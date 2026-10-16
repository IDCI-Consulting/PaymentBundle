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

            throw new GatewayException(sprintf('Error: %s. Context: %s', $e->getMessage(), json_encode($e->response->json)));
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
                    ->setDefined('expires_after')->setAllowedTypes('expires_after', ['int'])
                    ->setDefined('capture_method')->setAllowedValues('capture_method', ['manual', 'automatic'])
                    ->setDefined('failure_return_url')->setAllowedTypes('failure_return_url', ['string'])
                    ->setDefined('cart')->setAllowedTypes('failure_return_url', ['array'])
                        ->setNormalizer('cart', function (Options $options, array $value) {
                            $itemResolver = (new OptionsResolver())
                                ->setDefined('sku')->setAllowedTypes('sku', ['string'])
                                ->setDefined('vendor')->setAllowedTypes('vendor', ['string'])
                                ->setRequired('title')->setAllowedTypes('title', ['string'])
                                ->setDefined('variant_title')->setAllowedTypes('variant_title', ['string'])
                                ->setRequired('quantity')->setAllowedTypes('quantity', ['int'])
                                ->setRequired('unit_price')->setAllowedTypes('unit_price', ['int'])
                                ->setRequired('line_price')->setAllowedTypes('line_price', ['int'])
                                ->setDefined('is_gift')->setAllowedTypes('is_gift', ['bool'])
                                ->setDefined('categories')->setAllowedTypes('categories', ['array'])
                                ->setDefined('url')->setAllowedTypes('url', ['string'])
                                ->setDefined('picture_url')->setAllowedTypes('picture_url', ['string'])
                                ->setRequired('requires_shipping')->setAllowedTypes('requires_shipping', ['bool'])
                                ->setRequired('tax_included')->setAllowedTypes('tax_included', ['bool'])
                                ->setDefined('extra_data')->setAllowedTypes('extra_data', ['array'])
                                ->setDefined('marketplace_seller')->setAllowedTypes('marketplace_seller', ['array'])
                            ;

                            $discountResolver = (new OptionsResolver())
                                ->setDefined('title')->setAllowedTypes('title', ['string'])
                                ->setDefined('amount')->setAllowedTypes('amount', ['int'])
                                ->setDefined('amounts')->setAllowedTypes('amounts', ['int'])
                            ;

                            $cartResolver = (new OptionsResolver())
                                ->setRequired('items')->setAllowedTypes('items', ['array'])
                                    ->setNormalizer('items', function (Options $options, $value) use ($itemResolver) {
                                        return array_map(function (array $item) use ($itemResolver) {
                                            return $itemResolver->resolve($item);
                                        }, $value);
                                    })
                                ->setDefined('discounts')->setAllowedTypes('discounts', ['array'])
                                    ->setNormalizer('discounts', function (Options $options, $value) use ($discountResolver) {
                                        return array_map(function (array $discount) use ($discountResolver) {
                                            return $discountResolver->resolve($discount);
                                        }, $value);
                                    })
                            ;

                            return $cartResolver->resolve($value);
                        })
                    ->setDefined('shipping_info')->setAllowedTypes('shipping_info', ['array'])
                        ->setNormalizer('shipping_info', function (Options $options, array $value) {
                            $shippingOptionResolver = (new OptionsResolver())
                                ->setRequired('title')->setAllowedTypes('title', ['string'])
                                ->setRequired('amount')->setAllowedTypes('amount', ['int'])
                                ->setDefined('carrier')->setAllowedTypes('carrier', ['string'])
                                ->setDefined('express_delivery')->setAllowedTypes('express_delivery', ['bool'])
                                ->setDefined('pickup_delivery')->setAllowedTypes('pickup_delivery', ['bool'])
                                ->setDefined('shipping_method')->setAllowedValues('shipping_method', ['instore', 'pickup_store', 'pickup_point', 'postal'])
                            ;

                            $shippingInfoResolver = (new OptionsResolver())
                                ->setDefined('selected_options')->setAllowedTypes('selected_options', ['array'])
                                    ->setNormalizer('selected_options', function (Options $options, $value) use ($shippingOptionResolver) {
                                        return array_map(function (array $option) use ($shippingOptionResolver) {
                                            return $shippingOptionResolver->resolve($option);
                                        }, $value);
                                    })
                                ->setDefined('available_options')->setAllowedTypes('available_options', ['array'])
                                    ->setNormalizer('available_options', function (Options $options, $value) use ($shippingOptionResolver) {
                                        return array_map(function (array $option) use ($shippingOptionResolver) {
                                            return $shippingOptionResolver->resolve($option);
                                        }, $value);
                                    })
                            ;

                            return $shippingInfoResolver->resolve($value);
                        })
                    ->setDefault('integration_origin', 'IDCI')->setAllowedTypes('integration_origin', ['string'])
                    ->setDefined('merchant_covers_all_fees')->setAllowedTypes('merchant_covers_all_fees', ['bool'])
                ;
            })
            ->setDefault('customer', function (OptionsResolver $customerResolver) use ($transaction) {
                $customerResolver
                    ->setDefined('id')->setAllowedTypes('id', ['string'])
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
                    ->setDefined('banking_data_collected')->setAllowedTypes('banking_data_collected', ['bool'])
                    ->setDefined('is_business')->setAllowedTypes('is_business', ['bool'])
                    ->setDefined('business_id_number')->setAllowedTypes('business_id_number', ['string'])
                    ->setDefined('business_name')->setAllowedTypes('business_name', ['string'])
                    ->setDefault('account_id', $transaction->getCustomerId())->setAllowedTypes('account_id', ['string'])
                    ->setDefined('account_created')->setAllowedTypes('account_created', [\DateTimeInterface::class, 'string'])
                        ->setNormalizer('account_created', function (Options $options, $value) {
                            if (is_string($value)) {
                                $value = new \DateTime($value);
                            }

                            return $value->format('U');
                        })
                    ->setDefined('previous_orders_count')->setAllowedTypes('previous_orders_count', ['int'])
                    ->setDefined('gender')->setAllowedValues('gender', ['male', 'female'])
                    ->setDefined('national_identifier', function (OptionsResolver $cartResolver) {
                        $cartResolver
                            ->setDefined('type')->setAllowedValues('type', ['NIF_ES', 'codice_fiscale'])
                            ->setDefined('value')->setAllowedTypes('value', ['string'])
                        ;
                    })
                ;
            })
            ->setDefault('order', function (OptionsResolver $orderResolver) use ($transaction) {
                $orderResolver
                    ->setDefault('merchant_reference', $transaction->getId())->setAllowedTypes('merchant_reference', ['string'])
                    ->setDefined('merchant_url')->setAllowedTypes('merchant_url', ['string'])
                    ->setDefined('customer_url')->setAllowedTypes('customer_url', ['string'])
                    ->setDefined('comment')->setAllowedTypes('comment', ['string'])
                ;
            })
        ;

        return $resolver->resolve($options);
    }
}
