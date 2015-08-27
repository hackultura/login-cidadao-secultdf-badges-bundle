<?php

namespace PROCERGS\LoginCidadao\BadgesBundle\Event;

use PROCERGS\LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use PROCERGS\LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use PROCERGS\LoginCidadao\BadgesControlBundle\Event\ListBearersEvent;
use PROCERGS\LoginCidadao\BadgesBundle\Model\Badge;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;
use PROCERGS\LoginCidadao\BadgesControlBundle\Model\BadgeInterface;

class BadgesSubscriber extends AbstractBadgesEventSubscriber
{
    /** @var TranslatorInterface */
    protected $translator;

    /** @var EntityManager */
    protected $em;

    public function __construct(TranslatorInterface $translator,
                                EntityManager $em)
    {
        $this->translator = $translator;
        $this->em         = $em;

        $namespace = 'login-cidadao';
        $this->setName($namespace);

        $this->registerBadge('has_cpf',
            $translator->trans("$namespace.has_cpf.description", array(),
                'badges'), array('counter' => 'countHasCpf'));
        $this->registerBadge('valid_email',
                             $translator->trans("$namespace.valid_email.description", array(),
                                                'badges'), array('counter' => 'countValidEmail'));
        $this->registerBadge('is_agent_public',
                             $translator->trans("$namespace.is_agent_public.description",
                                                array(), 'badges'),
                                                array('counter' => 'countIsAgentPublic'));
    }

    public function onBadgeEvaluate(EvaluateBadgesEvent $event)
    {
        $this->checkCpf($event);
        $this->checkEmail($event);
        $this->checkAgentPublic($event);
    }

    public function onListBearers(ListBearersEvent $event)
    {
        $filterBadge = $event->getBadge();
        if ($filterBadge instanceof BadgeInterface) {
            $countMethod = $this->badges[$filterBadge->getName()]['counter'];
            $count       = $this->{$countMethod}($filterBadge->getData());

            $event->setCount($filterBadge, $count);
        } else {
            foreach ($this->badges as $name => $badge) {
                $countMethod = $badge['counter'];
                $count       = $this->{$countMethod}();
                $badge       = new Badge($this->getName(), $name);

                $event->setCount($badge, $count);
            }
        }
    }

    protected function checkCpf(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if (is_numeric($person->getCpf())) {
            $event->registerBadge($this->getBadge('has_cpf', true));
        }
    }

    protected function checkAgentPublic(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if (is_numeric($person->getCpf()) && is_null($person->getAgentPublic())) {
            $event->registerBadge($this->getBadge('is_agent_public', true));
        }
        # $event->registerBadge($this->getBadge('is_agent_public', true));
    }

    protected function checkEmail(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getEmailConfirmedAt() instanceof \DateTime && is_null($person->getConfirmationToken())) {
            $event->registerBadge($this->getBadge('valid_email', true));
        }
    }

    protected function getBadge($name, $data)
    {
        if (array_key_exists($name, $this->getAvailableBadges())) {
            return new Badge($this->getName(), $name, $data);
        } else {
            throw new Exception("Badge $name not found in namespace {$this->getName()}.");
        }
    }

    protected function countHasCpf()
    {
        return $this->em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->andWhere('p.cpf IS NOT NULL')
                ->getQuery()->getSingleScalarResult();
    }

    protected function countIsAgentPublic()
    {
        return $this->em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person')
            ->createQueryBuilder('p')
            ->select('p.agentPublic')
            ->andWhere('p.agentPublic IS NULL')
            ->getQuery()->getSingleScalarResult();
    }

    protected function countValidEmail()
    {
        return $this->em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->andWhere('p.confirmationToken IS NULL')
                ->andWhere('p.emailConfirmedAt IS NOT NULL')
                ->getQuery()->getSingleScalarResult();
    }
}