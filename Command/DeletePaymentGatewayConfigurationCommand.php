<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeletePaymentGatewayConfigurationCommand extends ContainerAwareCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:payment-gateway-configuration:delete')
            ->setDescription('Delete permanantly a payment gateway configuration')
            ->setHelp('Delete permanantly a payment gateway configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $paymentGatewayRepository = $om->getRepository(PaymentGatewayConfiguration::class);
        $paymentGatewayConfigurationList = $paymentGatewayRepository->findAll();

        if (count($paymentGatewayConfigurationList) < 1) {
            throw new NoPaymentGatewayConfigurationFoundException();
        }

        $question = new ChoiceQuestion(
            'Please select the gateway to delete permanantly',
            $paymentGatewayConfigurationList,
            0
        );
        $question->setErrorMessage('%s is an invalid choice.');
        $paymentGatewayConfiguration = $paymentGatewayRepository->findOneBy([
            'alias' => $helper->ask($input, $output, $question),
        ]);

        $question = new ConfirmationQuestion('Are you sure? [y/N]', false);
        if ($helper->ask($input, $output, $question)) {
            $om->remove($paymentGatewayConfiguration);
            $om->flush();

            $output->writeln(sprintf('<info>The %s configuration has been succesfully deleted</info>', $paymentGatewayConfiguration));
        }

        return 0;
    }
}
