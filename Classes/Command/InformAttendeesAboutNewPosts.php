<?php

namespace JWeiland\Pforum\Command;


use FluidTYPO3\Vhs\ViewHelpers\DebugViewHelper;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\PostRepository;
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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use Webyte\BbbEvents\Domain\Model\Attendee;
use Webyte\BbbEvents\Domain\Repository\AttendeeRepository;
use Webyte\BbbEvents\Domain\Repository\EventRepository;
use Webyte\BbbEvents\Domain\Service\AttendeeService;


class InformAttendeesAboutNewPosts extends Command
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
     * @var PostRepository
     */
    protected $postRepository = null;


    /**
     * @var SiteFinder
     */
    protected $sitefinder = null;
    public function __construct(\Webyte\BbbEvents\Domain\Repository\AttendeeRepository $attendeeRepository, \Webyte\BbbEvents\Domain\Repository\EventRepository $eventRepository, \JWeiland\Pforum\Domain\Repository\PostRepository $postRepository, \TYPO3\CMS\Core\Site\SiteFinder $sitefinder)
    {
        $this->attendeeRepository = $attendeeRepository;
        $this->eventRepository = $eventRepository;
        $this->postRepository = $postRepository;
        $this->sitefinder = $sitefinder;
        parent::__construct();
    }


    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Sends e-mails to all attendees about a new posts in the forum')
            ->setHelp('It looks for all new posts and sends out an e-mail informing about that')
//            ->addArgument(
//                'registrationPageUid',
//                InputArgument::REQUIRED,
//                'The uid of the page containing the registrationform'
//            )
        ;
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $posts = $this->postRepository->findUnsendPosts();

        /** @var Logger $logger */
        $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Start sending Information about Posts');

        /** @var Post $post */
        foreach ($posts as $post) {
            $attendees = $this->getAttendeesForPost($post);
            if (count($attendees) == 0) {
                $logger->error('No Attendees found for Post '.$post->getUid()." ".$post->getTitle());
                $this->changePostAttendeesInformedFlag($post, 2);
            } else {
                $this->changePostAttendeesInformedFlag($post, 1);
                /** @var Attendee $attendee */
                foreach ($attendees as $attendee) {
                    $logger->notice('Inform Attendee '.$attendee->getUid()." ".$attendee->getEmail()." about Post {$post->getUid()} ({$post->getTitle()})");
                    $this->sendPostInfo($post, $attendee);
                }
            }
        }
        $this->postRepository->forcePersist();

        $logger->notice('Finished sending topic infos');
        return 0;
    }

    private function changePostAttendeesInformedFlag(Post $post, int $newStatus = 1): void
    {
        $post->setAttendeesInformed($newStatus);
        $this->postRepository->update($post);
        $this->postRepository->forcePersist();
    }


    /**
     * @param  Attendee  $attendee
     */
    private function sendPostInfo(Post $post, Attendee $attendee): void
    {

        // Create the message
        /** @var FluidEmail $mail */
        $mail = GeneralUtility::makeInstance(FluidEmail::class);

        $templateName = 'ForumNewTopicInfo';

        $event = $attendee->getContingent()->getComitee()->getEvent();
        $topicTitle = $post->getTopic()?->getTitle();
        $email = $post->getFrontendUser()->getEmail();
        $creatorAttendee = $this->attendeeRepository->getExistingAttendeeInSameEventByMail($email, $event);
        $creatorName = $creatorAttendee ? $creatorAttendee->getFullName() : $email;
        $deeplink = $this->createLinkToPage($event->getRootpageuid());
        $mailtext = "der Teilnehmer <strong>{$creatorName}</strong> hat einen neuen Beitrag am Schwarzen Brett der Veranstaltung <strong>{$event->getTitle()}</strong> zum Thema <strong>{$topicTitle}</strong> erstellt:<br><br><hr><strong>{$post->getTitle()}</strong><br>".$post->getDescription()."<hr><br><br>Öffnene Sie die <a href='".$deeplink."'>Event-App</a> , um auf den Beitrag zu antworten: ".$deeplink;

        $site = $this->sitefinder->getSiteByPageId( $event->getRootpageuid() );
        $baseUri = (string)$site->getBase();

        // Prepare and send the message
        $mail
            // Give the message a subject
            ->subject("Neuer Beitrag am Schwarzen Brett: ".$post->getTitle())

            // Set the To addresses with an associative array
            ->to($attendee->getEmail())
            ->format('html')
            ->setTemplate($templateName)
            ->assign('headline', "Neuer Beitrag am Schwarzen Brett: ".$post->getTitle())
            ->assign('attendee', $attendee)
            ->assign('baseUri', $baseUri)
            ->assign('mailtext', $mailtext);

        if (!empty($attendee->getContingent()->getComitee()->getEvent()->getSendingMailFromAddress())) {
            $event = $attendee->getContingent()->getComitee()->getEvent();
            $mail->from(new Address($event->getSendingMailFromAddress(), $event->getSendingMailFromName()));
        }

        $attendee->addLogentry("Foruminfo Mail ".$post->getTitle());

        $mailer = GeneralUtility::makeInstance(Mailer::class);
        $mailer->send($mail);
        $this->attendeeRepository->update($attendee);

    }

    private function getAttendeesForPost(Post $post): array
    {
        $attendees = [];
        $eventId = $post->getTopic()?->getForum()?->getEvent();

        if ($eventId) {
            $event = $this->eventRepository->findByUid($eventId);
            if($event) $attendees = $this->attendeeRepository->findAttendeesByEvent($event)->toArray();
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
