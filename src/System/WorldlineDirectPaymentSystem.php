<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Context\TransactionStatus;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
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

    public const PAYMENT_STATUS_CREATED = 'CREATED';
    public const PAYMENT_STATUS_CANCELLED = 'CANCELLED';
    public const PAYMENT_STATUS_REJECTED = 'REJECTED';
    public const PAYMENT_STATUS_REJECTED_CAPTURE = 'REJECTED_CAPTURE';
    public const PAYMENT_STATUS_REDIRECTED = 'REDIRECTED';
    public const PAYMENT_STATUS_PENDING_CAPTURE = 'PENDING_CAPTURE';
    public const PAYMENT_STATUS_AUTHORIZATION_REQUESTED = 'AUTHORIZATION_REQUESTED';
    public const PAYMENT_STATUS_CAPTURE_REQUESTED = 'CAPTURE_REQUESTED';
    public const PAYMENT_STATUS_CAPTURED = 'CAPTURED';
    public const PAYMENT_STATUS_REFUND_REQUESTED = 'REFUND_REQUESTED';
    public const PAYMENT_STATUS_REFUNDED = 'REFUNDED';

    public const HOSTED_CHECKOUT_STATUS_PAYMENT_CREATED = 'PAYMENT_CREATED';
    public const HOSTED_CHECKOUT_STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const HOSTED_CHECKOUT_STATUS_CANCELLED_BY_CONSUMER = 'CANCELLED_BY_CONSUMER';

    public const HOSTED_CHECKOUT_STATUS_MAP = [
        self::HOSTED_CHECKOUT_STATUS_PAYMENT_CREATED => TransactionStatus::STATUS_PENDING,
        self::HOSTED_CHECKOUT_STATUS_IN_PROGRESS => TransactionStatus::STATUS_CREATED,
        self::HOSTED_CHECKOUT_STATUS_CANCELLED_BY_CONSUMER => TransactionStatus::STATUS_CANCELED,
    ];

    public const PAYMENT_STATUS_MAP = [
        self::PAYMENT_STATUS_CREATED => TransactionStatus::STATUS_CREATED,
        self::PAYMENT_STATUS_CANCELLED => TransactionStatus::STATUS_CANCELED,
        self::PAYMENT_STATUS_REJECTED => TransactionStatus::STATUS_FAILED,
        self::PAYMENT_STATUS_REJECTED_CAPTURE => TransactionStatus::STATUS_FAILED,
        self::PAYMENT_STATUS_REDIRECTED => TransactionStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_PENDING_CAPTURE => TransactionStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_AUTHORIZATION_REQUESTED => TransactionStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_CAPTURE_REQUESTED => TransactionStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_CAPTURED => TransactionStatus::STATUS_APPROVED,
        self::PAYMENT_STATUS_REFUND_REQUESTED => TransactionStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_REFUNDED => TransactionStatus::STATUS_REFUNDED,
    ];

    public const HOSTED_CHECKOUT_ID_QUERY_PARAMETER = 'hostedCheckoutId';
    public const HOSTED_CHECKOUT_RETURNMAC_PARAMETER = 'RETURNMAC';

    public const HOSTED_TOKENIZATION_ID_QUERY_PARAMETER = 'hosted_tokenization_id';
    public const HOSTED_TOKENIZATION_RETURNMAC_PARAMETER = 'RETURNMAC';
    public const HOSTED_TOKENIZATION_PAYMENT_ID_PARAMETER = 'paymentId';

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
            ->setDefault('locale', 'en')->setAllowedTypes('locale', ['string'])
            ->setDefault('hosted_tokenization_template_file', null)->setAllowedTypes('hosted_tokenization_template_file', ['null', 'string'])
        ;
    }

    protected function doRetrieveTransaction(Request $request): Transaction
    {
        if (Request::METHOD_POST === $request->getMethod()) {
            $payload = json_decode($request->getContent(), true);

            if (!isset($payload['payment']['paymentOutput']['references']['merchantReference'])) {
                throw new \Exception('Invalid payload');
            }

            $transactionId = $payload['payment']['paymentOutput']['references']['merchantReference'];

            return $this->transactionManager->retrieveTransactionById($transactionId);
        }
    }

    protected function doProcessInitialTransaction(array $parameters): ProcessedTransactionResult
    {
        $transaction = $parameters['transaction'];
        $this->createMerchantClient($parameters);

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $parameters['integration_method']) {
            $hostedCheckoutResponse = $this->callHostedCheckoutPage($transaction, $parameters);
            $hostedCheckoutStatus = $this->merchantClient->hostedCheckout()->getHostedCheckout($hostedCheckoutResponse->getHostedCheckoutId());

            $transaction
                ->setStatus(self::HOSTED_CHECKOUT_STATUS_MAP[$hostedCheckoutStatus->getStatus()])
                ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($hostedCheckoutResponse))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState($hostedCheckoutStatus->getStatus())
                    ->addMetadata('return_mac', $hostedCheckoutResponse->getReturnMac())
                    ->addMetadata('hosted_checkout_id', $hostedCheckoutResponse->getHostedCheckoutId())
                    ->addMetadata('redirect_url', $hostedCheckoutResponse->getRedirectUrl())
                )
            ;
            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            return new ProcessedTransactionResult(
                ProcessedTransactionResult::TYPE_HTML,
                $this->templating->render('@IDCIPayment/System/worldline/checkout.html.twig', [
                    'hosted_checkout_response' => $hostedCheckoutResponse,
                ])
            );
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $parameters['integration_method']) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($transaction, $parameters);

            $transaction
                ->setStatus(TransactionStatus::STATUS_CREATED)
                ->addMetadata('hosted_tokenization_id', $hostedTokenizationResponse->getHostedTokenizationId())
                ->addMetadata('hosted_tokenization_url', $hostedTokenizationResponse->getHostedTokenizationUrl())
            ;
            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            return new ProcessedTransactionResult(
                ProcessedTransactionResult::TYPE_HTML,
                $this->templating->render('@IDCIPayment/System/worldline/tokenization.html.twig', [
                    'hosted_tokenization_response' => $hostedTokenizationResponse,
                    'wordline_host' => $parameters['api_endpoint'],
                    'transaction' => $transaction,
                ])
            );
        }
    }

    protected function doProcessReturnClientTransaction(array $parameters): ?ProcessedTransactionResult
    {
        $transaction = $parameters['transaction'];
        $request = $parameters['request'];
        $this->createMerchantClient($parameters);

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $parameters['integration_method']) {
            $relatedTransactionNotifications = [];

            // Retrieve TransactionNotifications with RETURNMAC query parameter
            foreach ($transaction->getNotifications() as $notification) {
                if ($request->query->get(self::HOSTED_CHECKOUT_RETURNMAC_PARAMETER) === $notification->getMetadata('return_mac')) {
                    $relatedTransactionNotifications[] = $notification;
                }
            }

            if (empty($relatedTransactionNotifications)) {
                throw new \UnexpectedValueException(sprintf(
                    'No TransactionNotification found with the given RETURNMAC: %s',
                    $request->query->get(self::HOSTED_CHECKOUT_RETURNMAC_PARAMETER)
                ));
            }

            if (1 === count($relatedTransactionNotifications)
                && 'IN_PROGRESS' === $relatedTransactionNotifications[0]->getState()
            ) {
                $hostedCheckoutStatus = $this->merchantClient->hostedCheckout()->getHostedCheckout($request->query->get(self::HOSTED_CHECKOUT_ID_QUERY_PARAMETER));
                $paymentDetails = $this->merchantClient->payments()->getPaymentDetails($hostedCheckoutStatus->getCreatedPaymentOutput()->getPayment()->getId());

                $transaction
                    ->setStatus(self::PAYMENT_STATUS_MAP[$paymentDetails->getStatus()])
                    ->addNotification(
                        (new TransactionNotification())
                            ->setMessage(json_encode($hostedCheckoutStatus))
                            ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                            ->setState($paymentDetails->getStatus())
                            ->addMetadata('payment_id', $paymentDetails->getId())
                            ->addMetadata('payment_details', json_encode($paymentDetails))
                    )
                ;

                $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);
            }

            return null;
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $parameters['integration_method']) {
            if ($request->query->has(self::HOSTED_TOKENIZATION_ID_QUERY_PARAMETER)) {
                if ($request->query->get(self::HOSTED_TOKENIZATION_ID_QUERY_PARAMETER) !== $transaction->getMetadata('hosted_tokenization_id')) {
                    throw new \UnexpectedValueException('The given parameter "hosted_tokenization_id" does\'t match with the transaction');
                }

                $createPaymentResponse = $this->sendCreatePaymentRequest($parameters);
                $paymentDetails = $this->merchantClient->payments()->getPaymentDetails($createPaymentResponse->getPayment()->getId());

                if (null !== $createPaymentResponse->getMerchantAction()
                    && 'REDIRECT' === $createPaymentResponse->getMerchantAction()->getActionType()
                ) {
                    $transaction
                        ->setStatus(self::PAYMENT_STATUS_MAP[$paymentDetails->getStatus()])
                        ->addNotification(
                            (new TransactionNotification())
                                ->setMessage(json_encode($createPaymentResponse))
                                ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                                ->setState($paymentDetails->getStatus())
                                ->addMetadata('return_mac', $createPaymentResponse->getMerchantAction()->getRedirectData()->getReturnMac())
                                ->addMetadata('redirect_url', $createPaymentResponse->getMerchantAction()->getRedirectData()->getRedirectURL())
                                ->addMetadata('payment_id', $paymentDetails->getId())
                                ->addMetadata('payment_details', json_encode($paymentDetails))
                        )
                    ;
                    $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

                    return new ProcessedTransactionResult(
                        ProcessedTransactionResult::TYPE_REDIRECTION,
                        $createPaymentResponse->getMerchantAction()->getRedirectData()->getRedirectURL(),
                    );
                }

                $transaction
                    ->setStatus(self::PAYMENT_STATUS_MAP[$paymentDetails->getStatus()])
                    ->addNotification(
                        (new TransactionNotification())
                            ->setMessage(json_encode($createPaymentResponse))
                            ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                            ->setState($paymentDetails->getStatus())
                            ->addMetadata('payment_id', $paymentDetails->getId())
                            ->addMetadata('payment_details', json_encode($paymentDetails))
                    )
                ;
                $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

                return null;
            }

            // Retrieve TransactionNotifications with RETURNMAC query parameter
            foreach ($transaction->getNotifications() as $notification) {
                if ($request->query->get(self::HOSTED_TOKENIZATION_RETURNMAC_PARAMETER) === $notification->getMetadata('return_mac')) {
                    $relatedTransactionNotifications[] = $notification;
                }
            }

            if (empty($relatedTransactionNotifications)) {
                throw new \UnexpectedValueException(sprintf(
                    'No TransactionNotification found with the given RETURNMAC: %s',
                    $request->query->get(self::HOSTED_TOKENIZATION_RETURNMAC_PARAMETER)
                ));
            }

            if (1 === count($relatedTransactionNotifications)
                && 'REDIRECTED' === $relatedTransactionNotifications[0]->getState()
            ) {
                $paymentDetails = $this->merchantClient->payments()->getPaymentDetails($request->query->get(self::HOSTED_TOKENIZATION_PAYMENT_ID_PARAMETER));

                $transaction
                    ->setStatus(self::PAYMENT_STATUS_MAP[$paymentDetails->getStatus()])
                    ->addNotification(
                        (new TransactionNotification())
                            ->setMessage(json_encode($paymentDetails))
                            ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                            ->setState($paymentDetails->getStatus())
                            ->addMetadata('payment_id', $paymentDetails->getId())
                    )
                ;

                $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);
            }

            return null;
        }

        throw new \LogicException(sprintf('Wrong "integration_method" parameter given: "%s"', $parameters['integration_method']));
    }

    protected function doHandleNotification(array $parameters): void
    {
        $transaction = $parameters['transaction'];
        $request = $parameters['request'];
        $payload = json_decode($request->getContent(), true);

        $transaction
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage($request->getContent())
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState($payload['payment']['status'])
            )
        ;
        $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        $this->createMerchantClient($parameters);

        if (self::PAYMENT_STATUS_PENDING_CAPTURE === $payload['payment']['status']
            && in_array($transaction->getStatus(), [TransactionStatus::STATUS_CREATED, TransactionStatus::STATUS_PENDING])
        ) {
            $capturePaymentRequest = new SdkDomain\CapturePaymentRequest();
            $capturePaymentRequest->setAmount($payload['payment']['paymentOutput']['acquiredAmount']['amount']);
            $capturePaymentRequest->setIsFinal(true);

            $paymentReferences = new SdkDomain\PaymentReferences();
            $paymentReferences->setOperationGroupReference($transaction->getId());
            $capturePaymentRequest->setReferences($paymentReferences);


            $transaction
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($capturePaymentRequest->toObject()))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_TRANSMIT)
                        ->setState($transaction->getLastNotification()->getState())
                    )
            ;
            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            $captureResponse = $this->merchantClient->payments()->capturePayment($payload['payment']['id'], $capturePaymentRequest);

            $transaction
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($captureResponse->toObject()))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                        ->setState($captureResponse->getStatus())
                )
            ;
            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);
        }

        $paymentDetails = $this->merchantClient->payments()->getPaymentDetails($payload['payment']['id']);
        $transaction
            ->setStatus(self::PAYMENT_STATUS_MAP[$paymentDetails->getStatus()])
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($paymentDetails))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState($paymentDetails->getStatus())
                    ->addMetadata('payment_id', $paymentDetails->getId())
            )
        ;
        $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

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

        $transaction
            ->setStatus(TransactionStatus::STATUS_CREATED)
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($createHostedCheckoutRequest))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_TRANSMIT)
                    ->setState('INITIALIZE')
            )
        ;

        return $this->merchantClient->hostedCheckout()->createHostedCheckout($createHostedCheckoutRequest);
    }

    protected function callHostedTokenizationPage(Transaction $transaction, array $parameters): SdkDomain\CreateHostedTokenizationResponse
    {
        $createHostedTokenizationRequest = new SdkDomain\CreateHostedTokenizationRequest();
        if (null !== $parameters['hosted_tokenization_template_file']) {
            $createHostedTokenizationRequest->setVariant($parameters['hosted_tokenization_template_file']);
        }

        $this->createMerchantClient($parameters);

        $transaction
            ->setStatus(TransactionStatus::STATUS_CREATED)
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($createHostedTokenizationRequest))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_TRANSMIT)
                    ->setState('INITIALIZE')
            )
        ;

        return $this->merchantClient->hostedTokenization()->createHostedTokenization($createHostedTokenizationRequest);
    }

    public function sendCreatePaymentRequest(array $parameters): SdkDomain\CreatePaymentResponse
    {
        $createPaymentRequest = new SdkDomain\CreatePaymentRequest();
        $createPaymentRequest->setHostedTokenizationId($parameters['transaction']->getMetadata('hosted_tokenization_id'));

        $redirectionData = new SdkDomain\RedirectionData();
        $redirectionData->setReturnUrl($parameters['client_return_url']);

        $threeDSecure = new SdkDomain\ThreeDSecure();
        $threeDSecure->setRedirectionData($redirectionData);
        $threeDSecure->setSkipAuthentication(false);

        $cardPaymentMethodSpecificInput = new SdkDomain\CardPaymentMethodSpecificInput();
        $cardPaymentMethodSpecificInput->setThreeDSecure($threeDSecure);

        $createPaymentRequest->setCardPaymentMethodSpecificInput($cardPaymentMethodSpecificInput);

        $order = new SdkDomain\Order();

        $orderReferences = new SdkDomain\OrderReferences();
        $orderReferences->setMerchantReference($parameters['transaction']->getId());
        $order->setReferences($orderReferences);

        $browserData = new SdkDomain\BrowserData();
        $browserData->setColorDepth(24);
        $browserData->setJavaScriptEnabled(false);
        $browserData->setScreenHeight('1080');
        $browserData->setScreenWidth('1920');

        $customerDevice = new SdkDomain\CustomerDevice();
        $customerDevice->setAcceptHeader(
            'text/html,application/xhtml+xml,application/xmlq=0.9,image/webp,image/apng,*/*q=0.8,application/signed-exchangev=b3'
        );

        $customerDevice->setLocale($parameters['locale']);
        // $customerDevice->setTimezoneOffsetUtcMinutes("-180");
        $customerDevice->setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0 Win64 x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36'
        );
        $customerDevice->setBrowserData($browserData);

        $customer = new SdkDomain\Customer();
        $customer->setMerchantCustomerId($parameters['transaction']->getCustomerReference());
        $customer->setDevice($customerDevice);
        $order->setCustomer($customer);

        $amountOfMoney = new SdkDomain\AmountOfMoney();
        $amountOfMoney->setAmount($parameters['transaction']->getAmount());
        $amountOfMoney->setCurrencyCode($parameters['transaction']->getCurrencyCode());
        $order->setAmountOfMoney($amountOfMoney);

        $createPaymentRequest->setOrder($order);

        $feedbacks = new SdkDomain\Feedbacks();
        $feedbacks->setWebhooksUrls([$parameters['system_notification_url']]);
        $createPaymentRequest->setFeedbacks($feedbacks);

        $this->createMerchantClient($parameters);

        return $this->merchantClient->payments()->createPayment($createPaymentRequest);
    }
}