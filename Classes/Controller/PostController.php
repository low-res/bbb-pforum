<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Model\User;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation as Extbase;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller to manage (list and show) postings
 */
class PostController extends AbstractController
{
    /**
     * @param Topic $topic
     * @param Post|null $post
     */
    #[Extbase\IgnoreValidation(['argumentName' => 'post'])]
    public function newAction(Topic $topic, Post $post = null): \Psr\Http\Message\ResponseInterface
    {
        $this->view->assign('topic', $topic);
        $this->view->assign('post', $post);
        return $this->htmlResponse();
    }

    /**
     * Convert images to array while passing them to post model
     */
    public function initializeCreateAction(): void
    {
        $this->preProcessControllerAction();
    }

    public function createAction(Topic $topic, Post $post)
    {
        // if auth = frontend user
        if ((int)$this->settings['auth'] === 2) {
            $this->addFeUserToPost($topic, $post);
        }

        $topic->addPost($post);
        $this->topicRepository->update($topic);

        // if a preview was requested direct to preview action
        if ($this->controllerContext->getRequest()->hasArgument('preview')) {
            $post->setHidden(true); // post should not be visible while previewing
            $this->persistenceManager->persistAll(); return $this->redirect(
                'edit',
                'Post',
                'Pforum',
                ['post' => $post, 'isPreview' => true, 'isNew' => true]
            );
        }

        if (
            isset($this->settings['post']['hideAtCreation'])
            && $this->settings['post']['hideAtCreation'] === '1'
        ) {
            $post->setHidden(true);
        }

        // if auth = anonymous user
        /* send a mail to the user to activate, edit or delete his entry */
        if (((int)$this->settings['auth'] === 1) && $this->settings['emailIsMandatory']) {
            $this->persistenceManager->persistAll(); // we need an uid for mailing
            $this->mailToUser($post);
        }

        if (
            $post->getHidden() === false
            && $topic->getUser() instanceof User
            && $topic->getUser()->getEmail() !== ''
        ) {
            // Send an email to creator of topic to inform him about new comments/posts
            $this->mailToTopicCreator($topic, $post);
        }

        $this->addFlashMessageForCreation();
        return $this->redirect('show', 'Topic', 'Pforum', ['topic' => $topic]);
    }

    /**
     * Hidden record throws an exception.
     * That's why I check it here before calling editAction.
     */
    public function initializeEditAction(): void
    {
        $this->preProcessControllerAction();

        $this->registerPostFromRequest('post');
    }

    /**
     * @param Post|null $post
     * @param bool $isPreview
     * @param bool $isNew We need the information if updateAction was called from createAction.
     *                    If so we have to passthrough this information
     */
    #[Extbase\IgnoreValidation(['argumentName' => 'post'])]
    public function editAction(Post $post = null, bool $isPreview = false, bool $isNew = false): \Psr\Http\Message\ResponseInterface
    {
        $this->view->assign('post', $post);
        $this->view->assign('isPreview', $isPreview);
        $this->view->assign('isNew', $isNew);
        return $this->htmlResponse();
    }

    /**
     * getObjectByIdentifier can only find non-hidden values.
     * With this method we help extbase backend to find our hidden object.
     */
    public function initializeUpdateAction(): void
    {
        $this->preProcessControllerAction();

        $this->registerPostFromRequest('post');
    }

    /**
     * @param Post $post
     * @param bool $isNew We need the information if updateAction was
     *                    called from createAction. If so we have to add different messages
     */
    public function updateAction(Post $post, bool $isNew = false)
    {
        $this->postRepository->update($post);

        // if a preview was requested direct to preview action
        if ($this->controllerContext->getRequest()->hasArgument('preview')) {
            $post->setHidden(true);
            return $this->redirect(
                'edit',
                'Post',
                'Pforum',
                ['post' => $post, 'isPreview' => true, 'isNew' => $isNew]
            );
        } else {
            if ($isNew) {
                // if is new and preview was pressed we have to check for visibility again
                if ($this->settings['post']['hideAtCreation']) {
                    $post->setHidden(true);
                } else {
                    $post->setHidden(false);
                }

                // if auth = anonymous user
                // send a mail to the user to activate, edit or delete his entry
                if (((int)$this->settings['auth'] === 1) && $this->settings['emailIsMandatory']) {
                    $this->mailToUser($post);
                }

                $this->addFlashMessageForCreation();
            } else {
                // edited posts which are not new are visible
                $post->setHidden(false);
                $this->addFlashMessage(LocalizationUtility::translate('postUpdated', 'pforum'));
            }

            return $this->redirect('show', 'Topic', 'Pforum', ['topic' => $post->getTopic()]);
        }
    }

    /**
     * Hidden record throws an exception.
     * That's why I check it here before calling deleteAction.
     */
    public function initializeDeleteAction(): void
    {
        $this->preProcessControllerAction();

        $this->registerPostFromRequest('post');
    }

    /**
     * @param Post $post
     */
    public function deleteAction(Post $post)
    {
        $this->postRepository->remove($post);
        $this->addFlashMessage(LocalizationUtility::translate('postDeleted', 'pforum'));
        return $this->redirect('list', 'Forum', 'Pforum');
    }

    protected function mailToTopicCreator(Topic $topic, Post $post): void
    {
        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->to(new Address($topic->getUser()->getEmail(), $topic->getUser()->getName()))
            ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
            ->subject('Neue Antwort auf Ihr Thema:' . $topic->getTitle())
            ->setTemplate('Default')
            ->assignMultiple([
                'headline' => 'Hello ' . $topic->getUser()->getName(),
                'introduction' => 'Es gibt eine neue Antwort auf Ihr Thema ' . $topic->getTitle() . ':',
                'content' => nl2br($post->getDescription()),
            ]);
        GeneralUtility::makeInstance(Mailer::class)->send($email);
    }

    /**
     * Hidden record throws an exception.
     * That's why I check it here before calling activateAction.
     */
    public function initializeActivateAction(): void
    {
        $this->preProcessControllerAction();

        $this->registerPostFromRequest('post');
    }

    /**
     * We need this extra action, because hidden entries can't be found in FE mode.
     *
     * @param Post $post
     */
    public function activateAction(Post $post)
    {
        $post->setHidden(false);
        $this->postRepository->update($post);

        // send an email to creator of topic to inform him about new comments/posts
        $this->mailToTopicCreator($post->getTopic(), $post);

        $this->addFlashMessage(LocalizationUtility::translate('postActivated', 'pforum'));
        return $this->redirect('list', 'Forum', 'Pforum');
    }

    /**
     * This is a workaround to help controller actions to find (hidden) posts.
     */
    protected function registerPostFromRequest(string $argumentName): void
    {
        $argument = $this->request->getArgument($argumentName);
        if (is_array($argument)) {
            // get post from form ($_POST)
            $post = $this->postRepository->findHiddenObject((int)$argument['__identity']);
        } else {
            // get post from UID
            $post = $this->postRepository->findHiddenObject((int)$argument);
        }

        if ($post instanceof Post) {
            $this->session->registerObject($post, $post->getUid());
        }
    }

    protected function addFeUserToPost(Topic $topic, Post $post): \Psr\Http\Message\ResponseInterface
    {
        if (is_array($GLOBALS['TSFE']->fe_user->user) && $GLOBALS['TSFE']->fe_user->user['uid']) {
            $user = $this->frontendUserRepository->findByUid(
                (int)$GLOBALS['TSFE']->fe_user->user['uid']
            );
            $post->setFrontendUser($user);
        } else {
            /* normally this should never be called, because the link to create a new entry was not displayed if user was not authenticated */
            $this->addFlashMessage('You must be logged in before creating a post');
            return $this->redirect('show', 'Forum', 'Pforum', ['forum' => $topic->getForum()]);
        }
    }

    protected function mailToUser(Post $post): void
    {
        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->to(new Address($post->getUser()->getEmail(), $post->getUser()->getName()))
            ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
            ->subject(LocalizationUtility::translate('email.post.subject', 'pforum'))
            ->format('html')
            ->setTemplate('ConfigurePost')
            ->assignMultiple([
                'settings' => $this->settings,
                'post' => $post,
            ]);
        GeneralUtility::makeInstance(Mailer::class)->send($email);
    }

    protected function addFlashMessageForCreation(): void
    {
        if ($this->settings['post']['hideAtCreation']) {
            if ($this->settings['post']['activateByAdmin']) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('hiddenPostCreatedAndActivateByAdmin', 'pforum')
                );
            } else {
                $this->addFlashMessage(
                    LocalizationUtility::translate('hiddenPostCreatedAndActivateByUser', 'pforum')
                );
            }
        } else {
            // if topic is not hidden at creation there is no need to activate it by admin
            $this->addFlashMessage(
                LocalizationUtility::translate('postCreated', 'pforum')
            );
        }
    }
}
