<?php

declare(strict_types=1);

namespace DeVy\Modules\Pages\Services;

use DeVy\Core\Contracts\Store\StoreInterface;
use DeVy\Core\Persistence\JsonStore;
use DeVy\Core\Schema\SchemaDefaults;
use DeVy\Core\Services\HookManager;
use DeVy\Core\Services\PathService;
use DeVy\Core\Services\SettingsService;
use DeVy\Core\Support\PathSanitizer;
use DeVy\Core\Support\PageFileHandler;

class PageBuilderService implements StoreInterface
{
    protected PathService $paths;
    protected SettingsService $settings;
    protected PathSanitizer $sanitizer;
    protected PageFileHandler $files;
    protected PageService $pages;
    protected HookManager $hooks;

    protected ?JsonStore $store = null;

    protected string $path = '';

    public function __construct(
        PathService $paths,
        SettingsService $settings,
        PathSanitizer $sanitizer,
        PageFileHandler $files,
        PageService $pages,
        HookManager $hooks
    ) {
        $this->paths = $paths;
        $this->settings = $settings;
        $this->sanitizer = $sanitizer;
        $this->files = $files;
        $this->pages = $pages;
        $this->hooks = $hooks;

        /*
        |--------------------------------------------------------------------------
        | PAGE BUILDER SCHEMA HOOK
        |--------------------------------------------------------------------------
        */

        $this->hooks->add(
            'pages.builder.schema',
            function (array $schema): array {

                $page = $this->getPage();

                if (!$page) {
                    return $schema;
                }

                $pageSchema = $this->schema($page);

                return is_array($pageSchema)
                    ? $pageSchema
                    : $schema;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD PAGE
    |--------------------------------------------------------------------------
    */

    public function load(string $path): ?array
    {
        $path = $this->sanitizer->clean($path);

        $page = $this->pages->get($path);

        if (!$page) {

            $this->store = null;
            $this->path = '';

            return null;
        }

        $this->path = $path;

        $this->store = new JsonStore(
            $this->fieldsPath($path)
        );

        /*
        |--------------------------------------------------------------------------
        | APPLY DEFAULTS
        |--------------------------------------------------------------------------
        */

        $schema = $this->schema($page);

        if (is_array($schema)) {

            (new SchemaDefaults)->apply(
                $this->store,
                $schema
            );
        }

        return [
            'path' => $path,
            'page' => $page,
            'view' => $this->getView($page),
            'schema_path' => $this->schemaPath($page),
            'schema' => $schema,
            'fields' => $this->store->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PAGE
    |--------------------------------------------------------------------------
    */

    public function getPage(): ?array
    {
        if ($this->path === '') {
            return null;
        }

        return $this->pages->get($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function getView(array $page): string
    {
        return trim(
            (string)($page['meta']['view'] ?? '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCHEMA
    |--------------------------------------------------------------------------
    */

    public function schemaPath(array $page): ?string
    {
        $view = $this->getView($page);

        if ($view === '') {
            return null;
        }

        $theme = $this->settings->get(
            'template.theme',
            'default'
        );

        $path = $this->paths->themeSchemas($theme)
            . '/'
            . $view
            . '.php';

        return is_file($path)
            ? $path
            : null;
    }

    public function schema(array $page): ?array
    {
        $path = $this->schemaPath($page);

        if (!$path) {
            return null;
        }

        $schema = require $path;

        return is_array($schema)
            ? $schema
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | FIELDS PATH
    |--------------------------------------------------------------------------
    */

    protected function fieldsPath(string $path): string
    {
        $path = $this->sanitizer->clean($path);

        return $this->paths->content(
            'pages/' . trim($path, '/') . '/fields.json'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE INTERFACE
    |--------------------------------------------------------------------------
    */

    protected function requireStore(): JsonStore
    {
        if (!$this->store) {
            throw new \RuntimeException(
                'No page has been loaded into PageBuilderService.'
            );
        }

        return $this->store;
    }

    public function get(
        string $key = '',
        mixed $default = null
    ): mixed {
        return $this->requireStore()->get(
            $key,
            $default
        );
    }

    public function set(
        string $key,
        mixed $value
    ): void {
        $this->requireStore()->set(
            $key,
            $value
        );
    }

    public function has(
        string $key
    ): bool {
        return $this->requireStore()->has(
            $key
        );
    }

    public function all(): array
    {
        return $this->requireStore()->all();
    }

    public function save(): bool
    {
        return $this->requireStore()->save();
    }

    /*
    |--------------------------------------------------------------------------
    | FIELDS
    |--------------------------------------------------------------------------
    */

    public function saveFields(
        string $path,
        array $fields
    ): bool {

        $data = $this->load($path);

        if (!$data) {
            return false;
        }

        foreach ($fields as $key => $value) {

            $this->store->set(
                (string)$key,
                $value
            );
        }

        return $this->store->save();
    }

    public function getFields(string $path): array
    {
        $path = $this->sanitizer->clean($path);

        $store = new JsonStore(
            $this->fieldsPath($path)
        );

        return $store->all();
    }

    public function saveRawFields(
        string $path,
        array $fields
    ): bool {
        $path = $this->sanitizer->clean($path);

        if (!$this->sanitizer->isValid($path)) {
            return false;
        }

        $store = new JsonStore(
            $this->fieldsPath($path)
        );

        return $store->save($fields);
    }
}