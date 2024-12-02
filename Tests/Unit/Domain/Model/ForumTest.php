<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Domain\Model;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Model\Topic;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Test case
 */
class ForumTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @var Forum
     */
    protected $subject;

    protected function setUp(): void
    {
        $this->subject = new Forum();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
    }

    /**
     * @test
     */
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getTitle()
        );
    }

    /**
     * @test
     */
    public function setTitleSetsTitle(): void
    {
        $this->subject->setTitle('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getTitle()
        );
    }

    /**
     * @test
     */
    public function setTitleWithIntegerResultsInString(): void
    {
        $this->subject->setTitle(123);
        self::assertSame('123', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function setTitleWithBooleanResultsInString(): void
    {
        $this->subject->setTitle(true);
        self::assertSame('1', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function getTeaserInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getTeaser()
        );
    }

    /**
     * @test
     */
    public function setTeaserSetsTeaser(): void
    {
        $this->subject->setTeaser('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getTeaser()
        );
    }

    /**
     * @test
     */
    public function setTeaserWithIntegerResultsInString(): void
    {
        $this->subject->setTeaser(123);
        self::assertSame('123', $this->subject->getTeaser());
    }

    /**
     * @test
     */
    public function setTeaserWithBooleanResultsInString(): void
    {
        $this->subject->setTeaser(true);
        self::assertSame('1', $this->subject->getTeaser());
    }

    /**
     * @test
     */
    public function getTopicsInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getTopics()
        );
    }

    /**
     * @test
     */
    public function setTopicsSetsTopics(): void
    {
        $object = new Topic();
        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);
        $this->subject->setTopics($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getTopics()
        );
    }

    /**
     * @test
     */
    public function addTopicAddsOneTopic(): void
    {
        $objectStorage = new ObjectStorage();
        $this->subject->setTopics($objectStorage);

        $object = new Topic();
        $this->subject->addTopic($object);

        $objectStorage->attach($object);

        self::assertSame(
            $objectStorage,
            $this->subject->getTopics()
        );
    }

    /**
     * @test
     */
    public function removeTopicRemovesOneTopic(): void
    {
        $object = new Topic();
        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);
        $this->subject->setTopics($objectStorage);

        $this->subject->removeTopic($object);
        $objectStorage->detach($object);

        self::assertSame(
            $objectStorage,
            $this->subject->getTopics()
        );
    }
}
