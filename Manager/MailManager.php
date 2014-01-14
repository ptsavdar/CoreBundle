<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Manager;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\Translator;
use Claroline\CoreBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;

/**
 * @DI\Service("claroline.manager.mail_manager")
 */
class MailManager
{
    private $router;
    private $mailer;
    private $templating;
    private $translator;
    private $container;
    private $ch;

    /**
     * @DI\InjectParams({
     *     "router"         = @DI\Inject("router"),
     *     "mailer"         = @DI\Inject("mailer"),
     *     "templating"     = @Di\Inject("templating"),
     *     "ch"             = @DI\Inject("claroline.config.platform_config_handler"),
     *     "container"      = @DI\Inject("service_container")
     * })
     */
    public function __construct(
        \Swift_Mailer $mailer,
        EngineInterface $templating,
        UrlGeneratorInterface $router,
        Translator $translator,
        PlatformConfigurationHandler $ch,
        ContainerInterface $container
    )
    {
        $this->router = $router;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->container = $container;
        $this->ch = $ch;
    }

    /**
     * @return boolean
     */
    public function isMailerAvailable()
    {
        try {
            $this->mailer->getTransport()->start();

            return true;

        } catch (\Swift_TransportException $e) {
            return false;
        }
    }

    /**
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     * @return boolean
     */
    public function sendForgotPassword(User $user)
    {
        $hash = $user->getResetPasswordHash();
        $msg = $this->translator->trans('mail_click', array(), 'platform');
        $link = $this->container->get('request')->server->get('HTTP_ORIGIN') . $this->router->generate(
            'claro_security_reset_password',
            array('hash' => $hash)
        );
        $body = $this->templating->render(
            'ClarolineCoreBundle:Authentication:emailForgotPassword.html.twig',
            array('message' => $msg, 'link' => $link, 'user' => $user)
        );
        $subject = $this->translator->trans('reset_pwd', array(), 'platform');

        return $this->send($subject, $body, array($user));
    }

    /**
     * @param User $user
     *
     * @return boolean
     */
    public function sendPlainPassword(User $user)
    {
        $body = $this->translator->trans('admin_form_username', array(), 'platform').
            ' : '.$user->getUsername().' '.
            $this->translator->trans('admin_form_plainPassword_first', array(), 'platform').
            ': '.$user->getPlainPassword();

        $subject = $this->translator->trans('create_new_user_account', array(), 'platform');

        return $this->send($subject, $body, array($user));
    }

    /**
     * @param string $subject
     * @param string $body
     * @param User[] $users
     * @param User   $from
     *
     * @return boolean
     */
    public function send($subject, $body, array $users, $from = null)
    {
        if ($this->isMailerAvailable()) {
            $from = ($from === null) ? $this->ch->getParameter('support_email'): $from->getMail();
            $to = array();

            foreach ($users as $user) {
                $to[] = $user->getMail();
            }

            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom($from)
                ->setBody($body, 'text/html');

            if (count($to) > 1) {
                $message->setCc($to);
            } else {
                $message->setTo($to);
            }

            return $this->mailer->send($message) ? true: false;
        }

        return false;
    }
}
