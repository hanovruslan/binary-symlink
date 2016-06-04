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
            ->in('tests/to'));
    }

    public function testDoNothingInNotDevMode()
    {
        $event = new CommandEvent('name', $this->composer, $this->io);
        ScriptHandler::installBinary($event);
    }

    /**
     * @param array $selfExtra
     *
     * @dataProvider dataFailedOnMissedExtra
     */
    public function testFailedOnMissedExtra(array $selfExtra = null)
    {
        $this->setExpectedException(RuntimeException::class, 'cannot find binary symlinks');
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
    }

    public function dataFailedOnMissedExtra()
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
     * @param string $expected
     * @param array|null $selfExtra
     * @dataProvider dataExtra
     */
    public function testInstall($expected, $selfExtra)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $this->assertFileExists($expected);
    }

    public function dataExtra()
    {
        $to = 'tests/to';
        $selfExtra = [
            'from-dir' => 'tests/from',
            'to-dir' => $to,
            'links' => [],
        ];
        $expected0 = 'bin.sh';
        $expected1 = 'foo.sh';
        $expected2 = 'sdb.sh';
        $selfExtra0 = $selfExtra;
        $selfExtra0['links'][] = [
            'from' => $expected0,
        ];
        $selfExtra1 = $selfExtra;
        $selfExtra1['links'][] = [
            'from' => 'bin.sh',
            'to' => $expected1,
        ];
        $selfExtra2 = $selfExtra;
        $selfExtra2['links'][] = [
            'from' => 'subdir/subdir-bin.sh',
            'to' => $expected2,
        ];

        return [
            [$to . DIRECTORY_SEPARATOR . $expected0, $selfExtra0],
            [$to . DIRECTORY_SEPARATOR . $expected1, $selfExtra1],
            [$to . DIRECTORY_SEPARATOR . $expected2, $selfExtra2],
        ];
    }

    /**
     * @param int $expected
     * @param array $selfExtra
     * @param string $to
     * @dataProvider dataScanDir
     */
    public function testScanDir($expected, array $selfExtra, $to)
    {
        $this->package->setExtra($this->buildExtra($selfExtra));
        $event = new CommandEvent('name', $this->composer, $this->io, true);
        ScriptHandler::installBinary($event);
        $files = Finder::create()->files()
            ->in($to);
        $this->assertEquals($expected, $files->count());
    }

    public function dataScanDir()
    {
        $selfExtra = [
            'from-dir' => 'tests/from',
            'to-dir' => 'tests/to',
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
     */
    protected function buildExtra($selfExtra)
    {
        return [
            'symfony-assets-install' => 'relative',
            'evolaze-binary-symlink' => $selfExtra,
        ];
    }
}