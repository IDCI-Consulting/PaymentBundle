<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MercanetAtosSipsSealPaymentGateway extends AbstractAtosSipsSealPaymentGateway
{
    /**
     * @var string
     */
    private $serverUrl;

    public function __construct(
        ObjectManager $om,
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        string $serverUrl
    ) {
        parent::__construct($om, $templating, $router);

        $this->serverUrl = $serverUrl;
    }

    protected function getServerUrl(): string
    {
        return $this->serverUrl;
    }
}
