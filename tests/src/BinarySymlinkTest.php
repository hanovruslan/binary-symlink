<?php

namespace Evolaze\BinarySymlink\Test;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BaseIO;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Evolaze\BinarySymlink\ScriptHandler;
use PHPUnit_Framework_TestCase;
use RuntimeException;
use Composer\Script\CommandEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class BinarySymlinkTest extends PHPUnit_Framework_TestCase
{
    protected const FROM_DIR = 'tests' . DIRECTORY_SEPARATOR . 'from';
    protected const TO_DIR = 'tests' . DIRECTORY_SEPARATOR . 'to';
    protected const BIN = 'bin.sh';
    protected const FILEMODE_BIN = 'filemode-bin.sh';
    protected const OTHER_BIN = 'other-bin.sh';
    protected const OTHER_SUBDIR_BIN = 'subdir' . DIRECTORY_SEPARATOR . 'subdir-other-bin.sh';
    protected const SUBDIR_BIN = 'subdir' . DIRECTORY_SEPARATOR . 'subdir-bin.sh';
    protected const FILEMODE = '0644';

    /** @var Composer */
    protected $composer;

    /** @var BaseIO */
    protected $io;

    /** @var Config */
    protected $config;

    /** @var RootPackage */
    protected $package;

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
            ->in(self::FROM_DIR)
            ->name(self::FILEMODE_BIN), intval(self::FILEMODE, 8));
    }

    public function testDoNothingInNotDevMode()
    {
        $event = new CommandEvent('name', $this->composer, $this->io);
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
        $event = new CommandEvent('name', $this->composer, $this->io, true);
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
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileEquals($expectedFrom, $expectedTo);
    }

    public function dataProviderInstall()
    {
        $selfExtra0 = self::getDefaultSelfExtra();
        $from0 = self::BIN;
        $selfExtra0['links'] = [
            $from0,
        ];
        $from1 = self::BIN;
        $to1 = self::OTHER_BIN;
        $selfExtra1 = self::getDefaultSelfExtra();
        $selfExtra1['links'] = [
            $from1 => $to1,
        ];
        $selfExtra2 = self::getDefaultSelfExtra();
        $from2 = self::OTHER_BIN;
        $to2 = self::BIN;
        $selfExtra2['links'][] = [
            'from' => $from2,
            'to' => $to2,
        ];
        return [
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from0, self::TO_DIR . DIRECTORY_SEPARATOR . $from0, $selfExtra0],
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from1, self::TO_DIR . DIRECTORY_SEPARATOR . $to1, $selfExtra1],
            [self::FROM_DIR . DIRECTORY_SEPARATOR . $from2, self::TO_DIR . DIRECTORY_SEPARATOR . $to2, $selfExtra2],
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
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $files = Finder::create()->files()
            ->in($to);
        $this->assertEquals($expected, $files->count());
    }

    public function dataProviderScanDir()
    {
        $selfExtra = array_merge(self::getDefaultSelfExtra(), [
            'links' => [
                'subdir',
            ],
        ]);

        return [
            [2, $selfExtra, $selfExtra['to-dir']],
        ];
    }

    /**
     * @param string $expected
     * @param string $file
     * @param array $selfExtra
     * @dataProvider dataProviderFilemode
     */
    public function testFilemode(string $expected, string $file, array $selfExtra) {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertSame(substr(sprintf('%o', fileperms($file)), -4), $expected);
    }

    public function dataProviderFilemode()
    {
        $bin = self::FILEMODE_BIN;
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
            [self::FILEMODE, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra0],
            [$filemode, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra1],
            [$filemode, self::FROM_DIR . DIRECTORY_SEPARATOR . $bin, $selfExtra2],
        ];
    }

    protected static function getDefaultSelfExtra() {
        return [
            'from-dir' => self::FROM_DIR,
            'to-dir' => self::TO_DIR,
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