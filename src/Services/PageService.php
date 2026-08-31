<?php

namespace DeVy\Modules\Pages\Services;

use DeVy\Core\Services\PathService;
use DeVy\Core\Persistence\JsonStore;
use DeVy\Core\Support\PathSanitizer;
use DeVy\Core\Support\PageFileHandler;

class PageService
{
    protected PathService $paths;
    protected PathSanitizer $sanitizer;
    protected PageFileHandler $files;

    public function __construct(
        PathService $paths,
        PathSanitizer $sanitizer,
        PageFileHandler $files
    ) {
        $this->paths = $paths;
        $this->sanitizer = $sanitizer;
        $this->files = $files;
    }

    private function pagesPath(string $path = ''): string
    {
        return $this->paths->content('pages/' . trim($path, '/'));
    }

    public function exists(string $path): bool
    {
        $path = $this->sanitizer->clean($path);
        return is_dir($this->pagesPath($path));
    }

    public function all(): array
    {
        $base = $this->pagesPath();
        $result = [];

        if (!is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {

                $fullPath = $file->getPathname();

                // convert to relative path
                $relative = str_replace($base, '', $fullPath);
                $relative = trim(str_replace('\\', '/', $relative), '/');

                if ($relative === '') continue;

                // VALID PAGE = has meta.json
                if (file_exists($fullPath . '/meta.json')) {
                    $page = $this->get($relative);

                    if ($page) {
                        $result[$relative] = $page;
                    }
                }
            }
        }

        return $result;
    }

    public function get(string $path): ?array
    {
        $path = $this->sanitizer->clean($path);
        $dir = $this->pagesPath($path);

        if (!is_dir($dir)) return null;

        $store  = new JsonStore($dir . '/meta.json');
        $meta   = $store->all();

        $store  = new JsonStore($dir . '/fields.json');
        $fields = $store->all();

        $meta['slug'] = $meta['slug'] ?? basename($path);
        $meta['bind'] = $meta['bind'] ?? '/' . $path;

        return [
            'meta' => $meta,
            'body' => $this->files->read($dir . '/body.html'),
            'fields' => $fields,
            'path' => $path,
            'slug' => basename($path),
            'bind' => $meta['bind']
        ];
    }

    public function list(string $path = ''): array
    {
        $dir = $this->pagesPath($path);
        if (!is_dir($dir)) return [];

        $items = [];

        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {

            $dirSlug = basename($subDir);
            $fullPath = trim($path . '/' . $dirSlug, '/');

            $store = new JsonStore($subDir . '/meta.json');
            $meta = $store->all();

            $items[] = [
                'slug' => $meta['slug'] ?? $dirSlug,
                'path' => $fullPath,
                'bind' => $meta['bind'] ?? '/' . $fullPath,
                'title' => $meta['title'] ?? $dirSlug,
                'description' => $meta['description'] ?? '',
                'type' => count(glob($subDir . '/*', GLOB_ONLYDIR) ?: []) > 0 ? 'folder' : 'page'
            ];
        }

        return $items;
    }

    public function save(string $path, array $meta, string $body): bool
    {
        $path = $this->sanitizer->clean($path);

        if (!$this->sanitizer->isValid($path)) {
            return false;
        }

        $dir = $this->pagesPath($path);

        if (!$this->files->ensureDirectory($dir)) {
            return false;
        }

        // enforce consistency
        $meta['slug'] = basename($path);
        $meta['bind'] = '/' . $path;

        $body = $this->sanitizeHtml($body);

        $store = new JsonStore($dir . '/meta.json');

        return $store->save($meta)
            && $this->files->write($dir . '/body.html', $body);
    }

    public function delete(string $path): bool
    {
        $path = $this->sanitizer->clean($path);
        $dir  = $this->pagesPath($path);

        if (!is_dir($dir)) {
            return false;
        }

        // 🔥 BLOCK DELETE IF HAS CHILDREN
        if ($this->hasChildren($dir)) {
            return false;
        }

        return $this->files->deleteDirectory($dir);
    }

    /*
    public function getCachePath(string $path): string
    {
        $path = trim($path, '/');
        $path = $path === '' ? 'home' : $path;

        $dir = $this->paths->ensureDir(
            $this->paths->public() . '/cache/pages'
        );

        return $dir . '/' . $path . '.html';
    }

    public function clearCache(string $path): void
    {
        $file = $this->getCachePath($path);

        if (file_exists($file)) {
            unlink($file);
        }
    }*/

    protected function sanitizeHtml(string $html): string
    {
        static $purifier = null;

        if ($purifier === null) {

            $config = \HTMLPurifier_Config::createDefault();

            $config->set('HTML.DefinitionID', 'rnd-html-def');
            $config->set('HTML.DefinitionRev', 1);

            $config->set(
                'Cache.SerializerPath',
                $this->paths->ensureWritable($this->paths->cache() . '/htmlpurifier')
            );

            $config->set('HTML.Allowed', implode(',', [
                'p,b,strong,i,em,u',
                'h1,h2,h3,h4,h5,h6',
                'ul,ol,li',
                'a[href|title|target|rel]',
                'img[src|alt|title|width|height|loading]',
                'blockquote,code,pre,br,hr',
                'table[border|cellpadding|cellspacing]',
                'thead,tbody,tfoot',
                'tr',
                'td[colspan|rowspan]',
                'th[colspan|rowspan]',
                'rnd-media[type|src|alt|title]'
            ]));

            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ]);

            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.SafeIframe', false);
            $config->set('HTML.Nofollow', true);

            $config->set('HTML.Trusted', true);

            // 🔥 ONLY MODIFY WHEN RAW DEFINITION IS AVAILABLE
            $def = $config->maybeGetRawHTMLDefinition();

            if ($def) {

                $def->addElement(
                    'rnd-media',
                    'Inline',
                    'Optional: Inline',
                    'Common',
                    [
                        'type'  => 'Text',
                        'src'   => 'URI',
                        'alt'   => 'Text',
                        'title' => 'Text',
                    ]
                );

                $def->addAttribute('img', 'loading', 'Text');
            }

            $purifier = new \HTMLPurifier($config);
        }

        return $purifier->purify($html);
    }

    private function hasChildren(string $dir): bool
    {
        $items = array_diff(scandir($dir), ['.', '..']);

        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {

            $full = $dir . '/' . $item;

            // if folder OR valid page folder → block delete
            if (is_dir($full)) {
                return true;
            }
        }

        return false;
    }

}