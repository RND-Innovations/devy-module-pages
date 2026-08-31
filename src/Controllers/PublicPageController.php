<?php

namespace DeVy\Modules\Pages\Controllers;

use Throwable;

use DeVy\Core\Container;
use DeVy\Core\Http\Controller;
use DeVy\Core\Http\Response;
use DeVy\Core\Services\HookManager;
use DeVy\Core\Rendering\PageContext;

use DeVy\Modules\Pages\Services\PageService;

class PublicPageController extends Controller
{
    protected PageService $pages;

    protected HookManager $hooks;

    public function __construct(
        Container $container
    ) {
        parent::__construct($container);

        $this->pages = $this->service(
            PageService::class
        );

        $this->hooks = $this->service(
            HookManager::class
        );
    }

    /**
     * ----------------------------------------
     * Normalize URI To Page Path
     * ----------------------------------------
     */
    protected function resolvePath(
        string $uri
    ): string {

        $path = trim($uri, '/');

        return $path === ''
            ? 'home'
            : $path;
    }

    /**
     * ----------------------------------------
     * Page Exists
     * ----------------------------------------
     */
    public function exists(
        string $uri
    ): bool {

        return $this->pages->exists(
            $this->resolvePath($uri)
        );
    }

    /**
     * ----------------------------------------
     * Render Page
     * ----------------------------------------
     */
    public function render(
        string $uri
    ): Response {

        $path = $this->resolvePath($uri);

        $page = $this->pages->get($path);

        if (!$page) {

            return $this->abort(
                404,
                'Page Not Found',
                '',
                [
                    'title' => 'Page Not Found',
                    'description' => 'The requested page does not exist.',
                    'page_path' => $path,
                ]
            );

        }

        if (isset($page['meta']['status']) && $page['meta']['status'] =='draft' ) {

            return $this->abort(
                403,
                'Access Denied',
                '',
                [
                    'title' => 'Access Denied',
                    'description' => 'You do not have permission to access this page.',
                    'page_status' => $page['meta']['status'],
                ]
            );

        }



        $view = trim(
            $page['meta']['view']
            ?? 'default-page'
        );

        if ($view === '') {
            $view = 'default-page';
        }

        $page_id    = $page['meta']['slug'] ?? '';
        $page_class = $view;

        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        $view = '@theme/pages/' . $view;

        $content = $this->hooks->dispatch(
            'content.parse',
            $page['body'] ?? ''
        );

        try {

            $context = $this->container()->get(
                PageContext::class
            );

            $context
                ->id($page_id)
                ->class($page_class)
                ->meta($page['meta'] ?? [])
                ->fields($page['fields'] ?? [])
                ->content($content)
                ->body($page['body'] ?? '');

            $html = $this->renderView(
                $view,
                $context->toArray()
            );    


        } catch (Throwable) {

            return $this->abort(
                500,
                'System Error',
                '',
                [
                    'title' => 'System Error',
                    'description' => 'Server cannot render this page.',
                ]
            );

        }

        $this->pageCache()->putCurrent(
            $html
        );

        return $this->response(
            $html,
            200,
            [
                'Content-Type' => 'text/html'
            ]
        );
    }
}