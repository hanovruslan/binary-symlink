<?php

namespace Evolaze\BinarySymlink;

use Composer\Script\Event;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as BaseScriptHandler;
use Symfony\Component\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class ScriptHandler extends BaseScriptHandler
{
    protected const NAME_SELF = 'evolaze-binary-symlink';
    protected const NAME_FROM_DIR = 'from-dir';
    protected const NAME_TO_DIR = 'to-dir';
    protected const NAME_FROM = 'from';
    protected const NAME_TO = 'to';
    protected const NAME_LINKS = 'links';
    protected const DEFAULTS = [
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
    protected static function getFinder()
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
            throw new RuntimeException('cannot find links options');
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
     * @return mixed
     */
    protected static function extractOption(array $options, string $name)
    {
        return isset($options[$name])
            ? $options[$name]
            : self::DEFAULTS[$name];
    }

    /**
     * @param array $options
     * @param string $toDir
     * @param string $fromDir
     *
     * @return null | array
     */
    protected static function extractLinks(array $options, string $toDir, string $fromDir)
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
    protected static function buildLinks(array $links, string $toDir, string $fromDir)
    {
        $result = [];
        $filesystem = self::getFilesystem();
        foreach ($links as $rawFrom => $rawTo) {
            $rawLink = is_array($rawTo) ? $rawTo : [
                self::NAME_FROM => !is_int($rawFrom) ? $rawFrom : $rawTo,
                self::NAME_TO => $rawTo,
            ];
            foreach (self::resolveLinkFrom($rawLink, $fromDir) as $from) {
                $link = $rawLink;
                $link[self::NAME_FROM] = $from;
                $to = self::resolveLinkTo($link, $toDir, $fromDir);
                $from = $filesystem->makePathRelative($fromDir, dirname($to)) . $from;
                $result[] = [
                    self::NAME_FROM => $from,
                    self::NAME_TO => $to,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $link
     * @param string $toDir
     * @param string $fromDir
     *
     * @return string
     */
    protected static function resolveLinkTo(array $link, string $toDir, string $fromDir)
    {
        $to = $toDir . DIRECTORY_SEPARATOR;
        $to .= isset($link[self::NAME_TO]) && !is_dir($fromDir . DIRECTORY_SEPARATOR . $link[self::NAME_TO])
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
    protected static function resolveLinkFrom(array $link, string $fromDir)
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
    protected static function scanSubdir(string $dir, string $subdir)
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