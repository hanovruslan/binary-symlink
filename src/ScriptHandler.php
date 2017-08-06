<?php

namespace Evolaze\BinarySymlink;

use Composer\Script\Event;
use RuntimeException;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as BaseScriptHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ScriptHandler extends BaseScriptHandler
{
    protected const NAME_SELF = 'evolaze-binary-symlink';
    protected const NAME_FROM_DIR = 'from-dir';
    protected const NAME_TO_DIR = 'to-dir';
    protected const NAME_FROM = 'from';
    protected const NAME_TO = 'to';
    protected const NAME_LINKS = 'links';
    protected const NAME_FILEMODE = 'filemode';
    protected const NAME_USE_ROOT_AS_FROM_DIR = 'use-root';
    protected const DEFAULTS = [
        self::NAME_FROM_DIR => 'app',
        self::NAME_TO_DIR => 'bin',
        self::NAME_FILEMODE => '0644',
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
            $options = self::resolveOptions(self::getOptions($event));
            self::processOptions($options);
        }
    }

    /**
     * @param array $options
     */
    protected static function processOptions(array $options)
    {

        foreach ($options[self::NAME_LINKS] as $link) {
            if (null !== $link[self::NAME_FILEMODE]) {
                self::getFilesystem()->chmod(
                    realpath(dirname($link[self::NAME_TO]) . DIRECTORY_SEPARATOR . $link[self::NAME_FROM]),
                    intval($link[self::NAME_FILEMODE], 8)
                );
            }
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
     * @TODO fix inner cache of founded items
     */
    protected static function getFinder()
    {
        if (true || !isset(self::$finder)) {
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
    protected static function resolveOptions(array $options)
    {
        $result = self::_resolveOptions($options);
        if (!isset($result[self::NAME_LINKS])) {
            throw new RuntimeException('cannot find links options');
        }

        return $result;
    }

    /**
     * @param array $options
     * @return array|null
     */
    protected static function _resolveOptions(array $options)
    {
        $result = [];
        if (isset($options[self::NAME_SELF]) && is_array($options[self::NAME_SELF])) {
            $result[self::NAME_USE_ROOT_AS_FROM_DIR] = self::extractOption($options[self::NAME_SELF], self::NAME_USE_ROOT_AS_FROM_DIR);
            $result[self::NAME_FROM_DIR] = self::extractOption($options[self::NAME_SELF], self::NAME_FROM_DIR);
            $result[self::NAME_TO_DIR] = self::buildRealpath(self::extractOption($options[self::NAME_SELF], self::NAME_TO_DIR), $result[self::NAME_USE_ROOT_AS_FROM_DIR]);
            $result[self::NAME_FILEMODE] = self::extractOption($options[self::NAME_SELF], self::NAME_FILEMODE);
            $result[self::NAME_LINKS] = self::extractLinks(
                $options[self::NAME_SELF],
                $result[self::NAME_TO_DIR],
                $result[self::NAME_FROM_DIR],
                $result[self::NAME_FILEMODE],
                $result[self::NAME_USE_ROOT_AS_FROM_DIR]
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
     * @param string|null $filemode
     * @param string|null $useRoot
     *
     * @return array|null
     */
    protected static function extractLinks(
        array $options,
        string $toDir,
        string $fromDir,
        string $filemode = null,
        string $useRoot = null
    ) {
        $result = null;
        if (isset($options[self::NAME_LINKS])) {
            $options[self::NAME_LINKS] = is_array($options[self::NAME_LINKS])
                ? $options[self::NAME_LINKS]
                : [$options[self::NAME_LINKS]];
            $result = self::resolveLinks($options[self::NAME_LINKS], $toDir, $fromDir, $filemode, $useRoot);
        }

        return $result;
    }

    /**
     * @param array $rawLinks
     * @param string $toDir
     * @param string $fromDir
     * @param string|null $filemode
     * @param string|null $useRoot
     *
     * @return array
     */
    protected static function resolveLinks(
        array $rawLinks,
        string $toDir,
        string $fromDir,
        string $filemode = null,
        string $useRoot = null
    ) {
        $result = [];
        foreach ($rawLinks as $rawFrom => $rawTo) {
            $rawLink = is_array($rawTo) ? $rawTo : [
                self::NAME_FROM => !is_int($rawFrom) ? $rawFrom : $rawTo,
                self::NAME_TO => basename($rawTo),
            ];
            foreach (self::resolveLinkFromAndTo($rawLink, $fromDir, $toDir, $useRoot) as $from => $to) {
                $link = $rawLink;
                $link[self::NAME_FROM] = $from;

                $link =  [
                    self::NAME_FROM => self::buildRelative($from, $to),
                    self::NAME_TO => $to,
                ];
                if ($filemode = isset($rawTo[self::NAME_FILEMODE]) ? $rawTo[self::NAME_FILEMODE] : $filemode) {
                    $link[self::NAME_FILEMODE] = $filemode;
                }
                $result[] = $link;
            }
        }

        return $result;
    }

    /**
     * @param string $from
     * @param string $to
     * @return string
     */
    protected static function buildRelative(string $from, string $to)
    {
        return self::getFilesystem()->makePathRelative(dirname($from), dirname($to)) . basename($from);
    }

    /**
     * @param array $link
     * @param string $fromDir
     * @param string $toDir
     * @param string|null $useRoot
     *
     * @return array
     */
    protected static function resolveLinkFromAndTo(array $link, string $fromDir, string $toDir, string $useRoot = null)
    {
        $fromDir = $useRoot ? self::buildRealpath(DIRECTORY_SEPARATOR, $useRoot) : $fromDir;
        $result = [];
        if (is_dir($fromDir . DIRECTORY_SEPARATOR . $link[self::NAME_FROM])) {
            foreach (self::scanSubdir($fromDir, $link[self::NAME_FROM]) as $from) {
                $result = array_merge_recursive($result, [
                    $fromDir . DIRECTORY_SEPARATOR . $from => $toDir . DIRECTORY_SEPARATOR . basename($from)
                ]);
            }
        } else {
            $result = array_merge_recursive($result, [
                $fromDir . DIRECTORY_SEPARATOR . $link[self::NAME_FROM] => $toDir . DIRECTORY_SEPARATOR . $link[self::NAME_TO]
            ]);
        }

        return $result;
    }

    /**
     * @param string $relativePath
     * @param string|null $useRoot
     * @return string
     */
    protected static function buildRealpath(string $relativePath, string $useRoot = null)
    {
        return rtrim($useRoot ? getcwd() . DIRECTORY_SEPARATOR . $relativePath : $relativePath, '/');
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