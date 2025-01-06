<?php

namespace IDCI\Bundle\PaymentBundle\Controller;

use IDCI\Bundle\PaymentBundle\Manager\PaymentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="idci_payment_apple_pay_payment_gateway_")
 */
class ApplePayPaymentGatewayController extends AbstractController
{
    /**
     * @var PaymentManager
     */
    private $paymentManager;

    /**
     * @var string
     */
    private $domainVerificationDirectoryPath;

    public function __construct(
        PaymentManager $paymentManager,
        string $domainVerificationDirectoryPath
    ) {
        $this->paymentManager = $paymentManager;
        $this->domainVerificationDirectoryPath = $domainVerificationDirectoryPath;
    }

    /**
     * @Route("/.well-known/apple-developer-merchantid-domain-association.txt", name="domain_verify", methods={"GET"})
     */
    public function domainVerifyAction(Request $request)
    {
        $filePath = sprintf('%s/%s', $this->domainVerificationDirectoryPath, $request->getHost());

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException(sprintf('The domain "%s" can\'t be verified.', $request->getHost()));
        }

        return new Response(file_get_contents($filePath));
    }

    /**
     * @Route("/apple-pay-payment-gateway/{configurationAlias}/session", name="create_session", methods={"POST"})
     */
    public function createSessionAction(Request $request, string $configurationAlias)
    {
        $paymentContext = $this
            ->paymentManager
            ->createPaymentContextByAlias($configurationAlias)
        ;

        $data = json_decode($request->getContent(), true);

        if (!isset($data['ValidationUrl'])) {
            return new Response(
                json_encode(['error' => 'Missing "ValidationUrl" parameter']),
                Response::HTTP_BAD_REQUEST,
                [
                    'Content-Type' => 'application/json',
                ]
            );
        }

        $sessionData = $paymentContext->getPaymentGateway()->createSession($paymentContext->getPaymentGatewayConfiguration(), $data['ValidationUrl']);

        if (null === $sessionData) {
            return new Response(
                json_encode(['error' => 'Session creation error']),
                Response::HTTP_BAD_REQUEST,
                [
                    'Content-Type' => 'application/json',
                ]
            );
        }

        return new Response(
            $sessionData,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }
}
