<?php

namespace JWeiland\Pforum\Command;


use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
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
use Webyte\BbbEvents\Domain\Repository\AttendeeRepository;
use Webyte\BbbEvents\Domain\Repository\EventRepository;
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
     * eventRepository
     *
     * @var EventRepository
     */
    protected $eventRepository = null;



    /**
     * topicRepository
     *
     * @var TopicRepository
     */
    protected $topicRepository = null;


    /**
     * @var SiteFinder
     */
    protected $sitefinder = null;
    public function __construct(\Webyte\BbbEvents\Domain\Repository\AttendeeRepository $attendeeRepository, \Webyte\BbbEvents\Domain\Repository\EventRepository $eventRepository, \JWeiland\Pforum\Domain\Repository\TopicRepository $topicRepository, \TYPO3\CMS\Core\Site\SiteFinder $sitefinder)
    {
        $this->attendeeRepository = $attendeeRepository;
        $this->eventRepository = $eventRepository;
        $this->topicRepository = $topicRepository;
        $this->sitefinder = $sitefinder;
        parent::__construct();
    }


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


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topics = $this->topicRepository->findUnsendTopics();

        /** @var Logger $logger */
        $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Start sending Information about Topics');

        /** @var Topic $topic */
        foreach ($topics as $topic) {
            $attendees = $this->getAttendeesForTopic($topic);
            if (count($attendees) == 0) {
                $logger->error('No Attendees found for Topic '.$topic->getUid()." ".$topic->getTitle());
                $this->changeTopicAttendeesInformedFlag($topic, 2);
            } else {
                $this->changeTopicAttendeesInformedFlag($topic, 1);

                /** @var Attendee $attendee */
                foreach ($attendees as $attendee) {
                    $logger->notice('Inform Attendee '.$attendee->getUid()." ".$attendee->getEmail()." about Topic {$topic->getUid()} ({$topic->getTitle()})");
                    $this->sendTopicInfo($topic, $attendee);
                }
            }
        }

        $logger->notice('Finished sending topic infos');
        return 0;
    }

    private function changeTopicAttendeesInformedFlag(Topic $topic, int $newStatus = 1): void
    {
        $topic->setAttendeesInformed($newStatus);
        $this->topicRepository->update($topic);
        $this->topicRepository->forcePersist();
    }


    /**
     * @param  Attendee  $attendee
     */
    private function sendTopicInfo(Topic $topic, Attendee $attendee): void
    {

        // Create the message
        /** @var FluidEmail $mail */
        $mail = GeneralUtility::makeInstance(FluidEmail::class);

        $templateName = 'ForumNewTopicInfo';

        $event = $attendee->getContingent()->getComitee()->getEvent();
        $email = $topic->getFrontendUser()->getEmail();
        $creatorAttendee = $this->attendeeRepository->getExistingAttendeeInSameEventByMail($email, $event);
        $creatorName = $creatorAttendee ? $creatorAttendee->getFullName() : $email;
        $deeplink = $this->createLinkToPage($event->getRootpageuid());
        $mailtext = "der Teilnehmer <strong>{$creatorName}</strong> hat ein neues Thema am Schwarzen Brett zur Veranstaltung <strong>{$event->getTitle()}</strong> erstellt:<br><br><hr><strong>{$topic->getTitle()}</strong><br>".$topic->getDescription()."<hr><br><br>Öffnene Sie die <a href='".$deeplink."'>Event-App</a> , um auf das Thema zu antworten: ".$deeplink;

        $site = $this->sitefinder->getSiteByPageId( $event->getRootpageuid() );
        $baseUri = (string)$site->getBase();

        // Prepare and send the message
        $mail
            // Give the message a subject
            ->subject("Neues Thema am Schwarzen Brett: ".$topic->getTitle())

            // Set the To addresses with an associative array
            ->to($attendee->getEmail())
            ->format('html')
            ->setTemplate($templateName)
            ->assign('headline', "Neues Thema am Schwarzen Brett: ".$topic->getTitle() )
            ->assign('attendee', $attendee)
            ->assign('baseUri', $baseUri)
            ->assign('mailtext', $mailtext);

        if (!empty($attendee->getContingent()->getComitee()->getEvent()->getSendingMailFromAddress())) {
            $event = $attendee->getContingent()->getComitee()->getEvent();
            $mail->from(new Address($event->getSendingMailFromAddress(), $event->getSendingMailFromName()));
        }

        $attendee->addLogentry("Foruminfo Mail ".$topic->getTitle());

        $mailer = GeneralUtility::makeInstance(Mailer::class);
        $mailer->send($mail);
        $this->attendeeRepository->update($attendee);

    }

    private function getAttendeesForTopic(Topic $topic): array
    {
        $attendees = [];
        $eventId = $topic->getForum()?->getEvent();

        if ($eventId) {
            $event = $this->eventRepository->findByUid($eventId);
            $attendees = $this->attendeeRepository->findAttendeesByEvent($event)->toArray();
        }


        return $attendees;
    }


    public function injectAttendeeService()
    {

    }

    private function createLinkToPage($pid, $params = [])
    {
        return $this->sitefinder->getSiteByPageId($pid)
            ->getRouter()
            ->generateUri($pid, $params);
    }

}
