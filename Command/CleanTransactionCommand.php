<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanTransactionCommand extends Command
{
    private const DEFAULT_DELAY = 'P1D';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:transaction:clean')
            ->setDescription('Clean old transaction that stay in pending or created status after a given delay')
            ->setHelp('Clean transaction that stay in pending or created status after a given delay (formatted in DateInterval format)')
            ->addArgument('delay', InputArgument::OPTIONAL, 'The given period to estimate how to delete "old" aborted transactions')
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command.
Here is an example:

# Remove all the aborted transaction created one day ago
<info>php bin/console %command.name% P1D</info>
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getApplication()->getKernel()->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $delay = $input->getArgument('delay');
        if (!$delay) {
            $delay = self::DEFAULT_DELAY;
        }

        $qb = $om->createQueryBuilder();

        $qb
            ->select('t')
            ->from(Transaction::class, 't')
            ->where('t.updatedAt < :updatedAt')
            ->andWhere('t.status = :statusCreated OR t.status = :statusPending')
            ->setParameter('updatedAt', (new \DateTime('now'))->sub(new \DateInterval($delay)))
            ->setParameter('statusCreated', PaymentStatus::STATUS_CREATED)
            ->setParameter('statusPending', PaymentStatus::STATUS_PENDING)
        ;

        $transactions = $qb->getQuery()->getResult();

        if (0 === count($transactions)) {
            $output->write(sprintf('No result found for the given delay (%s)', $delay));

            return 0;
        }

        $question = new ConfirmationQuestion(
            sprintf(
                '%s results found for the given delay (%s), do you want to delete them? [y/N]',
                count($transactions),
                $delay
            ),
            false
        );
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
