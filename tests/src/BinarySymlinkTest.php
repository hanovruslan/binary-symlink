<?php

namespace Evolaze\BinarySymlink\Test;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BaseIO;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Evolaze\BinarySymlink\ScriptHandler;
use PHPUnit_Framework_TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class BinarySymlinkTest extends PHPUnit_Framework_TestCase
{
    protected const TESTS_DIR = 'tests';
    protected const FROM_DIR = self::TESTS_DIR . DIRECTORY_SEPARATOR . 'from';
    protected const TO_DIR = self::TESTS_DIR . DIRECTORY_SEPARATOR . 'to';
    protected const BIN_1 = '1.sh';
    protected const BIN_2 = '2.sh';
    protected const SUBDIR_0 = 'subdir0';
    protected const SUBDIR0_BIN_3 = self::SUBDIR_0 . DIRECTORY_SEPARATOR . '3.sh';
    protected const SUBDIR0_BIN_4 = self::SUBDIR_0 . DIRECTORY_SEPARATOR . '4.sh';
    protected const SUBDIR1_BIN_5 = self::SUBDIR_0 . DIRECTORY_SEPARATOR . '5.sh';
    protected const SUBDIR1_BIN_6 = self::SUBDIR_0 . DIRECTORY_SEPARATOR . '6.sh';
    protected const DEFAULT_FILEMODE = '0644';

    /** @var Composer */
    protected $composer;

    /** @var BaseIO */
    protected $io;

    /** @var Config */
    protected $config;

    /** @var RootPackage */
    protected $package;

    public function testDoNothingInNotDevMode()
    {
        $event = new Event('name', $this->composer, $this->io);
        ScriptHandler::installBinary($event);
    }

    /**
     * @param array|null $selfExtra
     *
     * @dataProvider dataProviderFailedOnMissedExtra
     */
    public function testFailedOnMissedExtra(array $selfExtra = null)
    {
        $this->setExpectedException(RuntimeException::class, 'cannot find links options');
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
    }

    public function dataProviderFailedOnMissedExtra()
    {
        $selfExtra0 = null;
        $selfExtra1 = [];
        $selfExtra2 = [
            'links' => null,
        ];

        return [
            [$selfExtra0],
            [$selfExtra1],
            [$selfExtra2],
        ];
    }

    /**
     * @param string $expectedFrom
     * @param string $expectedTo
     * @param array|null $selfExtra
     * @dataProvider dataProviderInstall
     */
    public function testInstall(string $expectedFrom, string $expectedTo, array $selfExtra = null)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileEquals($expectedFrom, $expectedTo);
    }

    public function dataProviderInstall()
    {
        $from0 = self::BIN_1;
        $selfExtra0 = array_merge(self::getDefaultSelfExtra(), [
            'links' => $from0,
        ]);
        $selfExtra1 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                $from0,
            ],
        ]);
        $to2 = self::BIN_2;
        $selfExtra2 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                $from0 => $to2,
            ],
        ]);
        $from3 = self::BIN_2;
        $to3 = self::BIN_1;
        $selfExtra3 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [[
                'from' => $from3,
                'to' => $to3,
            ]]
        ]);
        return [
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from0, self::TO_DIR . DIRECTORY_SEPARATOR . $from0, $selfExtra0],
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from0, self::TO_DIR . DIRECTORY_SEPARATOR . $from0, $selfExtra1],
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from0, self::TO_DIR . DIRECTORY_SEPARATOR . $to2, $selfExtra2],
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from3, self::TO_DIR . DIRECTORY_SEPARATOR . $to3, $selfExtra3],
        ];
    }

    /**
     * @param string $expectedFrom
     * @param string $expectedTo
     * @param array $selfExtra
     *
     * @dataProvider dataProviderUseRoot
     */
    public function testUseRoot(string $expectedFrom, string $expectedTo, array $selfExtra)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileEquals($expectedFrom, $expectedTo);
    }

    public function dataProviderUseRoot()
    {
        $from0 = self::FROM_DIR . DIRECTORY_SEPARATOR . self::BIN_1;
        $to0 = self::TO_DIR . DIRECTORY_SEPARATOR . self::BIN_1;
        $selfExtra0 = [
            'to-dir' => self::TO_DIR,
            'use-root' => true,
            'links' => $from0,
        ];

        return [
            [$from0, $to0, $selfExtra0],
        ];
    }

    /**
     * @param int $expected
     * @param array $selfExtra
     * @param string $to
     * @dataProvider dataProviderScanDir
     */
    public function testScanDir(int $expected, array $selfExtra, string $to)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $files = Finder::create()->files()
            ->in($to);
        $this->assertEquals($expected, $files->count());
    }

    public function dataProviderScanDir()
    {
        $selfExtra0 = array_merge(self::getDefaultSelfExtra(), [
            'links' => self::SUBDIR_0,
        ]);
        $selfExtra1 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                self::SUBDIR_0,
            ],
        ]);

        return [
            [2, $selfExtra0, $selfExtra0['to-dir']],
            [2, $selfExtra1, $selfExtra0['to-dir']],
        ];
    }

    /**
     * @param string $expected
     * @param string $file
     * @param array $selfExtra
     * @dataProvider dataProviderFilemode
     */
    public function testFilemode(string $expected, string $file, array $selfExtra)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertSame(substr(sprintf('%o', fileperms($file)), -4), $expected);
    }

    public function dataProviderFilemode()
    {
        $bin = self::BIN_1;
        $filemode = '0711';
        $selfExtra0 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                $bin,
            ],
        ]);
        $selfExtra1 = array_merge($selfExtra0, [
            'filemode' => $filemode,
        ]);
        $selfExtra2 = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                [
                    'from' => $bin,
                    'to' => $bin,
                    'filemode' => $filemode,
                ],
            ],
        ]);

        return [
            [self::DEFAULT_FILEMODE, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra0],
            [$filemode, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra1],
            [$filemode, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra2],
        ];
    }

    /**
     * @group full
     */
    public function testFull()
    {
        $filemode0 = '0411';
        $filemode1 = '0511';
        $filemode2 = '0755';
        $selfExtra = array_merge(self::getDefaultSelfExtra(), [
            'filemode' => $filemode0,
            'links' => [
                self::SUBDIR_0,
                [
                    'from' => 'subdir1',
                    'filemode' => $filemode1,
                ],
                [
                    'from' => self::BIN_1,
                    'to' => self::BIN_2,
                ],
                [
                    'from' => self::BIN_2,
                    'to' => self::BIN_1,
                    'filemode' => $filemode2,
                ],
            ],
        ]);
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . '1.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '2.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . '1.sh', $filemode1);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . '2.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '1.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . '2.sh', $filemode2);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . self::SUBDIR_0 . DIRECTORY_SEPARATOR . '3.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '3.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . self::SUBDIR_0 . DIRECTORY_SEPARATOR . '3.sh', $filemode0);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . self::SUBDIR_0 . DIRECTORY_SEPARATOR . '4.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '4.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . self::SUBDIR_0 . DIRECTORY_SEPARATOR . '4.sh', $filemode0);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . 'subdir1' . DIRECTORY_SEPARATOR . '5.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '5.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . 'subdir1' . DIRECTORY_SEPARATOR . '5.sh', $filemode1);
        $this->assertFileEquals(self::FROM_DIR . DIRECTORY_SEPARATOR . 'subdir1' . DIRECTORY_SEPARATOR . '6.sh', self::TO_DIR . DIRECTORY_SEPARATOR . '6.sh');
        $this->assertFileperms(self::FROM_DIR . DIRECTORY_SEPARATOR . 'subdir1' . DIRECTORY_SEPARATOR . '6.sh', $filemode1);
    }

    protected function assertFileperms(string $filename, string $filemode)
    {
        $this->assertSame(substr(sprintf('%o', fileperms($filename)), -4), $filemode);
    }

    /**
     * @param string $expectedFrom
     * @param string $expectedTo
     * @param array $selfExtra
     * @dataProvider dataProviderAliases
     */
    public function testAliases(string $expectedFrom, string $expectedTo, array $selfExtra)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new Event('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileEquals($expectedFrom, $expectedTo);
    }

    public function dataProviderAliases()
    {
        $from0 = self::BIN_1;
        $selfExtra0 = array_merge(self::getDefaultSelfExtra(true), [
            'links' => $from0,
        ]);
        return [
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from0, self::TO_DIR . DIRECTORY_SEPARATOR . $from0, $selfExtra0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->io = new NullIO();
        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);
        $this->package = new RootPackage('name', 'version', 'pretty-version');
        $this->composer->setPackage($this->package);
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        (new Filesystem())->remove(Finder::create()
            ->files()
            ->in(self::TO_DIR));
        (new Filesystem())->chmod(Finder::create()
            ->files()
            ->in(self::FROM_DIR), intval(self::DEFAULT_FILEMODE, 8));
    }

    protected static function getDefaultSelfExtra($useAliases = false)
    {
        return [
            $useAliases ? 'from' : 'from-dir' => self::FROM_DIR,
            $useAliases ? 'to' : 'to-dir' => self::TO_DIR,
        ];
    }

    /**
     * @param array|null $selfExtra
     * @return array
     *
     * Have to define some items from \Sensio\Bundle\DistributionBundle\Composer\ScriptHandler::$options
     * in order to avoid 'Undefined index' errors
     */
    protected function buildExtra(array $selfExtra = null)
    {
        return [
            'symfony-app-dir' => 'app',
            'symfony-web-dir' => 'web',
            'symfony-assets-install' => 'hard',
            'symfony-cache-warmup' => false,
            'evolaze-binary-symlink' => $selfExtra,
        ];
    }
}