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
        return new JsonResponse($this->transactionManager->retrieveTransactionByUuid($id)->toArray());
    }
}
