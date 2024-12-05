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
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use \TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

/**
 * Main controller to list and show postings/questions
 */
class AdministrationController extends ActionController
{
    /**
     * @var TopicRepository
     */
    protected $topicRepository;

    /**
     * @var PostRepository
     */
    protected $postRepository;

    protected ModuleTemplateFactory $moduleTemplateFactory;

    protected IconFactory $iconFactory;

    public function __construct(TopicRepository $topicRepository, PostRepository $postRepository, ModuleTemplateFactory $moduleTemplateFactory, IconFactory $iconFactory)
    {
        $this->topicRepository = $topicRepository;
        $this->postRepository = $postRepository;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->iconFactory = $iconFactory;
    }

    /**
     * Set up the doc header properly here
     */
    protected function initializeAction(): void
    {
//        if ($view instanceof BackendTemplateView) {
        //$view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation([]);
        $this->createDocheaderActionButtons();
        $this->createShortcutButton();
        parent::initializeAction();
//        }
    }

    protected function createDocheaderActionButtons(): void
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if (!in_array($this->actionMethodName, ['indexAction', 'listHiddenTopicsAction', 'listHiddenPostsAction'], true)) {
            return;
        }

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

//        $button = $buttonBar->makeLinkButton()
//            ->setHref( $uriBuilder->buildUriFromRoute )
//            ->setTitle('Back')
//            ->setIcon( $this->iconFactory->getIcon( 'actions-view-go-back', Icon::SIZE_SMALL ) );
//        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
    }

    protected function createShortcutButton(): void
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $pageId = (int)($this->request->getQueryParams()['id'] ?? 0);
//        $shortCutButton = $buttonBar->makeShortcutButton()
//            ->setRouteIdentifier('web_PforumAdministration')
//            ->setDisplayName('Shortcut')
//            ->setArguments(['route', 'module', 'id']);
//        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);

    }

    public function indexAction(): \Psr\Http\Message\ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        return $moduleTemplate->renderResponse(); //$this->htmlResponse( $moduleTemplate->renderContent() );
    }

    public function listHiddenTopicsAction(): \Psr\Http\Message\ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->view->assign('topics', $this->topicRepository->findAllHidden()->toArray());
        $moduleTemplate->setContent($this->view->render());
        return $moduleTemplate->renderResponse(); //$this->htmlResponse($moduleTemplate->renderContent());
    }

    public function listHiddenPostsAction(): \Psr\Http\Message\ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->view->assign('posts', $this->postRepository->findAllHidden()->toArray());
        $moduleTemplate->setContent($this->view->render());
        return $moduleTemplate->renderResponse(); //$this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Topic $record
     */
    public function activateTopicAction(Topic $record)
    {
        $record->setHidden(false);
        $this->topicRepository->update($record);
        $this->addFlashMessage(
            'Topic "' . $record->getTitle() . '" was activated.',
            'Topic activated',
            \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::INFO
        );
        return $this->redirect('listHiddenTopics');
    }

    /**
     * @param Post $record
     */
    public function activatePostAction(Post $record)
    {
        $record->setHidden(false);
        $this->postRepository->update($record);
        $this->addFlashMessage(
            'Post "' . $record->getTitle() . '" was activated.',
            'Post activated',
            \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::INFO
        );
        return $this->redirect('listHiddenPosts');
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
