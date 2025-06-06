<?php
class LBP_PostTypes {
    public static function register() {
        register_post_type('lot', [
            'labels' => [
                'name' => 'Lots',
                'singular_name' => 'Lot',
                'add_new' => 'Add New Lot',
                'add_new_item' => 'Add New Lot'
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_position' => 5
        ]);

        register_post_type('rate', [
            'labels' => [
                'name' => 'Rates',
                'singular_name' => 'Rate',
                'add_new' => 'Add New Rate',
                'add_new_item' => 'Add New Rate',
                'edit_item' => 'Edit Rate',
                'view_item' => 'View Rate',
            ],
            'public' => true,
            'has_archive' => false,
            'supports' => ['title', 'editor'],
            'menu_position' => 6,
            'capabilities' => [
                'create_posts' => 'do_not_allow', //
            ],
            'map_meta_cap' => true, // Required for custom capabilities to take effect
        ]);


        register_post_type('lot_service', [
            'labels' => [
                'name' => 'Lot Available Services',
                'singular_name' => 'Service',
                'add_new' => 'Add New Service',
                'add_new_item' => 'Add New Service'
            ],
            'public' => true,
            'has_archive' => false,
            'supports' => ['title', 'editor'],
            'menu_position' => 7
        ]);
    }
}
