<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Api;

use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/transactions")
 */
class ApiTransactionController extends Controller
{
    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    public function __construct(TransactionManagerInterface $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    /**
     * @Route("/{id}")
     * @Method({"GET"})
     */
    public function show($id)
    {
        $transaction = $this->transactionManager->retrieveTransactionByUuid($id);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'amount' => $transaction->getAmount(),
            'currencyCode' => $transaction->getCurrencyCode(),
            'status' => $transaction->getStatus(),
            'createdAt' => $transaction->getCreatedAt(),
            'updatedAt' => $transaction->getUpdatedAt(),
        ]);
    }
}
