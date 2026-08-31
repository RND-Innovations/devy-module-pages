<?php

namespace DeVy\Modules\Pages\Services;

use DeVy\Core\Services\PathService;
use DeVy\Core\Persistence\JsonStore;

class PageTreeService
{
    private PathService $paths;
    private PageService $pages;
    private JsonStore $store;

    private string $file;

    public function __construct(PathService $paths, PageService $pages)
    {
        $this->paths = $paths;
        $this->pages = $pages;
        $this->file = $this->paths->pages('pages.tree.json');
        $this->store = new JsonStore($this->file);
    }

    // -------------------------
    // LOAD TREE
    // -------------------------

    public function get(): array
    {
        $data = $this->store->all();

        return is_array($data)
            ? $data
            : ['tree' => []];
    }

    // -------------------------
    // SAVE TREE
    // -------------------------

    public function save(array $data): void
    {
        $this->paths->ensureWritable(
            dirname($this->file)
        );

        $this->store->save($data);
    }

    // -------------------------
    // AUTO SYNC (SAFE)
    // -------------------------

    public function sync(): array
    {
        $data = $this->get();
        $tree = $data['tree'] ?? [];

        $allPages = array_filter(
            $this->pages->all(),
            fn($page) => empty($page['deleted'])
        );

        $validPaths = array_map(
            fn($page) => $page['path'] ?? null,
            $allPages
        );

        $validPaths = array_filter($validPaths);

        // 🔥 THIS IS THE MISSING PIECE
        $tree = $this->cleanTree($tree, $validPaths);

        $existingPaths = $this->flattenPaths($tree);

        $changed = false;

        foreach ($validPaths as $path) {

            if (!is_string($path)) continue;

            if (!in_array($path, $existingPaths, true)) {
                $this->insertIntoTree($tree, $path);
                $changed = true;
            }
        }

        if ($changed) {
            $data['tree'] = $tree;
            $this->save($data);
        }

        return $data;
    }

    // -------------------------
    // INSERT INTO TREE
    // -------------------------

    private function insertIntoTree(array &$tree, string $path): void
    {
        $parts = explode('/', $path);
        $currentPath = '';

        $level = &$tree;

        foreach ($parts as $part) {

            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;

            $foundIndex = null;

            foreach ($level as $i => $node) {
                if ($node['path'] === $currentPath) {
                    $foundIndex = $i;
                    break;
                }
            }

            if ($foundIndex === null) {
                $level[] = [
                    'path' => $currentPath,
                    'featured' => false,
                    'children' => []
                ];
                $foundIndex = array_key_last($level);
            }

            if (!isset($level[$foundIndex]['children'])) {
                $level[$foundIndex]['children'] = [];
            }

            $level = &$level[$foundIndex]['children'];
        }
    }

    // -------------------------
    // FLATTEN TREE
    // -------------------------

    private function flattenPaths(array $tree): array
    {
        $paths = [];

        foreach ($tree as $node) {
            if (!empty($node['path'])) {
                $paths[] = $node['path'];
            }

            if (!empty($node['children'])) {
                $paths = array_merge(
                    $paths,
                    $this->flattenPaths($node['children'])
                );
            }
        }

        return $paths;
    }

    // -------------------------
    // TOGGLE FEATURED
    // -------------------------

    public function toggleFeatured(string $path): void
    {
        $data = $this->get();

        $data['tree'] = $this->mapTree($data['tree'], function ($node) use ($path) {
            if ($node['path'] === $path) {
                $node['featured'] = !($node['featured'] ?? false);
            }
            return $node;
        });

        $this->save($data);
    }

    // -------------------------
    // GENERIC TREE WALKER
    // -------------------------

    private function mapTree(array $tree, callable $callback): array
    {
        $new = [];

        foreach ($tree as $node) {

            $node = $callback($node);

            if (!empty($node['children'])) {
                $node['children'] = $this->mapTree($node['children'], $callback);
            }

            $new[] = $node;
        }

        return $new;
    }

    // -------------------------
    // REORDER LEVEL
    // -------------------------

    public function reorderLevel(string $path, array $order): void
    {
        $data = $this->get();

        $data['tree'] = $this->reorderRecursive($data['tree'], $path, $order);

        $this->save($data);
    }

    private function reorderRecursive(array $tree, string $path, array $order): array
    {
        // ROOT LEVEL
        if ($path === '') {
            return $this->applyOrder($tree, $order);
        }

        foreach ($tree as &$node) {

            if ($node['path'] === $path) {

                if (!isset($node['children'])) {
                    $node['children'] = [];
                }

                $node['children'] = $this->applyOrder(
                    $node['children'],
                    $order
                );

                return $tree;
            }

            if (!empty($node['children'])) {
                $node['children'] = $this->reorderRecursive(
                    $node['children'],
                    $path,
                    $order
                );
            }
        }

        return $tree;
    }

    private function applyOrder(array $nodes, array $order): array
    {
        $map = [];

        foreach ($nodes as $node) {
            $map[$node['path']] = $node;
        }

        $sorted = [];

        foreach ($order as $path) {
            if (isset($map[$path])) {
                $sorted[] = $map[$path];
                unset($map[$path]);
            }
        }

        // keep any missing (safety)
        foreach ($map as $node) {
            $sorted[] = $node;
        }

        return $sorted;
    }

    // -------------------------
    // GET LEVEL (USED BY CONTROLLER)
    // -------------------------

    public function getLevel(array $tree, string $path): array
    {
        if ($path === '') return $tree;

        foreach ($tree as $node) {

            if ($node['path'] === $path) {
                return $node['children'] ?? [];
            }

            if (!empty($node['children'])) {
                $found = $this->getLevel($node['children'], $path);
                if (!empty($found)) return $found;
            }
        }

        return [];
    }

    // -------------------------
    // FRONTEND MAPPING
    // -------------------------

    public function mapToPages(): array
    {
        $data = $this->get();

        return $this->mapTree($data['tree'], function ($node) {

            $page = $this->pages->get($node['path']);

            if ($page) {
                $node['page'] = $page;
            }

            $node['featured'] = $node['featured'] ?? false;

            return $node;
        });
    }


    private function cleanTree(array $tree, array $validPaths): array
    {
        $result = [];

        foreach ($tree as $node) {

            $path = $node['path'] ?? null;

            if (!$path || !in_array($path, $validPaths, true)) {
                continue;
            }

            if (!empty($node['children'])) {
                $node['children'] = $this->cleanTree(
                    $node['children'],
                    $validPaths
                );
            }

            $result[] = $node;
        }

        return $result;
    }
    
}