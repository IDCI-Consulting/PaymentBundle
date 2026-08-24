<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePaymentGatewayConfigurationCommand extends Command
{
    private $paymentGatewayRegistry;

    public function __construct(PaymentSystemRegistry $paymentSystemRegistry)
    {
        $this->paymentSystemRegistry = $paymentSystemRegistry;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('payment:gateway-configuration:create')
            ->setDescription('Create a new payment gateway configuration')
            ->setHelp('Create a new payment gateway configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $om = $this->getApplication()->getKernel()->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $paymentSystemList = $this->paymentSystemRegistry->getAll();

        $question = new ChoiceQuestion(
            'Please select the payment system',
            array_keys($this->paymentSystemList),
            0
        );
        $question->setErrorMessage('%s is an invalid choice.');
        $systemAlias = $helper->ask($input, $output, $question);

        $question = new Question('What alias do you want to give ?');
        $alias = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Would you want to set it activated? [Y/n]', true);
        $enabled = $helper->ask($input, $output, $question);

        $paymentSystemFQCN = get_class($paymentSystemList[$systemAlias]);

        $parameters = [];

        foreach ($paymentSystemFQCN::getParameterNames() as $parameterName) {
            $question = new Question(sprintf('%s: ', $parameterName));

            $parameters[$parameterName] = $helper->ask($input, $output, $question);
        }

        $paymentGatewayConfiguration = (new PaymentGatewayConfiguration())
            ->setAlias($alias)
            ->setGatewayName($gatewayName)
            ->setEnabled($enabled)
            ->setParameters($parameters)
        ;

        $om->persist($paymentGatewayConfiguration);
        $om->flush();

        $output->writeln(
            sprintf('<info>The %s configuration has been succesfully created</info>', $paymentGatewayConfiguration)
        );

        return Command::SUCCESS;
    }
}
