<?php

namespace Bnomei;

use Kirby\Filesystem\Dir;
use Kirby\Toolkit\A;
use ReflectionClass;

class BoostDirInventory
{
    private ?array $data;

    private bool $enabled;

    private bool $isDirty;

    public function __construct(array $options = [])
    {
        $this->enabled = A::get($options, 'enabled', true);
        $this->isDirty = false;
        $this->data = [];

        if ($this->enabled) {
            self::patchDirClass();

            if (file_exists($this->file())) {
                // $this->data = file_exists($this->file()) ? json_decode(file_get_contents($this->file()), true) : [];
                $this->data = include $this->file();
            }
        }
    }

    public function __destruct()
    {
        if (! $this->isDirty || ! $this->enabled) {
            return;
        }

        // file_put_contents($this->file(), json_encode($this->data, JSON_PRETTY_PRINT));
        file_put_contents(
            $this->file(),
            '<?php'.PHP_EOL.' return '.var_export($this->data, true).';',
            LOCK_EX
        ) !== false;
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->file());
        }
    }

    public function file(): string
    {
        return __DIR__.'/../boost-dir-inventory.cache.php';
    }

    public function get($key): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        $key = $this->key($key);

        return A::get($this->data, $key);
    }

    public function set($key, ?array $input = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->isDirty = true;
        $key = $this->key($key);
        $this->data[$key] = $input;
    }

    public static function flush(): void
    {
        $instance = static::singleton();
        if (file_exists($instance->file())) {
            unlink($instance->file());
        }

        $instance->data = [];
        $instance->isDirty = true;
    }

    public static function singleton(array $options = []): self
    {
        static $instance;

        return $instance ?: $instance = new self($options);
    }

    private function key($key): string
    {
        return is_array($key) ? hash('xxh3', print_r($key, true)) : $key;
    }

    public static function patchDirClass(): void
    {
        $reflection = new ReflectionClass(Dir::class);
        $file = $reflection->getFileName();

        $content = file_get_contents($file);
        $head = <<<'CODE'
$items = static::read($dir, $contentIgnore);
CODE;

        $head_new = <<<'CODE'
$cacheKey = func_get_args();
        if ($cache = \Bnomei\BoostDirInventory::singleton()->get($cacheKey)) {
            return $cache;
        }
        $items = static::read($dir, $contentIgnore);
CODE;
        $foot = <<<'CODE'
return $inventory;
	}
CODE;
        $foot_new = <<<'CODE'
\Bnomei\BoostDirInventory::singleton()->set($cacheKey, $inventory);

		return $inventory;
	}
CODE;
        if (strpos($content, $head_new) !== false) {
            return;
        }
        $content = str_replace($head, $head_new, $content);
        $content = str_replace($foot, $foot_new, $content);
        file_put_contents($file, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file);
        }
    }
}
