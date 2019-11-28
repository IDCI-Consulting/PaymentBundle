<?php

namespace IDCI\Bundle\PaymentBundle\Controller\Api;

use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transactions")
 */
class ApiTransactionController extends AbstractController
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
     * @Route("/{id}", methods={"GET"})
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
