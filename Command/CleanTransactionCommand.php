<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanTransactionCommand extends ContainerAwareCommand
{
    private const DEFAULT_HOUR_DELAY = 24;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:transaction:clean')
            ->setDescription('Clean old transaction that stay in pending or created status after a given delay (in hours)')
            ->setHelp('Clean transaction that stay in pending or created status after a given delay (in hours)')
            ->addArgument('hours', InputArgument::OPTIONAL, 'The given period to estimate how to delete "old" aborted transactions')
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command.
Here is an example:

# Remove all the aborted transaction created 24 hours ago
<info>php bin/console %command.name% 24</info>
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $hours = $input->getArgument('hours');
        if (!$hours) {
            $hours = self::DEFAULT_HOUR_DELAY;
        }

        $qb = $om->createQueryBuilder();

        $qb
            ->select('t')
            ->from(Transaction::class, 't')
            ->where('t.updatedAt < :updatedAt')
            ->andWhere('t.status = :statusCreated OR t.status = :statusPending')
            ->setParameter('updatedAt', (new \DateTime('now'))->sub(new \DateInterval(sprintf('PT%sH', $hours))))
            ->setParameter('statusCreated', PaymentStatus::STATUS_CREATED)
            ->setParameter('statusPending', PaymentStatus::STATUS_PENDING)
        ;

        $transactions = $qb->getQuery()->getResult();

        if (0 === count($transactions)) {
            $output->write(sprintf('No result found for the last %s hours', $hours));

            return 0;
        }

        $question = new ConfirmationQuestion(
            sprintf(
                '%s results found for the last %s hours, do you want to delete them? [y/N]',
                count($transactions),
                $hours
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
