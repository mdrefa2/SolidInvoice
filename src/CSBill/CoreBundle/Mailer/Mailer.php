<?php

namespace CSBill\CoreBundle\Mailer;

use CSBill\QuoteBundle\Entity\Quote;
use CSBill\QuoteBundle\Entity\Invoice;
use CSBill\CoreBundle\Mailer\Events\InvoiceEvent;
use CSBill\CoreBundle\Mailer\Events\QuoteEvent;
use CSBill\CoreBundle\Mailer\Events\MailerEvent;
use CSBill\CoreBundle\Mailer\MailerInterface;
use CSBill\CoreBundle\Mailer\Exception\UnexpectedFormatException;
use CS\SettingsBundle\Manager\SettingsManager;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Swift_Mailer;

class Mailer
{
    /**
     * @var Swift_Mailer
     */
    protected $mailer;

    /**
     * @var EngineInterface
     */
    protected $templating;

    protected $security;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function __construct($mailer, SettingsManager $settings)
    {
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    /**
     * (non-PHPdoc)
     * @see CSBill\CoreBundle\Service.MailerInterface::setTemplating()
     */
    public function setTemplating(EngineInterface $templating)
    {
        $this->templating = $templating;
    }

    /**
     * (non-PHPdoc)
     * @see CSBill\CoreBundle\Service.MailerInterface::setEventDispatcher()
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->dispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Set the security instance
     *
     * @param SecurityContextInterface $security
     */
    public function setSecurity(SecurityContextInterface $security)
    {
        $this->security = $security;
    }

    /**
     * Emails an invoice to the customers
     *
     * @param Invoice $invoice
     * @return boolean If the email was successfully sent
     */
    public function sendInvoice(Invoice $invoice)
    {
        // TODO : this needs to come from settings or somewhere so it can be extended
        $htmlTemplate = $this->getTemplate('CSBillInvoiceBundle:Email:invoice.html.twig', array('invoice' => $invoice));
        $textTemplate = $this->getTemplate('CSBillInvoiceBundle:Email:invoice.txt.twig', array('invoice' => $invoice));

        $subject = $this->getSubject('invoice.email_subject', $invoice->getId());

        $users = array();

        foreach ($invoice->getUsers() as $user) {
            $users[(string) $user->getDetail('email')] = $user->getFirstname() . ' ' . $user->getLastname();
        }

        $event = new InvoiceEvent();
        $event->setInvoice($invoice);

        $sent = $this->sendMessage($subject, $users, $htmlTemplate, $textTemplate, $event);

        return $sent;
    }

    /**
     * Emails a quote to the customers
     * @param Quote $quote
     * @return boolean If the email was successfully sent
     */
    public function sendQuote(Quote $quote)
    {
        // TODO : this needs to come from settings or somewhere so it can be extended
        $htmlTemplate = $this->getTemplate('CSBillQuoteBundle:Email:quote.html.twig', array('quote' => $quote));
        $textTemplate = $this->getTemplate('CSBillQuoteBundle:Email:quote.txt.twig', array('quote' => $quote));

        $subject = $this->getSubject('quote.email_subject', $quote->getId());

        $users = array();

        foreach ($quote->getUsers() as $user) {
            $users[(string) $user->getDetail('email')] = $user->getFirstname() . ' ' . $user->getLastname();
        }

        $event = new QuoteEvent();
        $event->setQuote($quote);

        $sent = $this->sendMessage($subject, $users, $htmlTemplate, $textTemplate, $event);

        return $sent;
    }

    /**
     * Get the subject for an email
     *
     * @param string $settingsKey
     * @param integer $id
     * @return string
     */
    public function getSubject($settingsKey, $id = null)
    {
        return str_replace('{id}', $id, $this->settings->get($settingsKey));
    }

    protected function sendMessage($subject, $users, $htmlTemplate = null, $textTemplate = null, Event $event = null)
    {
        $message = \Swift_Message::newInstance();

        $fromAddress = $this->settings->get('email.from_address');

        if(null !== $fromAddress) {
            $fromName = $this->settings->get('email.from_name');

            $message->setFrom($fromAddress, $fromName);
        } else {
            // if a from address is not specified in the config, then we use the currently logged-in users address
            $token = $this->security->getToken();

            $user = $token->getUser();

            $message->setFrom($user->getEmail());
        }

        $message->setSubject($subject)
                ->setTo($users);

        if(null !== $event) {
            $event->setHtmlTemplate($htmlTemplate);
            $event->setTextTemplate($textTemplate);
            $event->setMessage($message);

            $this->dispatcher->dispatch($event->getEvent(), $event);

            $htmlTemplate = $event->getHtmlTemplate();
            $textTemplate = $event->getTextTemplate();
        }

        if($this->settings->get('email.format') === 'html') {
            $message->setBody($htmlTemplate, 'text/html')
                    ->addPart($textTemplate, 'text/plain');
        } else {
            $message->setBody($textTemplate, 'text/plain');
        $format = (string) $this->settings->get('email.format');

        switch($format) {
            case 'html' :
                $message->setBody($htmlTemplate, 'text/html');
            break;

            case 'text' :
                $message->setBody($textTemplate, 'text/plain');
            break;

            case 'both' :
                $message->setBody($htmlTemplate, 'text/html')
                        ->addPart($textTemplate, 'text/plain');
            break;

            default :
                throw new UnexpectedFormatException($format);
            break;
        }

        $mailerEvent = new MailerEvent;
        $mailerEvent->setMessage($message);
        $this->dispatcher->dispatch(MailerEvents::MAILER_SEND, $mailerEvent);


        return $this->mailer->send($message);
    }

    protected function getTemplate($template, array $parameters = array())
    {
        return $this->templating->exists($template) ? $this->templating->render($template, $parameters) : null;
    }
}