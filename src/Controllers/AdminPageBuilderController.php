<?php

declare(strict_types=1);

namespace DeVy\Modules\Pages\Controllers;

use DeVy\Core\Container;

use DeVy\Core\Http\{
    Controller,
    Request,
    Response
};

use DeVy\Core\Schema\SchemaForm;
use DeVy\Core\Services\ToastService;
use DeVy\Modules\Pages\Services\PageBuilderService;

class AdminPageBuilderController extends Controller
{
    protected SchemaForm $form;
    protected PageBuilderService $builder;
    protected ToastService $toast;

    public function __construct(
        Container $container
    ) {
        parent::__construct($container);

        $this->builder = $this->service(
            PageBuilderService::class
        );

        $this->form = $this->service(
            SchemaForm::class
        );

        $this->toast = $this->service(
            ToastService::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BUILDER
    |--------------------------------------------------------------------------
    */

    public function builder(
        array $params,
        Request $request
    ): Response {

        $path = trim(
            (string)($params['path'] ?? ''),
            '/'
        );

        if ($path === '') {
            return $this->redirectRoute(
                'admin.pages'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD PAGE
        |--------------------------------------------------------------------------
        */

        $data = $this->builder->load($path);

        if (!$data) {

            $this->toast->add(
                'Page not found',
                'error'
            );

            return $this->redirectRoute(
                'admin.pages'
            );
        }

        $data['page']["title"] = 'Page Builder';
        
        /*
        |--------------------------------------------------------------------------
        | CHECK VIEW
        |--------------------------------------------------------------------------
        */

        if ($data['view'] === '') {

            $this->toast->add(
                'This page has no view assigned.',
                'error'
            );

            return $this->redirectUrl(
                $this->router->route(
                    'admin.pages.edit'
                ) . $path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK SCHEMA
        |--------------------------------------------------------------------------
        */

        if (!is_array($data['schema'])) {

            $this->toast->add(
                'No builder schema found for view: ' . $data['view'],
                'error'
            );

            return $this->redirectUrl(
                $this->router->route(
                    'admin.pages.edit'
                ) . $path
            );
        }

        $schema = $this->form->build(
            $this->builder,
            'pages.builder.schema'
        );

        return $this->view(
            '@Pages/builder.twig',
            [
                'page' => $data['page'],
                'path' => $path,
                'view' => $data['view'],
                'schema' => $schema,

                'routes' => [
                    'save' => $this->router->route(
                        'admin.pages.builder.save'
                    ),

                    'back' => $this->router->route(
                        'admin.pages.edit'
                    ) . $path
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    public function save(
        array $params,
        Request $request
    ): Response {

        $path = trim(
            (string)$request->input('path', ''),
            '/'
        );

        if ($path === '') {
            return $this->redirectRoute(
                'admin.pages'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD PAGE
        |--------------------------------------------------------------------------
        */

        $data = $this->builder->load($path);

        if (!$data) {

            $this->toast->add(
                'Page not found',
                'error'
            );

            return $this->redirectRoute(
                'admin.pages'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK VIEW
        |--------------------------------------------------------------------------
        */

        if ($data['view'] === '') {

            $this->toast->add(
                'This page has no view assigned.',
                'error'
            );

            return $this->redirectUrl(
                $this->router->route(
                    'admin.pages.edit'
                ) . $path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK SCHEMA
        |--------------------------------------------------------------------------
        */

        if (!is_array($data['schema'])) {

            $this->toast->add(
                'No builder schema found for view: ' . $data['view'],
                'error'
            );

            return $this->redirectUrl(
                $this->router->route(
                    'admin.pages.edit'
                ) . $path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE THROUGH SCHEMA FORM
        |--------------------------------------------------------------------------
        */

        $this->form->save(
            $this->builder,
            'pages.builder.schema',
            $request->input(
                'fields',
                []
            )
        );

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        $this->toast->add(
            'Page updated successfully!',
            'success'
        );

        return $this->redirectUrl(
            $this->router->route(
                'admin.pages.builder'
            ) . $path
        );
    }
}