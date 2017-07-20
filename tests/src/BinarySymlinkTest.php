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
    /** @var Composer */
    protected $composer;

    /** @var BaseIO */
    protected $io;

    /** @var Config */
    protected $config;

    /** @var RootPackage */
    protected $package;

    protected const BIN = 'bin';
    protected const OTHER_BIN = 'other-bin';
    protected const SUBDIR_BIN = 'subdir-bin';
    protected const OTHER_SUBDIR_BIN = 'other-subdir-bin';
    protected const BINS = [
        self::BIN => 'bin.sh',
        self::OTHER_BIN => 'other-bin.sh',
        self::SUBDIR_BIN => 'subdir' . DIRECTORY_SEPARATOR . 'subdir-bin.sh',
        self::OTHER_SUBDIR_BIN => 'subdir' . DIRECTORY_SEPARATOR . 'subdir-other-bin.sh',
    ];
    protected const FROM_DIR = 'tests' . DIRECTORY_SEPARATOR . 'from';
    protected const TO_DIR = 'tests' . DIRECTORY_SEPARATOR . 'to';

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
        $selfExtra = [
            'from-dir' => self::FROM_DIR,
            'to-dir' => self::TO_DIR,
        ];
        $selfExtra0 = $selfExtra;
        $from0 = self::BINS[self::BIN];
        $selfExtra0['links'] = [
            $from0,
        ];
        $from1 = self::BINS[self::BIN];
        $to1 = self::BINS[self::OTHER_BIN];
        $selfExtra1 = $selfExtra;
        $selfExtra1['links'] = [
            $from1 => $to1,
        ];
        $selfExtra2 = $selfExtra;
        $from2 = self::BINS[self::OTHER_BIN];
        $to2 = self::BINS[self::BIN];
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
        $selfExtra = [
            'from-dir' => self::FROM_DIR,
            'to-dir' => self::TO_DIR,
            'links' => [
                'subdir',
            ],
        ];

        return [
            [2, $selfExtra, $selfExtra['to-dir']],
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