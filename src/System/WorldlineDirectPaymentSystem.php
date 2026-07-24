<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Context\TransactionStatus;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Model\TransactionNotification;
use OnlinePayments\Sdk\Authentication\V1HmacAuthenticator;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\Domain as SdkDomain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorldlineDirectPaymentSystem extends AbstractPaymentSystem
{
    public const INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE = 'hosted_checkout_page';
    public const INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE = 'hosted_tokenization_page';

    public const ALLOWED_INTEGRATION_METHODS = [
        self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE,
        self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE,
    ];

    public const HOSTED_CHECKOUT_STATUS_PAYMENT_CREATED = 'PAYMENT_CREATED';
    public const HOSTED_CHECKOUT_STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const HOSTED_CHECKOUT_STATUS_CANCELLED_BY_CONSUMER = 'CANCELLED_BY_CONSUMER';

    public const HOSTED_CHECKOUT_STATUS_MAP = [
        self::HOSTED_CHECKOUT_STATUS_PAYMENT_CREATED => TransactionStatus::STATUS_PENDING,
        self::HOSTED_CHECKOUT_STATUS_IN_PROGRESS => TransactionStatus::STATUS_CREATED,
        self::HOSTED_CHECKOUT_STATUS_CANCELLED_BY_CONSUMER => TransactionStatus::STATUS_CANCELED,
    ];

    protected $merchantClient = null;

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
            ->setDefault('locale', null)->setAllowedTypes('locale', ['null', 'string'])
        ;
    }

    public function doBuildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        $this->createMerchantClient($parameters);

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $parameters['integration_method']) {
            $hostedCheckoutResponse = $this->callHostedCheckoutPage($transaction, $parameters);
            $hostedCheckoutStatus = $this->merchantClient->hostedCheckout()->getHostedCheckout($hostedCheckoutResponse->getHostedCheckoutId());

            $transaction
                ->setStatus(self::HOSTED_CHECKOUT_STATUS_MAP[$hostedCheckoutStatus->getStatus()])
                ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($hostedCheckoutResponse))
                    ->setState($hostedCheckoutStatus->getStatus())
                    ->addMetadata('return_mac', $hostedCheckoutResponse->getReturnMac())
                    ->addMetadata('hosted_checkout_id', $hostedCheckoutResponse->getHostedCheckoutId())
                    ->addMetadata('redirect_url', $hostedCheckoutResponse->getRedirectUrl())
            );
            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            return $this->templating->render('@IDCIPayment/System/worldline/checkout.html.twig', [
                'hosted_checkout_response' => $hostedCheckoutResponse,
            ]);
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $parameters['integration_method']) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($transaction, $parameters);

            return $this->templating->render('@IDCIPayment/System/worldline/tokenization.html.twig', [
                'hosted_tokenization_response' => $hostedTokenizationResponse,
                'wordline_host' => $parameters['api_endpoint'],
                'transaction' => $transaction,
            ]);
        }
    }

    public function doBuildFinalHTMLView(Transaction $transaction, Request $request, array $parameters): ?string
    {
        $this->createMerchantClient($parameters);

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $parameters['integration_method']) {
            $relatedTransactionNotifications = [];

            // Retrieve TransactionNotifications with RETURNMAC query parameter
            foreach ($transaction->getNotifications() as $notification) {
                if ($request->query->get('RETURNMAC') === $notification->getMetadata('return_mac')) {
                    $relatedTransactionNotifications[] = $notification;
                }
            }

            if (empty($relatedTransactionNotifications)) {
                throw new \UnexpectedValueException(sprintf('No TransactionNotification found with the given RETURNMAC: %s', $request->query->get('RETURNMAC')));
            }

            if (1 === count($relatedTransactionNotifications)
                && 'IN_PROGRESS' === $relatedTransactionNotifications[0]->getState()
            ) {
                $hostedCheckoutStatus = $this->merchantClient->hostedCheckout()->getHostedCheckout($request->query->get('hostedCheckoutId'));

                $transaction
                    ->setStatus(self::HOSTED_CHECKOUT_STATUS_MAP[$hostedCheckoutStatus->getStatus()])
                    ->addNotification(
                    (new TransactionNotification())
                        ->setMessage($relatedTransactionNotifications[0]->getMessage())
                        ->setState($hostedCheckoutStatus->getStatus())
                        ->setMetadata($relatedTransactionNotifications[0]->getMetadata())
                );
                $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);
            }

            return null;
        }

        return 'WorldlineDirectPaymentSystem::doBuildFinalHTMLView';
    }

    public function doHandleNotification(Transaction $transaction, Request $request, array $parameters): void
    {

    }

    protected function createMerchantClient(array $parameters): void
    {
        if (null !== $this->merchantClient) {
            return;
        }

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

        $this->merchantClient = $client->merchant($parameters['merchant_id']);
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
        $createHostedCheckoutRequest = new SdkDomain\CreateHostedCheckoutRequest();
        $createHostedCheckoutRequest->setOrder($this->createOrder($transaction));

        $hostedCheckoutSpecificInput = new SdkDomain\HostedCheckoutSpecificInput();
        $hostedCheckoutSpecificInput->setReturnUrl($parameters['client_return_url']);

        if (null !== $parameters['locale']) {
            $hostedCheckoutSpecificInput->setLocale($parameters['locale']);
        }

        $createHostedCheckoutRequest->setHostedCheckoutSpecificInput($hostedCheckoutSpecificInput);

        $feedbacks = new SdkDomain\Feedbacks();
        $feedbacks->setWebhooksUrls([$parameters['system_notification_url']]);
        $createHostedCheckoutRequest->setFeedbacks($feedbacks);

        $this->createMerchantClient($parameters);

        return $this->merchantClient->hostedCheckout()->createHostedCheckout($createHostedCheckoutRequest);
    }
}