<?php

namespace DeVy\Modules\Pages\Controllers;

use RuntimeException;

use DeVy\Core\Container;

use DeVy\Core\Http\Router;
use DeVy\Core\Http\Controller;
use DeVy\Core\Http\Request;
use DeVy\Core\Http\Response;

use DeVy\Core\Services\TemplateService;
use DeVy\Core\Services\ToastService;
use DeVy\Core\Services\HookManager;
use DeVy\Core\Services\SettingsService;

use DeVy\Modules\Pages\Services\PageService;
use DeVy\Modules\Pages\Services\PageBuilderService;


class AdminPageController extends Controller
{
    private PageService $pages;
    private PageBuilderService $builder;
    private ToastService $toast;
    private HookManager $hooks;
    private SettingsService $settings;

    public function __construct(
        Container $container
    ) {
        parent::__construct($container);

        $this->pages = $this->service(PageService::class);
        $this->builder = $this->service(PageBuilderService::class);
        $this->toast = $this->service(ToastService::class);
        $this->hooks = $this->service(HookManager::class);
        $this->settings = $this->service(SettingsService::class);
    }

    public function pages(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);

        $path = $params['path'] ?? null;

        $create = is_null($path)
            ? $router->route('admin.pages.create.base')
            : $router->route('admin.pages.create') . $path;

        return $this->view('@Pages/list.twig', [
            'items' => $this->pages->list($path ?? '/'),
            'current_path' => $path,
            'page' => ['title' => 'Pages'],
            'routes' => [
                'create' => $create,
                'list' => $router->route('admin.pages'),
                'view' => $router->route('admin.pages.debug.view')
            ]
        ]);
    }

    public function create(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);

        $path = $params['path'] ?? null;

        return $this->view('@Pages/create.twig', [
            'current_path' => $path,
            'page' => ['title' => 'Create Page'],
            'routes' => [
                'store' => $router->route('admin.pages.store'),
                'back' => $router->route('admin.pages')
                    . ($path ? '/view/' . $path : '')
            ]
        ]);
    }

    public function store(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);

        $parent = $request->input('path', '');
        $slug = trim((string)$request->input('slug', ''));
        $base = $router->route('admin.pages');

        if($parent==''){
            $goto = $base . "/create";
        }else{
            $goto = $base . "/create/" . $parent;
        }

        if (!$slug) {
            $this->toast->add('Slug required', 'error');
            return $this->redirectUrl($goto);
        }

        $fullPath = trim($parent . '/' . $slug, '/');

        if ($this->pages->exists($fullPath)) {
            $this->toast->add('Page already exists', 'error');
            return $this->redirectUrl($goto);
        }

        $this->pages->save($fullPath, [
            'title' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'draft'
        ], '');

        $this->toast->add('New page drafted! Start editing!', 'success');

        $goto = $base . "/edit/".trim($parent . '/' . $slug, '/');

        return $this->redirectUrl($goto);
    }

    public function edit(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);

        $path = $params['path'] ?? null;

        if (!$path) {
            return $this->redirectRoute('admin.pages');
        }

        $page = $this->pages->get($path);

        if (!$page) {
            return $this->redirectRoute('admin.pages');
        }

        $page['title'] = 'Edit Page';
        $fields = $this->builder->getFields($path);

        $parent = '';
        if (str_contains($path, '/')) {
            $parts = explode('/', $path);
            array_pop($parts);
            $parent = implode('/', $parts);
        }

        return $this->view('@Pages/edit.twig', [
            'page' => $page,
            'current_path' => $parent,
            'views' => $this->hooks->dispatch('theme.views', []),
            'fields' => $fields,
            'routes' => [
                'list' => $router->route('admin.pages'),
                'build' => $router->route('admin.pages') . '/builder/' . $path,
                'back' => $router->route('admin.pages') . '/view/' . $parent
            ]
        ]);
    }

    public function save(
        array $params,
        Request $request
    ): Response {

        $path = $request->input('path');

        if (!$path) {
            return Response::json([
                'success' => false,
                'message' => 'Invalid page path.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | META
        |--------------------------------------------------------------------------
        */

        $meta = [
            'title' => $request->input('title', ''),
            'keyword' => $request->input('keyword', ''),
            'description' => $request->input('description', ''),
            'view' => $request->input('view', ''),
            'status' => $request->input('status', 'draft')
        ];

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        $body = $request->input('body', '');

        /*
        |--------------------------------------------------------------------------
        | FIELDS JSON
        |--------------------------------------------------------------------------
        */

        $fieldsJson = (string)$request->input(
            'fields',
            '{}'
        );

        $fields = json_decode(
            $fieldsJson,
            true
        );

        if (!is_array($fields)) {
            return Response::json([
                'success' => false,
                'message' => 'Fields must contain valid JSON.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE PAGE
        |--------------------------------------------------------------------------
        */

        $ok = $this->pages->save(
            $path,
            $meta,
            $body
        );

        if (!$ok) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to save page.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE FIELDS
        |--------------------------------------------------------------------------
        */

        if (!$this->builder->saveRawFields(
            $path,
            $fields
        )) {
            return Response::json([
                'success' => false,
                'message' => 'Page saved, but fields.json could not be saved.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CACHE
        |--------------------------------------------------------------------------
        */

        $this->pageCache()->delete($path);

        return Response::json([
            'success' => true,
            'message' => 'Saved'
        ]);
    }

    public function delete(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);
        
        $parent = $request->input('parent');
        $path = $request->input('path');
        $base = $router->route('admin.pages');

        if ($this->pages->delete($path)) {
            $this->pageCache()->delete($path);
            $this->toast->add('Removed : ' . $path, 'success');
            $goto = $base . "/view/" . $parent;
            return $this->redirectUrl($goto);
        }

        $this->toast->add('Cannot remove : ' . $path, 'error');
        $goto = $base . "/edit/" . $path;
        return $this->redirectUrl($goto);
    }

    public function debug(array $params, Request $request): Response
    {
        $settings = $this->service(SettingsService::class);

        $base = rtrim($settings->get('site.url'), '/');

        $path = trim((string)$request->query('path', ''), '/');

        if (!$this->pages->exists($path)) {
            $this->toast->add('Invalid or missing page', 'error');
            return $this->redirectRoute('admin.pages');
        }

        $page = $this->pages->get($path);

        if (!$page) {
            $this->toast->add('Page not found', 'error');
            return $this->redirectRoute('admin.pages');
        }

        $this->pageCache()->delete($path);

        return $this->view('@Pages/debug.twig', [
            'base' => $base,
            'path' => $path,
            'url' => $base . '/' . $path,
            'page' => ['title' => 'Debug: ' . $path],
            'routes' => []
        ]);
    }
}