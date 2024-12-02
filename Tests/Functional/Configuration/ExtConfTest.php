<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Configuration;

use JWeiland\Pforum\Configuration\ExtConf;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Test case
 */
class ExtConfTest extends FunctionalTestCase
{
    use ProphecyTrait;

    /**
     * @var ExtConf
     */
    protected $subject;

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/pforum'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ExtConf();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getEmailFromAddressInitiallyThrowsException(): void
    {
        self::expectExceptionCode(1604694223);
        $this->subject->getEmailFromAddress();
    }

    /**
     * @test
     */
    public function getEmailFromAddressInitiallyReturnsEmailFromInstallTool(): void
    {
        $email = 'info@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $email;
        self::assertSame(
            $email,
            $this->subject->getEmailFromAddress()
        );
    }

    /**
     * @test
     */
    public function setEmailFromAddressSetsEmailAddress(): void
    {
        $email = 'abc@example.com';
        $this->subject->setEmailFromAddress($email);

        self::assertSame(
            $email,
            $this->subject->getEmailFromAddress()
        );
    }

    /**
     * @test
     */
    public function getEmailFromNameInitiallyThrowsException(): void
    {
        self::expectExceptionCode(1604694279);
        $this->subject->getEmailFromName();
    }

    /**
     * @test
     */
    public function getEmailFromNameInitiallyReturnsEmailNameFromInstallTool(): void
    {
        $name = 'stefan';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $name;
        self::assertSame(
            $name,
            $this->subject->getEmailFromName()
        );
    }

    /**
     * @test
     */
    public function setEmailFromNameSetsEmailName(): void
    {
        $name = 'stefan';
        $this->subject->setEmailFromName($name);

        self::assertSame(
            $name,
            $this->subject->getEmailFromName()
        );
    }
}
