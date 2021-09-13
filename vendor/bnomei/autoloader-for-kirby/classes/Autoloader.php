<?php

declare(strict_types=1);

namespace Bnomei;

use Spyc;
use Symfony\Component\Finder\Finder;

final class Autoloader
{
    // exclude files like filename.config.(php|yml)
    public const PHP = '/^[\w\d\-\_]+\.php$/';
    public const ANY_PHP = '/^[\w\d\-\_\.]+\.php$/';
    public const PAGE_PHP = '/^[\w\d\-\_]+(Page)\.php$/';
    public const USER_PHP = '/^[\w\d\-\_]+(User)\.php$/';
    public const YML = '/^[\w\d\-\_]+\.yml$/';
    public const PHP_OR_HTMLPHP = '/^[\w\d\-\_]+(\.html)?\.php$/';
    public const PHP_OR_YML = '/^[\w\d\-\_]+\.(php|yml)$/';
    public const PHP_OR_YML_OR_JSON = '/^[\w\d\-\_]+\.(php|yml|json)$/';

    /** @var self */
    private static $singleton;

    /** @var array */
    private $options;

    /** @var array */
    private $registry;

    public function __construct(array $options = [])
    {
        $this->options = array_merge_recursive([
            'blueprints' => [
                'folder' => 'blueprints',
                'name' => static::PHP_OR_YML,
                'key' => 'relativepath',
                'require' => false,
                'lowercase' => true,
            ],
            'classes' => [
                'folder' => 'classes',
                'name' => static::PHP,
                'key' => 'classname',
                'require' => false,
                'lowercase' => true,
                'map' => [],
            ],
            'collections' => [
                'folder' => 'collections',
                'name' => static::PHP,
                'key' => 'relativepath',
                'require' => true,
                'lowercase' => false,
            ],
            'controllers' => [
                'folder' => 'controllers',
                'name' => static::ANY_PHP,
                'key' => 'filename',
                'require' => true,
                'lowercase' => true,
            ],
            'pagemodels' => [
                'folder' => 'models',
                'name' => static::PAGE_PHP,
                'key' => 'classname',
                'require' => false,
                'lowercase' => true,
                'map' => [],
            ],
            'usermodels' => [
                'folder' => 'models',
                'name' => static::USER_PHP,
                'key' => 'classname',
                'require' => false,
                'lowercase' => true,
                'map' => [],
            ],
            'snippets' => [
                'folder' => 'snippets',
                'name' => static::PHP_OR_HTMLPHP,
                'key' => 'relativepath',
                'require' => false,
                'lowercase' => false,
            ],
            'templates' => [
                'folder' => 'templates',
                'name' => static::ANY_PHP,
                'key' => 'filename',
                'require' => false,
                'lowercase' => true,
            ],
        	'translations' => [
        		'folder' => 'translations',
        		'name' => static::PHP_OR_YML_OR_JSON,
        		'key' => 'filename',
        		'require' => true,
                'lowercase' => true,
        	],
        ], $options);

        if (!array_key_exists('dir', $this->options)) {
            throw new \Exception("Autoloader needs a directory to start scanning at.");
        }

        $this->registry = [];
    }

    public function dir(): string
    {
        return $this->options['dir'];
    }

    private function registry(string $type): array
    {
        // only register once
        if (array_key_exists($type, $this->registry)) {
            return $this->registry[$type];
        }

        $options = $this->options[$type];
        $dir = $this->options['dir'] . '/' . $options['folder'];
        if (!file_exists($dir) || !is_dir($dir)) {
            return [];
        }

        $this->registry[$type] = [];
        $finder = (new Finder())->files()
            ->name($options['name'])
            ->in($dir);

        foreach ($finder as $file) {
            $key = '';
            $class = '';
            $split = explode('.', $file->getPathname());
            $extension = array_pop($split);
            if ($options['key'] === 'relativepath') {
                $key = $file->getRelativePathname();
                $key = str_replace('.' . $extension, '', $key);
                if ($options['lowercase']) {
                    $key = strtolower($key);
                }
            } elseif ($options['key'] === 'filename') {
                $key = basename($file->getRelativePathname());
                $key = str_replace('.' . $extension, '', $key);
                if ($options['lowercase']) {
                    $key = strtolower($key);
                }
            } elseif ($options['key'] === 'classname') {
                $key = $file->getRelativePathname();
                $key = str_replace('.' . $extension, '', $key);
                $class = str_replace('/', '\\', $key);
                if ($classFile = file_get_contents($file->getPathname())) {
                    if (preg_match('/^namespace (.*);$/im', $classFile, $matches) === 1) {
                        $class = str_replace($matches[1] . '\\', '', $class);
                        $class = $matches[1] . '\\' . $class;
                    }    
                }
                $this->registry[$type]['map'][$class] = $file->getRelativePathname();
                
                foreach(['Page', 'User'] as $suffix) {
                    $at = strpos($key, $suffix);
                    if ($at === strlen($key) - strlen($suffix)) {
                        $key = substr($key, 0, -strlen($suffix));
                    }
                }
                if ($options['lowercase']) {
                    $key = strtolower($key);
                }
                $this->registry[$type][$key] = $class;
            }
            if (empty($key)) {
                continue;
            } else {
                $key = strval($key); // in case key looks like a number but should be a string
            }
            
            if ($options['key'] === 'classname') {
                $this->registry[$type][$key] = $class;
            } elseif ($options['require'] && $extension && strtolower($extension) === 'php') {
                $path = $file->getPathname();
                $this->registry[$type][$key] = require_once $path;
            } elseif ($options['require'] && $extension && strtolower($extension) === 'json') {
                $path = $file->getPathname();
                $this->registry[$type][$key] = json_decode(file_get_contents($path), true);
            } elseif ($options['require'] && $extension && strtolower($extension) === 'yml') {
                $path = $file->getPathname();
                 // remove BOM
                $yaml = str_replace("\xEF\xBB\xBF", '', file_get_contents($path));
                $this->registry[$type][$key] = Spyc::YAMLLoadString($yaml);
            } else {
                $this->registry[$type][$key] = $file->getRealPath();
            }
        }

        if ($options['key'] === 'classname' && array_key_exists('map', $this->registry[$type])) {
            // sort by \ in FQCN count desc
            // within same count sort alpha
            $map = array_flip($this->registry[$type]['map']);
            uasort($map, function($a, $b) {
                $ca = substr_count($a, '\\');
                $cb = substr_count($b, '\\');
                if ($ca === $cb) {
                    $alpha = [$a, $b];
                    sort($alpha);
                    return $alpha[0] === $a ? -1 : 1;
                }
                return $ca < $cb ? 1 : -1;
                
            });
            $map = array_flip($map);
            $this->load($map, $this->options['dir'] . '/' . $options['folder']);
            unset($this->registry[$type]['map']);
        }

        return $this->registry[$type];
    }

    public function blueprints(): array
    {
        return $this->registry('blueprints');
    }

    public function classes(string $folder = null): array
    {
        if ($folder) {
            $this->options['classes']['folder'] = $folder;
        }
        return $this->registry('classes');
    }

    public function collections(): array
    {
        return $this->registry('collections');
    }

    public function controllers(): array
    {
        return $this->registry('controllers');
    }

    public function pageModels(): array
    {
        return $this->registry('pagemodels');
    }

    public function userModels(): array
    {
        return $this->registry('usermodels');
    }

    public function snippets(): array
    {
        return $this->registry('snippets');
    }

    public function templates(): array
    {
        return $this->registry('templates');
    }

    public function translations(): array
    {
        return $this->registry('translations');
    }

    public static function singleton(array $options = []): self
    {
        if (self::$singleton && self::$singleton->dir() === $options['dir']) {
            return self::$singleton;
        }
        self::$singleton = new self($options);
        return self::$singleton;
    }

    // https://github.com/getkirby/kirby/blob/c77ccb82944b5fa0e3a453b4e203bd697e96330d/config/helpers.php#L505
    /**
     * A super simple class autoloader
     *
     * @param array $classmap
     * @param string $base
     * @return void
     */
    private function load(array $classmap, string $base = null)
    {
        // convert all classnames to lowercase
        $classmap = array_change_key_case($classmap);

        spl_autoload_register(function ($class) use ($classmap, $base) {
            $class = strtolower($class);

            if (!isset($classmap[$class])) {
                return false;
            }

            if ($base) {
                include $base . '/' . $classmap[$class];
            } else {
                include $classmap[$class];
            }
        });
    }
}
