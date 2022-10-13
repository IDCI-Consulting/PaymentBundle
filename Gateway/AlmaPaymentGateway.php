<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Alma\API\Client;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\EurekaStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlmaPaymentGateway extends AbstractPaymentGateway
{
    private function getClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration)
    {
        if (!class_exists(Client::class)) {
            throw new \RuntimeException('AlmaPaymentGateway cache requires "alma/alma-php-client" package');
        }

        return new Client($paymentGatewayConfiguration->get('api_key'));
    }

    private function createPayment(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
        $payment = $this->getClient($paymentGatewayConfiguration)->payments->createPayment(
            $this->resolveCreatePaymentOptions(
                $paymentGatewayConfiguration,
                $transaction,
                $transaction->getMetadata('payment_options') ?? []
            )
        );

        dd($payment);
    }

    /**
     * Build payment gateway options.
     *
     * @method buildOptions
     *
     * @throws \UnexpectedValueException If required transaction metadata is not set
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $payment = $this->createPayment($paymentGatewayConfiguration, $transaction);

        return [
            'payment' => $payment,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return array_merge($options, [
            'hmac' => $this->buildHmac($options, self::HMAC_TYPE_ENTRY),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/alma.html.twig', [
            // 'url' => $this->eurekaPaymentGatewayClient->getPaymentFormUrl(),
            // 'initializationData' => $initializationData,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the request method is not POST
     */
    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('POST')) {
            throw new \UnexpectedValueException('Eureka : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setRaw($request->request->all())
        ;

        $payment = $this->getClient($paymentGatewayConfiguration)->payments->fetch($paymentId);

        $gatewayResponse
            ->setTransactionUuid($request->request->get('orderRef'))
            ->setAmount($request->request->get('amount'))
            ->setCurrencyCode($request->request->get('currency'))
        ;

        if ('0' !== $request->request->get('returnCode')) {
            $gatewayResponse->setMessage(EurekaStatusCode::getStatusMessage($request->request->get('returnCode')));

            if ('6' === $request->request->get('returnCode')) {
                $gatewayResponse->setStatus(PaymentStatus::STATUS_CANCELED);
            }

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
                'merchant_id',
                'api_key',
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
                            ->setDefined('postal_code')->setAllowedTypes('postal_code', ['string'])
                            ->setDefined('city')->setAllowedTypes('city', ['string'])
                            ->setDefined('country')->setAllowedTypes('country', ['string'])
                        ;
                    })
                    ->setDefined('customer_cancel_url')->setAllowedTypes('customer_cancel_url', ['string'])
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
                            ->setDefined('postal_code')->setAllowedTypes('postal_code', ['string'])
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
                    ->setDefault('id', $transaction->getCustomerId())->setAllowedTypes('id', ['string'])
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
                    ->setDefault('merchant_reference', $transaction->getItemId())->setAllowedTypes('merchant_reference', ['string'])
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
