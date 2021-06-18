<?php 
/*
Plugin Name: WPCargo Extend
Description: Extend function from wpcargo
Author: PUYUP
Version: 1.0
Author URI: http://puyup.com
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


add_action( 'pre_get_posts', function( $q ) {
    if( $title = $q->get( '_meta_or_title' ) ) {
        add_filter( 'get_meta_sql', function( $sql ) use ( $title ) {
            global $wpdb;

            // Only run once:
            static $nr = 0; 
            if( 0 != $nr++ ) return $sql;

            // Modified WHERE
            $sql['where'] = sprintf(
                " AND ( %s OR %s ) ",
                $wpdb->prepare( "{$wpdb->posts}.post_title like '%%%s%%'", $title),
                mb_substr( $sql['where'], 5, mb_strlen( $sql['where'] ) )
            );

            return $sql;
        });
    }
});


function wpcargo_pagination_extend( $args = array() ) {    
    $defaults = array(
        'range'           => 4,
        'custom_query'    => FALSE,
        'previous_string' => esc_html__( 'Previous', 'wpcargo' ),
        'next_string'     => esc_html__( 'Next', 'wpcargo' ),
        'before_output'   => '<div id="wpcargo-pagination-wrapper"><nav class="wpcargo-pagination post-nav" aria-label="'.esc_html__('Shipments', 'wpcargo').'"><ul class="wpcargo-pagination pg-blue justify-content-center">',
        'after_output'    => '</ul></nav</div>'
    );    
    $args = wp_parse_args( 
        $args, 
        apply_filters( 'wpcargo_pagination_defaults', $defaults )
    );    
    $args['range'] = (int) $args['range'] - 1;
    if ( !$args['custom_query'] )
        $args['custom_query'] = @$GLOBALS['wp_query'];
    $count = (int) $args['custom_query']->max_num_pages;
    $page  = intval( get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) );
    $ceil  = ceil( $args['range'] / 2 );    
    if ( $count <= 1 )
        return FALSE;    
    if ( !$page )
        $page = 1;    
    if ( $count > $args['range'] ) {
        if ( $page <= $args['range'] ) {
            $min = 1;
            $max = $args['range'] + 1;
        } elseif ( $page >= ($count - $ceil) ) {
            $min = $count - $args['range'];
            $max = $count;
        } elseif ( $page >= $args['range'] && $page < ($count - $ceil) ) {
            $min = $page - $ceil;
            $max = $page + $ceil;
        }
    } else {
        $min = 1;
        $max = $count;
    }    
    $echo = '';
    $previous = intval($page) - 1;
    $previous = esc_attr( get_pagenum_link($previous) );    
    $firstpage = esc_attr( get_pagenum_link(1) );
    if ( $firstpage && (1 != $page) )
        $echo .= '<li class="previous wpcargo-page-item"><a class="wpcargo-page-link waves-effect waves-effect" href="' . $firstpage . '">' . esc_html__( 'First', 'wpcargo' ) . '</a></li>';
    if ( $previous && (1 != $page) )
        $echo .= '<li class="wpcargo-page-item" ><a class="wpcargo-page-link waves-effect waves-effect" href="' . $previous . '" title="' . esc_html__( 'previous', 'wpcargo') . '">' . $args['previous_string'] . '</a></li>';    
    if ( !empty($min) && !empty($max) ) {
        for( $i = $min; $i <= $max; $i++ ) {
            if ($page == $i) {
                $echo .= '<li class="wpcargo-page-item active"><span class="wpcargo-page-link waves-effect waves-effect">' . str_pad( (int)$i, 2, '0', STR_PAD_LEFT ) . '</span></li>';
            } else {
                $echo .= sprintf( '<li class="wpcargo-page-item"><a class="wpcargo-page-link waves-effect waves-effect" href="%s">%002d</a></li>', esc_attr( get_pagenum_link($i) ), $i );
            }
        }
    }    
    $next = intval($page) + 1;
    $next = esc_attr( get_pagenum_link($next) );
    if ($next && ($count != $page) )
        $echo .= '<li class="wpcargo-page-item"><a class="wpcargo-page-link waves-effect waves-effect" href="' . $next . '" title="' . esc_html__( 'next', 'wpcargo') . '">' . $args['next_string'] . '</a></li>';    
    $lastpage = esc_attr( get_pagenum_link($count) );
    if ( $lastpage ) {
        $echo .= '<li class="next wpcargo-page-item"><a class="wpcargo-page-link waves-effect waves-effect" href="' . $lastpage . '">' . esc_html__( 'Last', 'wpcargo' ) . '</a></li>';
    }
    if ( isset($echo) ){
        echo $args['before_output'] . $echo . $args['after_output'];
    }
}


add_action( 'init', 'remove_my_shortcodes',20 );
function remove_my_shortcodes() {
    remove_shortcode( 'wpcargo_account' );
    remove_shortcode( 'wpc-ca-account' );

    add_shortcode( 'wpcargo_account', 'account_shortcode_callback_extend' );
    add_shortcode( 'wpc-ca-account', 'account_shortcode_callback_extend' );
}


function account_shortcode_callback_extend() {
    global $wpdb, $wpcargo;
    ob_start();
    $get_results = $wpdb->get_results("SHOW TABLES LIKE '".$wpdb->prefix."wpcargo_custom_fields'");
    $plugins = get_option ( 'active_plugins', array () );
    if( !is_user_logged_in() ){
        ?>
        <div class="wpcargo-login" style="width: 450px; margin: 0 auto;">
            <?php wp_login_form(); ?>
        </div>
        <?php
        return false;
    }
    $user_id			= get_current_user_id();
    $user_info 			= get_userdata( $user_id );
    $user_full_name		= $wpcargo->user_fullname( $user_id );
    $shipment_sort 		= isset( $_GET['sort'] ) ? $_GET['sort'] : 'all' ;
    $paged				= ( get_query_var('page') ? get_query_var('page') : ( ( get_query_var('paged') ) ? get_query_var('paged') : 1 ) );
    $shipment_args = apply_filters( 'wpcargo_account_query', array(
        'post_type' 		=> 'wpcargo_shipment',
        'posts_per_page' 	=> 12,
        'orderby' 			=> 'date',
        'order' 			=> 'DESC',
        'paged' 			=> $paged,
        'meta_query' 		=> array(
                    'relation' => 'OR',
                    array(
                        'key' => 'registered_shipper',
                        'value' => $user_id
                    ),
                    array(
                        'key' => 'registered_receiver',
                        'value' => $user_id
                    ),
                )
        ), $shipment_sort
    );
    $shipment_query  	= new WP_Query($shipment_args);
    if(!empty($get_results) && is_array($plugins) && in_array('wpcargo-custom-field-addons/wpcargo-custom-field.php', $plugins) ){
        $template = wpcargo_include_template( 'account-cf.tpl' );
        require_once( $template );
    }else{
        $template = wpcargo_include_template( 'account.tpl' );
        require_once( $template );
    }
    // Reset Post Data
    wp_reset_postdata();
    $output = ob_get_clean();
    return $output;
}


add_filter( 'wpcfe_dashboard_arguments', 'wpcfe_dashboard_arguments_extend', 10, 1 );
function wpcfe_dashboard_arguments_extend( $args ) {
    // this as 's'
    $args['_meta_or_title'] = $args['s'];

    // don't use anymore to search with post_meta
    $args['s'] = '';

    return $args;
}


add_filter( 'wpcfe_dashboard_meta_query', 'wpcfe_dashboard_meta_query_extend', 10, 1 );
function wpcfe_dashboard_meta_query_extend( $meta_query ) {
    $s_shipment = isset( $_GET['wpcfes'] ) ? $_GET['wpcfes'] : '' ;

    // search by destination
    if ($s_shipment) {
        $meta_query[] = array(
            'key' => 'wpcargo_destination',
            'value' => $s_shipment,
        );
    }

    return $meta_query;
}