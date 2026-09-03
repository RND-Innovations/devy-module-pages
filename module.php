<?php

use DeVy\Core\Modules\ModuleContext;
use DeVy\Core\Assets\AssetRegistry;
use DeVy\Core\Support\PathSanitizer;
use DeVy\Core\Support\PageFileHandler;

use DeVy\Modules\Pages\Services\{
    PageService,
    PageTreeService,
    PageBuilderService
};

use DeVy\Modules\Pages\Controllers\{
    AdminPageController,
    AdminPageOrganizerController,
    AdminPageBuilderController,
    PublicPageController
};

return [

/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

    'meta' => [
        'name' => 'Pages',
        'description' => 'Easily build, manage, and publish custom pages across your website.',        
        'version' => '1.0.1',
        'author' => 'RND Innovations',
        'website' => 'https://rndvn.com',
        'license' => 'MIT',
        'icon' => 'content',        
        'namespace' => 'DeVy\\Modules\\Pages',
        'requires' => [
            'framework' => '^1.0',
            'modules' => ['Admin'],
            'php' => '^8.3'
        ]
    ],


/*
|--------------------------------------------------------------------------
| REGISTER
|--------------------------------------------------------------------------
*/

'register' => function (ModuleContext $ctx) {

    $c = $ctx->container();

    $c->singleton(PageService::class, fn($c) => new PageService(
        $ctx->path(),
        $c->get(PathSanitizer::class),
        $c->get(PageFileHandler::class),
    ));

    $c->singleton(PageBuilderService::class, fn($c) => new PageBuilderService(
        $ctx->path(),
        $ctx->settings(),
        $c->get(PathSanitizer::class),
        $c->get(PageFileHandler::class),
        $c->get(PageService::class),
        $ctx->hooks(),
    ));

    $c->singleton(PageTreeService::class, fn($c) => new PageTreeService(
        $ctx->path(),
        $c->get(PageService::class),
    ));

    // cleaner controller registration
    $ctx->controllers([
        AdminPageController::class,
        AdminPageOrganizerController::class,
        AdminPageBuilderController::class,
        PublicPageController::class,
    ]);


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    $ctx->permission()->addRoles([
        'editor' => 'Editor',
        'writer' => 'Writer',
    ]);


    $ctx->permission()->addPermissions([
        [
            'key' => 'admin.pages.view',
            'name' => 'View Pages',
            'module' => 'pages',
            'description' => 'View and access the Pages management area.',
            'roles' => [
                'admin',
                'editor',
                'writer',
            ],
        ],

        [
            'key' => 'admin.pages.create',
            'name' => 'Create Pages',
            'module' => 'pages',
            'description' => 'Create new pages.',
            'roles' => [
                'admin',
                'editor',
                'writer',
            ],
        ],

        [
            'key' => 'admin.pages.edit',
            'name' => 'Edit Pages',
            'module' => 'pages',
            'description' => 'Edit existing pages.',
            'roles' => [
                'admin',
                'editor',
            ],
        ],

        [
            'key' => 'admin.pages.delete',
            'name' => 'Delete Pages',
            'module' => 'pages',
            'description' => 'Delete pages.',
            'roles' => [
                'admin',
            ],
        ],

        [
            'key' => 'admin.pages.organize',
            'name' => 'Organize Pages',
            'module' => 'pages',
            'description' => 'Organize the page hierarchy.',
            'roles' => [
                'admin',
                'editor',
            ],
        ],
    ]);

},

/*
|--------------------------------------------------------------------------
| BOOT
|--------------------------------------------------------------------------
*/

'boot' => function (ModuleContext $ctx) {

    $config = $ctx->config();

    $adminSlug = trim($config->get('admin.slug'), '/');

    $pageService = fn() => $ctx->get(PageService::class);

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES (clean + grouped)
    |--------------------------------------------------------------------------
    */

    $ctx->adminRoutes(function ($r) {

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        $r->add([
            'name' => 'admin.pages',
            'method' => 'GET',
            'uri' => '/pages',
            'permission' => 'admin.pages.view',
            'action' => [AdminPageController::class, 'pages'],
        ]);

        $r->add([
            'name' => 'admin.pages.debug.view',
            'method' => 'GET',
            'uri' => '/pages/debug/view',
            'permission' => 'admin.pages.view',
            'action' => [AdminPageController::class, 'debug'],
        ]);

        $r->add([
            'name' => 'admin.pages.view.base',
            'method' => 'GET',
            'uri' => '/pages/view',
            'permission' => 'admin.pages.view',
            'action' => [AdminPageController::class, 'pages'],
        ]);

        $r->add([
            'name' => 'admin.pages.view',
            'method' => 'GET',
            'uri' => '/pages/view/{path:.+}',
            'permission' => 'admin.pages.view',
            'action' => [AdminPageController::class, 'pages'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        $r->add([
            'name' => 'admin.pages.create',
            'method' => 'GET',
            'uri' => '/pages/create/{path:.+}',
            'permission' => 'admin.pages.create',
            'action' => [AdminPageController::class, 'create'],
        ]);

        $r->add([
            'name' => 'admin.pages.create.base',
            'method' => 'GET',
            'uri' => '/pages/create',
            'permission' => 'admin.pages.create',
            'action' => [AdminPageController::class, 'create'],
        ]);

        $r->add([
            'name' => 'admin.pages.store',
            'method' => 'POST',
            'uri' => '/pages/store',
            'permission' => 'admin.pages.create',
            'action' => [AdminPageController::class, 'store'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | EDIT
        |--------------------------------------------------------------------------
        */

        $r->add([
            'name' => 'admin.pages.edit',
            'method' => 'GET',
            'uri' => '/pages/edit/{path:.+}',
            'permission' => 'admin.pages.edit',
            'action' => [AdminPageController::class, 'edit'],
        ]);

        $r->add([
            'name' => 'admin.pages.save',
            'method' => 'POST',
            'uri' => '/pages/save',
            'permission' => 'admin.pages.edit',
            'action' => [AdminPageController::class, 'save'],
        ]);

        $r->add([
            'name' => 'admin.pages.builder',
            'method' => 'GET',
            'uri' => '/pages/builder/{path:.+}',
            'permission' => 'admin.pages.edit',
            'action' => [AdminPageBuilderController::class, 'builder'],
        ]);

        $r->add([
            'name' => 'admin.pages.builder.save',
            'method' => 'POST',
            'uri' => '/pages/builder/save',
            'permission' => 'admin.pages.edit',
            'action' => [AdminPageBuilderController::class, 'save'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        $r->add([
            'name' => 'admin.pages.delete',
            'method' => 'POST',
            'uri' => '/pages/delete',
            'permission' => 'admin.pages.delete',
            'action' => [AdminPageController::class, 'delete'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | ORGANIZER
        |--------------------------------------------------------------------------
        */

        $r->add([
            'name' => 'admin.page-organizer',
            'method' => 'GET',
            'uri' => '/page-organizer',
            'permission' => 'admin.pages.organize',
            'action' => [AdminPageOrganizerController::class, 'tree'],
        ]);

        $r->add([
            'name' => 'admin.page-organizer.toggle',
            'method' => 'POST',
            'uri' => '/page-organizer/toggle',
            'permission' => 'admin.pages.organize',
            'action' => [AdminPageOrganizerController::class, 'toggle'],
        ]);

        $r->add([
            'name' => 'admin.page-organizer.save',
            'method' => 'POST',
            'uri' => '/page-organizer/save',
            'permission' => 'admin.pages.organize',
            'action' => [AdminPageOrganizerController::class, 'save'],
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | ASSETS
    |--------------------------------------------------------------------------
    */

    $ctx->registerPageAssets(
        'admin.pages.edit',
        'pages-editor',
        [
            'css' => [
                '/Modules/Pages/assets/editor.css',
            ],
            'js' => [
                '/Modules/Pages/assets/editor.js',
            ],
        ]
    );

    $ctx->registerPageAssets(
        'admin.pages.tree',
        'pages-organizer',
        [
            'js' => [
                '/Modules/Pages/assets/organizer.js',
            ],
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | FRONTEND FALLBACK
    |--------------------------------------------------------------------------
    */

    $ctx->hook('router.fallback', function ($uri) use ($ctx, $config) {

        $adminSlug = trim($config->get('admin.slug'), '/');

        if (str_starts_with(trim($uri, '/'), $adminSlug)) {
            return null;
        }

        $controller = $ctx->get(PublicPageController::class);

        if ($controller->exists($uri)) {
            $controller->render($uri)->send();
            return true;
        }

        return null;
    });

    /*
    |--------------------------------------------------------------------------
    | NAVIGATION (clean DSL-style)
    |--------------------------------------------------------------------------
    */

    $ctx->addNavigation('admin', [
        'title' => 'Pages',
        'icon' => 'content',
        'order' => 2,
        'permission' => 'admin.pages.view',
        'children' => [

            [
                'title' => 'All Pages',
                'icon' => 'list',
                'order' => 1,
                'url' => $ctx->router()->route('admin.pages'),
                'permission' => 'admin.pages.view'
            ],

            [
                'title' => 'Add New',
                'icon' => 'plus',
                'order' => 2,
                'url' => $ctx->router()->route('admin.pages.create.base'),
                'permission' => 'admin.pages.create'
            ],

            [
                'title' => 'Organize',
                'icon' => 'updown',
                'order' => 3,
                'url' => $ctx->router()->route('admin.page-organizer'),
                'permission' => 'admin.pages.organize'
            ],
        ]
    ]);

    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    $ctx->hook('admin.header.right', function ($items) use ($ctx) {

        $items[] = [
            'type'  => 'link',
            'title' => 'View',
            'icon'  => 'eye',
            'url'   => $ctx->router()->route('admin.pages.debug.view'),
            'order' => 1,
        ];

        return $items;
    });

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD STATS
    |--------------------------------------------------------------------------
    */

    $ctx->hook('admin.dashboard.stats', function ($stats) use ($pageService, $ctx) {

        $pages = $ctx->get(PageService::class)->all();

        $total = count($pages);

        $published = count(array_filter($pages, fn($p) =>
            ($p["meta"]['status'] ?? 'published') === 'published'
        ));

        $drafts = count(array_filter($pages, fn($p) =>
            ($p["meta"]['status'] ?? '') === 'draft'
        ));

        $stats[] = [
            'title' => 'Pages',
            'value' => $total,
            'icon'  => 'content',
            'color' => 'emerald',
            'url'   => $ctx->router()->route('admin.pages'),
            'meta'  => "{$published} published • {$drafts} drafts"
        ];

        return $stats;
    });

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD WIDGET
    |--------------------------------------------------------------------------
    */

    $ctx->hook('admin.dashboard.main', function ($widgets) use ($ctx) {

        $pages = array_reverse($ctx->get(PageService::class)->all());
        $latest = array_slice($pages, 0, 8);

        $widgets[] = $ctx->templates()->render(
            '@Pages/widgets/dashboard-widget.twig',
            ['pages' => $latest]
        );

        return $widgets;
    });


    /*
    |--------------------------------------------------------------------------
    | SITEMAP
    |--------------------------------------------------------------------------
    */

    $ctx->hook('sitemap.collect', function (array $payload) use ($ctx): array {

        $service = $ctx->get(PageService::class);
        $urls = [];

        foreach ($service->all() as $page) {

            if (($page['status'] ?? 'published') !== 'published') continue;
            if (($page['sitemap'] ?? true) === false) continue;
            if (($page['seo']['noindex'] ?? false) === true) continue;

            $path = trim($page['path'] ?? '', '/');

            if ($path === '' || $path === '404' || $path === 'home') continue;

            $urls[] = [
                'loc' => '/' . $path,
                'priority' => $page['seo']['priority'] ?? 0.7,
                'changefreq' => $page['seo']['changefreq'] ?? 'weekly',
                'lastmod' => $page['updated_at'] ?? date('c')
            ];
        }

        $payload['pages'] = array_merge($payload['pages'] ?? [], $urls);

        return $payload;

    }, 10);

}
];