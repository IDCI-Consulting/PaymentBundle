<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use OnlinePayments\Sdk\Authentication\V1HmacAuthenticator;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\Domain as SdkDomain;
use OnlinePayments\Sdk\Merchant\MerchantClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorldlineDirectPaymentSystem extends AbstractPaymentSystem
{
    public const INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE = 'hosted_checkout_page';
    public const INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE = 'hosted_tokenization_page';

    public const ALLOWED_INTEGRATION_METHODS = [
        self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE,
        self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE,
    ];

    public function configureParameters(OptionsResolver $resolver): void
    {
        parent::configureParameters($resolver);

        $resolver
            ->setRequired('api_endpoint')->setAllowedTypes('api_endpoint', ['string'])
            ->setRequired('api_key')->setAllowedTypes('api_key', ['string'])
            ->setRequired('api_secret')->setAllowedTypes('api_secret', ['string'])
            ->setRequired('merchant_id')->setAllowedTypes('merchant_id', ['string'])
            ->setRequired('integrator')->setAllowedTypes('integrator', ['string'])
            ->setRequired('integration_method')->setAllowedValues('integration_method', self::ALLOWED_INTEGRATION_METHODS)
        ;
    }

    public function buildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $parameters['integration_method']) {
            $hostedCheckoutResponse = $this->callHostedCheckoutPage($transaction, $parameters);

            return $this->templating->render('@IDCIPayment/System/worldline/checkout.html.twig', [
                'hosted_checkout_response' => $hostedCheckoutResponse,
            ]);
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $parameters['integration_method']) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($transaction, $parameters);

            return $this->templating->render('@IDCIPayment/Gateway/worldline/tokenization.html.twig', [
                'hosted_tokenization_response' => $hostedTokenizationResponse,
                'wordline_host' => $parameters['api_endpoint'],
                'transaction' => $transaction,
            ]);
        }
    }

    protected function createMerchantClient(array $parameters): MerchantClientInterface
    {
        $communicatorConfiguration = new CommunicatorConfiguration(
            $parameters['api_key'],
            $parameters['api_secret'],
            $parameters['api_endpoint'],
            $parameters['integrator'],
            null
        );

        $authenticator = new V1HmacAuthenticator($communicatorConfiguration);
        $communicator = new Communicator($communicatorConfiguration, $authenticator);
        $client = new Client($communicator);

        return $client->merchant($parameters['merchant_id']);
    }

    protected function createOrder(Transaction $transaction): SdkDomain\Order
    {
        $amountOfMoney = new SdkDomain\AmountOfMoney();
        $amountOfMoney->setAmount($transaction->getAmount());
        $amountOfMoney->setCurrencyCode($transaction->getCurrencyCode());

        $customer = new SdkDomain\Customer();
        $customer->setMerchantCustomerId($transaction->getCustomerReference());

        $orderReferences = new SdkDomain\OrderReferences();
        $orderReferences->setMerchantReference($transaction->getId());
        // $orderReferences->setOperationGroupReference($transaction->getId());

        $order = new SdkDomain\Order();
        $order->setAmountOfMoney($amountOfMoney);
        $order->setCustomer($customer);
        $order->setReferences($orderReferences);

        return $order;
    }

    protected function callHostedCheckoutPage(Transaction $transaction, array $parameters): SdkDomain\CreateHostedCheckoutResponse
    {
        $merchantClient = $this->createMerchantClient($parameters);

        $createHostedCheckoutRequest = new SdkDomain\CreateHostedCheckoutRequest();
        $createHostedCheckoutRequest->setOrder($this->createOrder($transaction));

        $hostedCheckoutSpecificInput = new SdkDomain\HostedCheckoutSpecificInput();
        $hostedCheckoutSpecificInput->setReturnUrl($parameters['client_return_url']);

        if (isset($parameters['locale']) && null !== $parameters['locale']) {
            $hostedCheckoutSpecificInput->setLocale($parameters['locale']);
        }

        $createHostedCheckoutRequest->setHostedCheckoutSpecificInput($hostedCheckoutSpecificInput);

        $feedbacks = new SdkDomain\Feedbacks();
        $feedbacks->setWebhooksUrls([$parameters['system_notification_url']]);
        $createHostedCheckoutRequest->setFeedbacks($feedbacks);

        $hostedCheckoutResponse = $merchantClient->hostedCheckout()->createHostedCheckout($createHostedCheckoutRequest);

        /*
        $transaction
            ->setStatus(PaymentStatus::STATUS_PENDING)
            ->addMetadata('hosted_checkout_id', $hostedCheckoutResponse->getHostedCheckoutId())
            ->addMetadata('return_mac', $hostedCheckoutResponse->getReturnMac())
            ->addMetadata('redirect_url', $hostedCheckoutResponse->getRedirectUrl())
        ;
        $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);
        */

        return $hostedCheckoutResponse;
    }
}