<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanTransactionCommand extends ContainerAwareCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:transaction:clean')
            ->setDescription('Clean old aborted transaction')
            ->setHelp('Clean old aborted transaction')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $qb = $om->createQueryBuilder();

        $qb
            ->select('t')
            ->from(Transaction::class, 't')
            ->where('t.updatedAt < :updatedAt')
            ->andWhere('t.status = :statusCreated OR t.status = :statusPending')
            ->setParameter('updatedAt', (new \DateTime('now'))->sub(new \DateInterval('P1D')))
            ->setParameter('statusCreated', PaymentStatus::STATUS_CREATED)
            ->setParameter('statusPending', PaymentStatus::STATUS_PENDING)
        ;

        $transactions = $qb->getQuery()->getResult();

        $question = new ConfirmationQuestion(sprintf('%s results found, do you want to delete them? [y/N]', count($transactions)), false);
        $delete = $helper->ask($input, $output, $question);

        if (false === $delete) {
            return 0;
        }

        foreach ($transactions as $transaction) {
            $om->remove($transaction);
        }

        $om->flush();
    }
}
