<?php

namespace DeVy\Modules\Pages\Controllers;

use DeVy\Core\Container;
use DeVy\Core\Http\Router;
use DeVy\Core\Http\Controller;
use DeVy\Core\Http\Request;
use DeVy\Core\Http\Response;

use DeVy\Modules\Pages\Services\PageTreeService;

class AdminPageOrganizerController extends Controller
{
    private PageTreeService $tree;

    public function __construct(
        Container $container
    ) {
        parent::__construct($container);

        $this->tree = $this->service(PageTreeService::class);
    }

    public function tree(array $params, Request $request): Response
    {
        $router = $this->service(Router::class);

        $path = $request->query('path', '');

        $treeData = $this->tree->sync();

        $items = $this->getLevel($treeData['tree'], $path);

        $items = array_filter($items, fn($node) =>
            !$this->isSystemPage($node['path'] ?? '')
        );

        return $this->view('@Pages/tree.twig', [
            'tree' => array_values($items),
            'page' => ['title' => 'Organizer'],
            'current_path' => $path,
            'routes' => [
                'toggle' => $router->route('admin.page-organizer.toggle'),
                'save' => $router->route('admin.page-organizer.save')
            ]
        ]);
    }

    public function toggle(array $params, Request $request): Response
    {
        $path = $request->input('path', '');

        if ($path !== '') {
            $this->tree->toggleFeatured($path);
        }

        return Response::json(['success' => true]);
    }

    public function save(array $params, Request $request): Response
    {
        $input = $request->json();

        if (!is_array($input) || !isset($input['order'])) {
            return Response::json([
                'success' => false,
                'error' => 'Invalid payload'
            ], 422);
        }

        $path = $input['path'] ?? '';
        $order = $input['order'] ?? [];

        $this->tree->reorderLevel($path, $order);

        return Response::json(['success' => true]);
    }

    private function getLevel(array $tree, string $path): array
    {
        if ($path === '') {
            return $tree;
        }

        foreach ($tree as $node) {
            if (($node['path'] ?? '') === $path) {
                return $node['children'] ?? [];
            }

            if (!empty($node['children'])) {
                $found = $this->getLevel($node['children'], $path);

                if (!empty($found)) {
                    return $found;
                }
            }
        }

        return [];
    }

    private function isSystemPage(string $path): bool
    {
        return in_array($path, ['home', '404'], true);
    }
}