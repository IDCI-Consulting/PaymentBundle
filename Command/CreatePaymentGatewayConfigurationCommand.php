<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\UnsupportedPaymentGatewayParametersConfiguration;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistry;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePaymentGatewayConfigurationCommand extends ContainerAwareCommand
{
    private $paymentGatewayRegistry;

    public function __construct(PaymentGatewayRegistry $paymentGatewayRegistry)
    {
        $this->paymentGatewayRegistry = $paymentGatewayRegistry;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:payment-gateway-configuration:create')
            ->setDescription('Create a new payment gateway configuration')
            ->setHelp('Create a new payment gateway configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $paymentGatewayList = $this->paymentGatewayRegistry->getAll();

        $question = new ChoiceQuestion('Please select the gateway', array_keys($this->paymentGatewayRegistry->getAll()), 0);
        $question->setErrorMessage('%s is an invalid choice.');
        $gatewayName = $helper->ask($input, $output, $question);

        $question = new Question('What alias do you want to give ?');
        $alias = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Would you want to set it activated? [Y/n]', true);
        $enabled = $helper->ask($input, $output, $question);

        $paymentGatewayFQCN = get_class($paymentGatewayList[$gatewayName]);

        $parameters = [];

        foreach ($paymentGatewayFQCN::getParameterNames() as $parameterName) {
            $question = new Question(sprintf('%s:', $parameterName));

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

        $output->writeln(sprintf('<info>The %s configuration has been succesfully created</info>', $paymentGatewayConfiguration));

        return 0;
    }
}
