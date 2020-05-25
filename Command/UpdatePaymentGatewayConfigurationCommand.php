<?php

namespace IDCI\Bundle\PaymentBundle\Command;

use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class UpdatePaymentGatewayConfigurationCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:payment-gateway-configuration:update')
            ->setDescription('Update a payment gateway configuration')
            ->setHelp('Update a payment gateway configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getApplication()->getKernel()->getContainer()->get('doctrine')->getManager();
        $helper = $this->getHelper('question');

        $paymentGatewayRepository = $om->getRepository(PaymentGatewayConfiguration::class);
        $paymentGatewayConfigurationList = $paymentGatewayRepository->findAll();

        if (count($paymentGatewayConfigurationList) < 1) {
            throw new NoPaymentGatewayConfigurationFoundException(
                'You need to have at least one payment gateway configuration'
            );
        }

        $question = new ChoiceQuestion('Please select the gateway', $paymentGatewayConfigurationList, 0);
        $question->setErrorMessage('%s is an invalid choice.');
        $paymentGatewayConfiguration = $paymentGatewayRepository->findOneBy([
            'alias' => $helper->ask($input, $output, $question),
        ]);

        $question = new Question(
            sprintf('What alias do you want to give ? [%s]', $paymentGatewayConfiguration->getAlias()),
            $paymentGatewayConfiguration->getAlias()
        );

        $paymentGatewayConfiguration->setAlias($helper->ask($input, $output, $question));

        $labelCurrentActivated = $paymentGatewayConfiguration->isEnabled() ? '[Y/n]' : '[y/N]';
        $question = new ConfirmationQuestion(
            'Would you want to set it activated? [Y/n]',
            $paymentGatewayConfiguration->isEnabled()
        );
        $paymentGatewayConfiguration->setEnabled($helper->ask($input, $output, $question));

        $parameters = [];

        foreach ($paymentGatewayConfiguration->getParameters() as $parameterName => $parameterValue) {
            $question = new Question(sprintf('%s [%s]:', $parameterName, $parameterValue), $parameterValue);

            $parameters[$parameterName] = $helper->ask($input, $output, $question);
        }

        $paymentGatewayConfiguration->setParameters($parameters);

        $om->persist($paymentGatewayConfiguration);
        $om->flush();

        $output->writeln(
            sprintf('<info>The %s configuration has been succesfully updated</info>', $paymentGatewayConfiguration)
        );

        return 0;
    }
}
