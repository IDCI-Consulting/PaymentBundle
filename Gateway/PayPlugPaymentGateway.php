<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payplug;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PayPlugPaymentGateway extends AbstractPaymentGateway
{
    const MODE_HOSTED = 'hosted';
    const MODE_LIGHTBOX = 'lightbox';
    const MODE_INTEGRATED = 'integrated';

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        Payplug\Payplug::init(array(
            'apiVersion' => $paymentGatewayConfiguration->get('version'),
            'secretKey' => $paymentGatewayConfiguration->get('secret_key'),
        ));

        $payment = Payplug\Payment::create(array_replace_recursive(
            $this->resolvePaymentOptions($paymentGatewayConfiguration, $transaction, $options),
            [
                'metadata' => [
                    'transaction_id' => $transaction->getId(),
                ],
            ]
        ));

        return [
            'mode' => $paymentGatewayConfiguration->get('mode'),
            'payment' => $payment,
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
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction, $options);

        return $this->templating->render('@IDCIPayment/Gateway/payplug.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return new GatewayResponse();
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
        if (!$request->isMethod(Request::METHOD_POST)) {
            throw new \UnexpectedValueException('PayPlug : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (empty($request->request->all())) {
            return $gatewayResponse->setMessage('The request do not contains required post data');
        }

        $params = $request->request->all();

        // TODO: Retrieve payment from Payplug to improve confiance in "is_paid" bool

        $gatewayResponse
            ->setTransactionUuid($params['metadata']['transaction_id'])
            ->setAmount($params['amount'])
            ->setCurrencyCode($params['currency'])
            ->setRaw($params)
        ;

        if (!$params['is_paid']) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    private function resolvePaymentOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options
    ): array {
        $resolver = (new OptionsResolver())
            ->setDefault('amount', $transaction->getAmount())->setAllowedTypes('amount', ['int', 'float'])
            ->setDefault('currency', $transaction->getCurrencyCode())->setAllowedTypes('currency', ['string'])
            ->setDefault('billing', function (OptionsResolver $billingResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['billing'])) {
                    return;
                }

                $billingResolver
                    ->setDefined('title')->setAllowedTypes('title', ['string'])
                    ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                    ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                    ->setDefined('mobile_phone_number')->setAllowedTypes('mobile_phone_number', ['string'])
                    ->setDefined('email')->setAllowedTypes('email', ['string'])
                        ->setNormalizer('email', function (OptionsResolver $emailResolver, $value) {
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                throw new InvalidOptionException('The option "billing[email]" is not a valid email');
                            }

                            return $value;
                        })
                    ->setDefined('address1')->setAllowedTypes('address1', ['string'])
                    ->setDefined('postcode')->setAllowedTypes('postcode', ['string'])
                    ->setDefined('city')->setAllowedTypes('city', ['string'])
                    ->setDefined('country')->setAllowedValues('country', Countries::getCountryCodes())
                    ->setDefined('language')->setAllowedTypes('language', ['string'])
                ;
            })
            ->setDefault('shipping', function (OptionsResolver $shippingResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['shipping'])) {
                    return;
                }

                $shippingResolver
                    ->setDefined('title')->setAllowedTypes('title', ['string'])
                    ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                    ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                    ->setDefined('mobile_phone_number')->setAllowedTypes('mobile_phone_number', ['string'])
                    ->setDefined('email')->setAllowedTypes('email', ['string'])
                        ->setNormalizer('email', function (OptionsResolver $emailResolver, $value) {
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                throw new InvalidOptionException('The option "shipping[email]" is not a valid email');
                            }

                            return $value;
                        })
                    ->setDefined('address1')->setAllowedTypes('address1', ['string'])
                    ->setDefined('postcode')->setAllowedTypes('postcode', ['string'])
                    ->setDefined('city')->setAllowedTypes('city', ['string'])
                    ->setDefined('country')->setAllowedValues('country', Countries::getCountryCodes())
                    ->setDefined('language')->setAllowedTypes('language', ['string'])
                ;
            })
            ->setDefault('hosted_payment', function (OptionsResolver $hostedPaymentResolver) use ($paymentGatewayConfiguration, $transaction, $options) {
                if (!isset($options['hosted_payment']) && !in_array($paymentGatewayConfiguration->get('mode'), [self::MODE_HOSTED, self::MODE_LIGHTBOX])) {
                    return;
                }

                $hostedPaymentResolver
                    ->setDefault('return_url', $paymentGatewayConfiguration->get('return_url'))->setAllowedTypes('return_url', ['string'])
                    ->setDefault('cancel_url', $paymentGatewayConfiguration->get('return_url'))->setAllowedTypes('cancel_url', ['string'])
                    ->setDefined('sent_by')->setAllowedTypes('sent_by', ['string'])
                ;
            })
            ->setDefault('notification_url', $paymentGatewayConfiguration->get('callback_url'))->setAllowedTypes('notification_url', ['string'])
            ->setDefined('metadata')->setAllowedTypes('metadata', ['array'])
        ;

        return array_filter($resolver->resolve($options));
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'version',
                'secret_key',
                'mode',
            ]
        );
    }
}
