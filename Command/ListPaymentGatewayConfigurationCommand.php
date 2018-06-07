<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ListPaymentGatewayConfigurationCommand extends ContainerAwareCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:payment-gateway-configuration:list')
            ->setDescription('List and show all payment gateway configuration')
            ->setHelp('List and show all a payment gateway configuration')
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

        $question = new ChoiceQuestion('Please select the gateway to show', $paymentGatewayConfigurationList, 0);
        $question->setErrorMessage('%s is an invalid choice.');
        $paymentGatewayConfiguration = $paymentGatewayRepository->findOneBy([
            'alias' => $helper->ask($input, $output, $question),
        ]);

        $output->writeln('<comment>Payment gateway configuration :</comment>');

        $output->writeln([
            sprintf('<options=bold,underscore>id</>: %s', $paymentGatewayConfiguration->getId()),
            sprintf('<options=bold,underscore>alias</>: %s', $paymentGatewayConfiguration->getAlias()),
            sprintf('<options=bold,underscore>gateway_name</>: %s', $paymentGatewayConfiguration->getGatewayName()),
            sprintf('<options=bold,underscore>enabled</>: %s', $paymentGatewayConfiguration->isEnabled() ? 'true' : 'false'),
        ]);

        foreach ($paymentGatewayConfiguration->getParameters() as $parameterName => $parameterValue) {
            $output->writeln(sprintf('<options=bold,underscore>parameters[%s]</>: %s', $parameterName, $parameterValue));
        }

        return 0;
    }
}
