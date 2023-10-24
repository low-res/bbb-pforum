<?php

namespace JWeiland\Pforum\Command;


use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use Webyte\BbbEvents\Domain\Model\Attendee;
use Webyte\BbbEvents\Domain\Repository\AttendeeRepository;


class InformAttendeesAboutNewTopic extends Command
{

    /**
     * attendeeRepository
     *
     * @var AttendeeRepository
     */
    protected $attendeeRepository = null;


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

        $topics = $this->topicRepository->findUnsendTopics();

        /** @var Logger $logger */
        $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Start sending Information about Topics');

        /** @var Topic $topic */
        foreach ($topics as $topic) {
            $attendees = $this->getAttendeesForTopic($topic);
            if(count($attendees) == 0) {
                $logger->notice('No Attendees found for Topic '.$topic->getUid()." ".$topic->getTitle());
                $topic->setAttendeesInformed( 2 );
            } else {
                /** @var Attendee $attendee */
                foreach ($attendees as $attendee) {
                    $logger->notice('Inform Attendee '.$attendee->getUid()." ".$attendee->getEmail()." about Topic {$topic->getUid()} ({$topic->getTitle()})");
                    $this->sendTopicInfo($topic, $attendee);
                }
                $topic->setAttendeesInformed( 1 );
            }

        }
        $logger->notice('Finished sending topic infos');
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
     * @param  TopicRepository  $topicRepository
     */
    public function injectTopicRepository(TopicRepository $topicRepository)
    {
        $this->topicRepository = $topicRepository;
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
    private function sendTopicInfo(Topic $topic, Attendee $attendee)
    {

        // Create the message
        /** @var FluidEmail $mail */
        $mail = GeneralUtility::makeInstance(FluidEmail::class);

        $templateName = 'TopicInfo';

        $forumCeUid = $this->getPageWithForumCeForTopic($topic);
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($forumCeUid);
        $baseUri = (string) $site->getBase();



        // Prepare and send the message
        $mail

            // Give the message a subject
            ->subject("Neues Thema im Forum: ".$topic->getTitle())

            // Set the To addresses with an associative array
            ->to($attendee->getEmail())
            ->format('html')
            ->setTemplate($templateName)
            ->assign('headline', 'Forum');

        if (!empty($attendee->getContingent()->getComitee()->getEvent()->getSendingMailFromAddress())) {
            $event = $attendee->getContingent()->getComitee()->getEvent();
            $mail->from(new Address($event->getSendingMailFromAddress(), $event->getSendingMailFromName()));
        }

        $attendee->addLogentry("Mailtemplate: ".$templateName.", Foruminfo Mail ".$topic->getTitle());

        $mailer = GeneralUtility::makeInstance(Mailer::class);
        $mailer->send($mail);
        $this->attendeeRepository->update($attendee);
    }

    private function getAttendeesForTopic(Topic $topic): array
    {
        $attendees = [];
        $eventId = $topic->getForum()?->getEvent();

        if( $eventId )
        {
            $attendees = $this->attendeeRepository->findAttendeesByEvent($eventId);
        }


        return $attendees;
    }

    private function getPageWithForumCeForTopic(Topic $topic)
    {
        $pidOfForum = $topic->getForum()->getPid();
        $pageId = 0; // get the site that has a tt_content with list_type "pforum_forum" and pages contains  $pidOfForum
        return $pageId;
    }


    public function injectAttendeeService()
    {

    }

}
