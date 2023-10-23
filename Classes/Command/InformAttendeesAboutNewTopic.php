<?php

namespace JWeiland\Pforum\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webyte\BbbEvents\Domain\Model\Attendee;
use Webyte\BbbEvents\Domain\Model\Comittee;
use Webyte\BbbEvents\Domain\Repository\AttendeeRepository;
use Webyte\BbbEvents\Domain\Service\AttendeeService;


class InformAttendeesAboutNewTopic extends Command
{

    /**
     * attendeeRepository
     *
     * @var AttendeeRepository
     */
    protected $attendeeRepository = null;


    /**
     * @var SiteFinder
     */
    protected $sitefinder = null;


    /** @var AttendeeService */
    protected $attendeeService;


    private $registrationPageUid;


    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Sends e-mails to all attendees about a new topic in the forum')
            ->setHelp('It looks for all new topics and sends out an e-mail informing about that')
//            ->addArgument(
//                'registrationPageUid',
//                InputArgument::REQUIRED,
//                'The uid of the page containing the registrationform'
//            )
        ;
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->registrationPageUid = $input->getArgument('registrationPageUid');

        $attendees = $this->attendeeRepository->getUninvitedAttendees();

        /** @var Logger $logger */
        $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Start sending Information about Topics');

        /** @var Attendee $attendee */
        foreach ($attendees as $attendee) {
            $logger->notice('Invite Attendee '.$attendee->getUid()." ".$attendee->getEmail());
            $this->sendInvitationMail($attendee);
            $this->markAttendeeAsInvited($attendee);
        }
        $this->attendeeRepository->forcePersist();
        $logger->notice('Finished sending Invitations');
        return 0;
    }


    /**
     * @param  AttendeeRepository  $attendeeRepository
     */
    public function injectAttendeeRepository(AttendeeRepository $attendeeRepository)
    {
        $this->attendeeRepository = $attendeeRepository;
    }


    /**
     * @param  AttendeeService  $attendeeService
     */
    public function injectAttendeeService(AttendeeService $attendeeService)
    {
        $this->attendeeService = $attendeeService;
    }


    /**
     * @param  SiteFinder  $sitefinder
     */
    public function injectSiteFinder(SiteFinder $sitefinder)
    {
        $this->sitefinder = $sitefinder;
    }


    /**
     * @param  Attendee  $attendee
     */
    private function sendInvitationMail(Attendee $attendee)
    {

        // Create the message
        /** @var FluidEmail $mail */
        $mail = GeneralUtility::makeInstance(FluidEmail::class);

        /** @var Comittee $comitee */
        $comitee = $attendee->getContingent()->getComitee();

        $templateName = 'Invitation';
        if ($attendee->getFeuser()) {
            $templateName = 'InvitationnoteForExistingUser';
        }

        $this->attendeeService->setRegistrationPageId($this->registrationPageUid);
        $registrationlink = $this->attendeeService->getRegistrationUrl($attendee);
        $preparedMailtext = $this->replacePlaceholdersInText($comitee->getEventstext(), $attendee);

        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($this->registrationPageUid);
        $baseUri = (string) $site->getBase();

        if ($attendee->getFeuser()) {
            $registrationlink = $baseUri;
        }

        // Prepare and send the message
        $mail

            // Give the message a subject
            ->subject($comitee->getMailsubject())

            // Set the To addresses with an associative array
            ->to($attendee->getEmail())
            ->format('html')
            ->setTemplate($templateName)
            ->assign('headline', 'Einladung')
            ->assign('attendee', $attendee)
            ->assign('mailtext', $preparedMailtext)
            ->assign('latestregister', $comitee->getLatestregister())
            ->assign('registrationcode', $attendee->getRegistrationCode())
            ->assign('registrationurl', $registrationlink)
            ->assign('mailsubject', $comitee->getMailsubject())
            ->assign('baseUri', $baseUri);

        if (!empty($attendee->getContingent()->getComitee()->getEvent()->getSendingMailFromAddress())) {
            $event = $attendee->getContingent()->getComitee()->getEvent();
            $mail->from(new Address($event->getSendingMailFromAddress(), $event->getSendingMailFromName()));
        }

        $attendee->addLogentry("Mailtemplate: ".$templateName.", Registrationlink ".$registrationlink);

        $mailer = GeneralUtility::makeInstance(Mailer::class);
        $mailer->send($mail);
        $attendee->addLogentry("Mailer Debuginfo: ".$mailer->getSentMessage()->getDebug());
        $this->attendeeRepository->update($attendee);
    }

}
