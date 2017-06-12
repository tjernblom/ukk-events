<?php

class ukk_events {

    /********************************************
     *  Add Wordpress actions
     ********************************************/

    public function __construct() {
        add_action('init', array(&$this, 'create_pagetype'));

        add_action('admin_menu', array( $this, 'import_page_add'));
        add_action('admin_init', array( $this, 'add_theme_caps'));

        //add_filter('archive_template', array(&$this, 'archive'));

        add_action('admin_init', array( $this, 'add_meta_box'));

        if ( !wp_next_scheduled( 'refresh_tickster_events' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'refresh_tickster_events' );
        }
        add_action('refresh_tickster_events', array($this, 'refresh_tickster_events') );
    }


    /**
     * Create the page type
     */
    public function create_pagetype() {

        $labels = array(
            'name' => __('Kalendarium'),
            'singular_name' => __('Evenemang'),
            'add_new' => __('Nytt evenemang'),
            'add_new_item' => __('Nytt evenemang'),
            'edit_item' => __('Redigera evenemang'),
            'view_item' => __('Visa evenemang'),
            'view_items' => __('Visa alla evenemang'),
            'search_items' => __('Sök efter evenemang'),
            'not_found' => __('Inga evenemang hittades'),
            'not_found_in_trash' => __('Inga evenemang finns i papperskorgen'),
            'parent_item_colon' => '',
        );

        $args = array(
            'label' => __('Evenemang'),
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-feedback',
            'capability_type' => 'page',
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array("slug" => "kalendarium"),
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_nav_menus' => true
        );

        register_post_type('event', $args);

        $this->create_taxonomy();

    }

    /**
     * Create the taxonomy for the pagetype
     */
    private function create_taxonomy() {

        $labels = array(
            'name'              => _x( 'Kategorier', 'taxonomy general name', 'textdomain' ),
            'singular_name'     => _x( 'Kategori', 'taxonomy singular name', 'textdomain' ),
            'search_items'      => __( 'Sök kategorier', 'textdomain' ),
            'all_items'         => __( 'Alla kategorier', 'textdomain' ),
            'edit_item'         => __( 'Redigera kategori', 'textdomain' ),
            'update_item'       => __( 'Uppdatera kategori', 'textdomain' ),
            'add_new_item'      => __( 'Lägg till kategori', 'textdomain' ),
            'new_item_name'     => __( 'Ny kategori', 'textdomain' ),
            'menu_name'         => __( 'Kategorier', 'textdomain' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_nav_menus' => true,
            'rewrite'           => array('slug' => 'kalendariekategori'),
        );

        register_taxonomy( 'eventcat', 'event', $args );

        // Add tags
        $labels = array(
            'name' => _x( 'Tags', 'taxonomy general name' ),
            'singular_name' => _x( 'Tag', 'taxonomy singular name' ),
            'search_items' =>  __( 'Search Tags' ),
            'popular_items' => __( 'Popular Tags' ),
            'all_items' => __( 'All Tags' ),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => __( 'Edit Tag' ), 
            'update_item' => __( 'Update Tag' ),
            'add_new_item' => __( 'Add New Tag' ),
            'new_item_name' => __( 'New Tag Name' ),
            'separate_items_with_commas' => __( 'Separate tags with commas' ),
            'add_or_remove_items' => __( 'Add or remove tags' ),
            'choose_from_most_used' => __( 'Choose from the most used tags' ),
            'menu_name' => __( 'Tags' ),
        ); 

        register_taxonomy('tag', 'event', array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var' => true,
            'rewrite' => array( 'slug' => 'tag' ),
        ));

    }


    /*
    * Add single and archive templates for jobs
    */

    public function archive ( $archive_template ) {
        /*global $post;

        if ( is_tax( 'jobcat' ) ) {
            $archive_template = CEXPERIS_DIR . 'redirect-jobcat.php';
        }
        return $archive_template;*/
    }


    /*
    * We add meta boxes with ACF, and not here, but we need to insert some custom CSS
    */
    public function add_meta_box() {
        add_meta_box('add_admin_css', 'OBS!', array( $this, 'add_admin_css'), 'event');
    }
    public function add_admin_css() {
        global $post;
        if (isset($post->ukk_tickster_id)) {
        ?>
        Ljusgrå datafält importeras löpande från Tickster och ska inte uppdateras manuellt i Wordpress.
        <style>
            div[data-name=call_to_action_link],
            div[data-name=price_html],
            div[data-name=performers_text],
            div[data-name=occurrences] {
                opacity: .2;
            }
        </style>
        <?php } else { ?>
        <style>
            #add_admin_css { display: none; }
        </style>
    <?php
        }
    }


    /**
     * Import items when called by cron
     */
    public function refresh_tickster_events() {
        $ukk_tickster_import_instance = new ukk_tickster_import();
        $ukk_tickster_import_instance->refresh();
    }


    /**
     * Add import page to menu
     */
    public function import_page_add() {
    add_submenu_page(
        'edit.php?post_type=event',
        __('Importera evenemang'),
        __('Importera'),
        'edit_pages',
        'menu-posts-job',
        array( $this, 'import_page_echo') );
    }

    public function import_page_echo() { ?>
        <div class="wrap">
            <h2>Importera evenemang</h2>
        <?php

        if (isset($_POST['action']) && $_POST['action'] == "refresh") {
            $this->refresh_tickster_events();
            echo "<p><strong>Evenemang från Tickster har importerats.</strong></p>";
        } else {
            echo "<p><strong>Evenemang från Tickster uppdateras automatiskt och regelbundet.</strong></p>";
        }

        echo "<p>Senaste uppdatering av evenemang: ".date('Y-m-d H:i:s', get_option( 'ukk_tickster__last_update' ));

        ?>
            <form action="" method="post">
                <input type="hidden" value="refresh" name="action">
                <p><button type="submit" class="button action">Importera från Tickster nu</button></p>
            </form>
        <?php

    }

    /**
     * Sets the administrator roles
     */
    function add_theme_caps() {
        $admins = get_role( 'administrator' );
        $admins->add_cap( 'edit_event' );
        $admins->add_cap( 'read_event' );
        $admins->add_cap( 'delete_event' );
        $admins->add_cap( 'edit_events' );
        $admins->add_cap( 'delete_events' );
        $admins->add_cap( 'edit_others_events' );

        $editors = get_role( 'editor' );
        $editors->add_cap( 'edit_event' );
        $editors->add_cap( 'read_event' );
        $editors->add_cap( 'delete_event' );
        $editors->add_cap( 'edit_events' );
        $editors->add_cap( 'delete_events' );
        $editors->add_cap( 'edit_others_events' );
    }

    /**
     * Functions for getting all occurrances of an event (don't group by event)
     */
    public function query_occurrences_where( $where ) {
        // We need some LIKEs in where
        $where = str_replace("meta_key = 'occurrences_%_", "meta_key LIKE 'occurrences_%_", $where);
        $where = str_replace("meta_value = '%", "meta_value LIKE '", $where);
        // Only upcoming please
        $where .= "AND CAST(wp_postmeta.meta_value AS DATE) >= '".date('Y-m-d')."'";
        //wp_postmeta.meta_value 
        return $where;
    }

    public function query_occurrences_groupby($groupby) {
        // Group only if duplicate start dates
        return $groupby.', wp_postmeta.meta_value';
    }
        
    public function query_occurrences_request($request, $q) {
        // Add meta_key and startdate to result
        $request = str_replace(' FROM', ', wp_postmeta.meta_key as meta_key, wp_postmeta.meta_value as occurrence_startdate FROM', $request);
        //echo $request;
        return $request;
    }
     
    public function query_occurrences_add_meta ($posts) {
        // Make it easier to access enddate and url for the occurrence
        foreach ($posts as &$post_item) {
            $row_prefix = str_replace('startdate', '', $post_item->meta_key);
            $propertyName = $row_prefix.'enddate';
            $post_item->occurrence_enddate = $post_item->$propertyName;
            $propertyName = $row_prefix.'url';
            $post_item->occurrence_url = $post_item->$propertyName;
        }
        return $posts;
    }   

    private function query_events_by_occurrence () {
        add_filter('posts_where', array($this, 'query_occurrences_where'));
        add_filter('posts_groupby', array($this, 'query_occurrences_groupby'));
        add_filter('posts_request', array($this, 'query_occurrences_request'), 1, 2);
        add_filter('posts_results', array($this, 'query_occurrences_add_meta'));
    }

    private function query_events_by_occurrence_remove () {
        remove_filter('posts_where', array($this, 'query_occurrences_where'));
        remove_filter('posts_groupby', array($this, 'query_occurrences_groupby'));
        remove_filter('posts_request', array($this, 'query_occurrences_request'), 1, 2);
        remove_filter('posts_results', array($this, 'query_occurrences_add_meta'));
    }


    /**
     * Get all events with multiple filters
     * - Parameter as array containing
     * -    search (query as string): Leave out or empty for no search
     * -    eventcat (slug as string): Leave out or empty for no category filtering
     * -    tag (slug as string): Leave out or empty for no tag filtering
     * -    period (date as string): Leave out or empty for no time period filtering,
     * -        - Format YYYY-MM-DD for DAY filtering
     * -        - Format YYYY-MM for MONTH filtering
     */
    public function get_events ($eventquery = array()) {

        $this->query_events_by_occurrence();

        $query_arr = array(
            'post_type'         => 'event',
            'post_status'       => 'publish',
            'posts_per_page'    => 20,
            'meta_key'          => 'occurrences_%_startdate',
            'orderby'           => 'meta_value',
            'order'             => 'ASC'
        );

        if (isset($eventquery['search']) && $eventquery['search'] != '') {
            $query_arr['s'] = $eventquery['search'];
        }

        if ((isset($eventquery['eventcat']) && $eventquery['eventcat'] != '') 
            || (isset($eventquery['tag']) && $eventquery['tag'] != '') ) {
            $query_arr['tax_query'] = array('relation' => 'AND');

            if (isset($eventquery['eventcat']) && $eventquery['eventcat'] != '') {
                array_push($query_arr['tax_query'], array(
                    'taxonomy' => 'eventcat',
                    'field'    => 'slug',
                    'terms'    => $eventquery['eventcat']
                ));
            }

            if (isset($eventquery['tag']) && $eventquery['tag'] != '') {
                array_push($query_arr['tax_query'], array(
                    'taxonomy' => 'tag',
                    'field'    => 'slug',
                    'terms'    => $eventquery['tag']
                ));
            }
        }

        if (isset($eventquery['period']) && $eventquery['period'] != '') {
            $query_arr['meta_value'] = '%'.$eventquery['period'].'%';
        }

        $query = new WP_Query($query_arr);

        $this->query_events_by_occurrence_remove();

        return $query;

    }


    /**
     * Get one event with same meta fields as get_events(). Returns
     * - occurrence_startdate
     * - occurrence_enddate
     * - occurrence_url
     * - venue_text
     * - call_to_action_text
     */
    public function get_event ( $post_id ) {

        $this_post = get_post($post_id);

        $next_occurrence_found = false;

        while ( have_rows('occurrences', $post_id) && !$next_occurrence_found ) {
            the_row();

            $this_post->occurrence_startdate = get_sub_field('startdate');
            $this_post->occurrence_enddate = get_sub_field('enddate');
            $this_post->occurrence_url = get_sub_field('url');

            if (strtotime($this_post->occurrence_startdate) > time()) {
                 $next_occurrence_found = true;
            }
        }

        $this_post->venue_text = get_field('venue_text', $post_id);
        $this_post->call_to_action_text = get_field('call_to_action_text', $post_id);

        return $this_post;

    }


    /**
     * Get event categories
     * - Accepts args array for WP function get_terms()
     * - To order by manual sort value in admin use
     * $ukk_events->get_event_categories(array('orderby' => 'manual'))
     */
    public function get_event_categories ($query_arr = array()) {

        $query_arr['taxonomy'] = 'eventcat';
        
        $categories = get_categories($query_arr);  

        if (isset($query_arr['orderby']) && $query_arr['orderby'] == 'manual') {
            if (function_exists('get_field')) {
                usort($categories, function($a, $b) {
                   return get_field("eventcat_order", "category_".$a->term_id) - get_field("eventcat_order", "category_".$b->term_id);
                });
            }
        }

        return $categories;

    }


    /**
     * Get event tags ordered by acf field eventcat_order
     * - Accepts args array for WP function get_terms()
     */
    public function get_event_tags ($query_arr = array()) {

        $query_arr['taxonomy'] = 'tag';
        
        $tags = get_categories($query_arr);  

        return $tags;

    }


}

?>
