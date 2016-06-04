<?php

namespace Evolaze\BinarySymlink;

use Composer\Script\Event;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as BaseScriptHandler;
use Symfony\Component\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class ScriptHandler extends BaseScriptHandler
{
    const NAME_SELF = 'evolaze-binary-symlink';
    const NAME_FROM_DIR = 'from-dir';
    const NAME_TO_DIR = 'to-dir';
    const NAME_FROM = 'from';
    const NAME_TO = 'to';
    const NAME_LINKS = 'links';

    protected static $DEFAULTS = [
        self::NAME_FROM_DIR => 'app',
        self::NAME_TO_DIR => 'bin',
    ];

    /** @var Filesystem */
    protected static $filesystem;

    /** @var Finder */
    protected static $finder;

    /** @var array */
    protected static $options = [];

    /**
     * install app specific bin files if dev env required.
     *
     * @param Event $event
     */
    public static function installBinary(Event $event)
    {
        if ($event->isDevMode()) {
            $options = self::buildOptions(self::getOptions($event));
            self::processOptions($options);
        }
    }

    /**
     * @param array $options
     */
    protected static function processOptions(array $options)
    {
        foreach ($options[self::NAME_LINKS] as $link) {
            self::getFilesystem()
                ->symlink($link[self::NAME_FROM], $link[self::NAME_TO]);
        }
    }

    /**
     * @return Filesystem
     */
    protected static function getFilesystem()
    {
        if (!isset(self::$filesystem)) {
            self::$filesystem = new Filesystem();
        }

        return self::$filesystem;
    }

    /**
     * @return Finder
     */
    static protected function getFinder()
    {
        if (!isset(self::$finder)) {
            self::$finder = Finder::create();
        }

        return self::$finder;
    }

    /**
     * @param array $options
     *
     * @return array
     *
     * @throws RuntimeException in case of missed extra options
     */
    protected static function buildOptions(array $options)
    {
        $result = self::extractOptions($options);
        if (!isset($result[self::NAME_LINKS])) {
            throw new RuntimeException('cannot find binary symlinks');
        }

        return $result;
    }

    /**
     * @param array $options
     * @return array|null
     */
    protected static function extractOptions(array $options)
    {
        $result = [];
        if (isset($options[self::NAME_SELF]) && is_array($options[self::NAME_SELF])) {
            $result[self::NAME_FROM_DIR] = self::extractOption($options[self::NAME_SELF], self::NAME_FROM_DIR);
            $result[self::NAME_TO_DIR] = self::extractOption($options[self::NAME_SELF], self::NAME_TO_DIR);
            $result[self::NAME_LINKS] = self::extractLinks(
                $options[self::NAME_SELF],
                $result[self::NAME_TO_DIR],
                $result[self::NAME_FROM_DIR]
            );
        }

        return $result;
    }

    /**
     * @param array $options
     * @param string $name
     * @return mixed mixed
     */
    protected static function extractOption(array $options, $name)
    {
        return isset($options[$name])
            ? $options[$name]
            : self::$DEFAULTS[$name];
    }

    /**
     * @param array $options
     * @param string $toDir
     * @param string $fromDir
     *
     * @return null | array
     */
    protected static function extractLinks(array $options, $toDir, $fromDir)
    {
        return isset($options[self::NAME_LINKS])
            && is_array($options[self::NAME_LINKS])
            ? self::buildLinks($options[self::NAME_LINKS], $toDir, $fromDir)
            : null;
    }

    /**
     * @param array $links
     * @param string $toDir
     * @param string $fromDir
     *
     * @return array
     */
    protected static function buildLinks(array $links, $toDir, $fromDir)
    {
        $result = [];
        $filesystem = self::getFilesystem();
        foreach ($links as $key => $link) {
            $link = !is_array($link)
                ? [self::NAME_FROM => $link]
                : $link;
            $from = self::buildLinkFrom($link, $fromDir);
            foreach ($from as $_from) {
                $_link = $link;
                $_link[self::NAME_FROM] = $_from;
                $to = self::buildLinkTo($_link, $toDir);
                $_from = $filesystem->makePathRelative($fromDir, dirname($to)) . $_from;
                $result[] = [
                    self::NAME_FROM => $_from,
                    self::NAME_TO => $to,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $link
     * @param string $toDir
     *
     * @return string
     */
    static protected function buildLinkTo(array $link, $toDir)
    {
        $to = $toDir . DIRECTORY_SEPARATOR;
        $to .= isset($link[self::NAME_TO])
            ? $link[self::NAME_TO]
            : basename($link[self::NAME_FROM]);

        return $to;
    }

    /**
     * @param array $link
     * @param string $fromDir
     *
     * @return array
     */
    static protected function buildLinkFrom(array $link, $fromDir)
    {
        if (is_dir($fromDir . DIRECTORY_SEPARATOR . $link[self::NAME_FROM])) {
            $from = self::scanSubdir($fromDir, $link[self::NAME_FROM]);
        } else {
            $from = [
                $link[self::NAME_FROM]
            ];
        }

        return $from;
    }

    /**
     * @param string $dir
     * @param string $subdir
     * @return array
     */
    static protected function scanSubdir($dir, $subdir)
    {
        $result = [];
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        $files = self::getFinder()->files()
            ->in($dir . DIRECTORY_SEPARATOR . $subdir);
        foreach ($files as $file) {
            $result[] = $subdir . DIRECTORY_SEPARATOR . $file->getRelativePathname();
        }

        return $result;
    }
}