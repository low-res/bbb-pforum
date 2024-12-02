<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Domain\Model;

use JWeiland\Pforum\Domain\Model\User;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Test case
 */
class UserTest extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @var User
     */
    protected $subject;

    protected function setUp(): void
    {
        $this->subject = new User();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
    }

    /**
     * @test
     */
    public function getNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getName()
        );
    }

    /**
     * @test
     */
    public function setNameSetsName(): void
    {
        $this->subject->setName('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getName()
        );
    }

    /**
     * @test
     */
    public function setNameWithIntegerResultsInString(): void
    {
        $this->subject->setName(123);
        self::assertSame('123', $this->subject->getName());
    }

    /**
     * @test
     */
    public function setNameWithBooleanResultsInString(): void
    {
        $this->subject->setName(true);
        self::assertSame('1', $this->subject->getName());
    }

    /**
     * @test
     */
    public function getUsernameInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getUsername()
        );
    }

    /**
     * @test
     */
    public function setUsernameSetsUsername(): void
    {
        $this->subject->setUsername('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getUsername()
        );
    }

    /**
     * @test
     */
    public function setUsernameWithIntegerResultsInString(): void
    {
        $this->subject->setUsername(123);
        self::assertSame('123', $this->subject->getUsername());
    }

    /**
     * @test
     */
    public function setUsernameWithBooleanResultsInString(): void
    {
        $this->subject->setUsername(true);
        self::assertSame('1', $this->subject->getUsername());
    }

    /**
     * @test
     */
    public function getEmailInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getEmail()
        );
    }

    /**
     * @test
     */
    public function setEmailSetsEmail(): void
    {
        $this->subject->setEmail('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getEmail()
        );
    }

    /**
     * @test
     */
    public function setEmailWithIntegerResultsInString(): void
    {
        $this->subject->setEmail(123);
        self::assertSame('123', $this->subject->getEmail());
    }

    /**
     * @test
     */
    public function setEmailWithBooleanResultsInString(): void
    {
        $this->subject->setEmail(true);
        self::assertSame('1', $this->subject->getEmail());
    }
}
