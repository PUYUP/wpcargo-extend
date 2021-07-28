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


/**
 * Load assets file
 */
function footer_scripts() { ?>
    <script type="text/javascript">
        (function ($) {
            $(document).ready(function () {
                $(document).on('keyup', '#wpcfe-packages-repeater input', function(value) {
                    var this$ = $(this);
                    var tr$ = this$.closest('tr');

                    var inputLength = tr$.find("input[name*='[wpc-pm-length]']").val();
                    var inputWidth = tr$.find("input[name*='[wpc-pm-width]']").val();
                    var inputHeight = tr$.find("input[name*='[wpc-pm-height]']").val();

                    // Volume
                    var volume = (inputLength * inputWidth * inputHeight) / 4000;
                    tr$.find("input[name*='[tvolume]']").val(Math.round(volume* 1) / 1);
                    
                    // Cubic
                    var cubic = (inputLength * inputWidth * inputHeight) / 1000000;
                    tr$.find("input[name*='[tkubikasi]']").val(Math.round(cubic* 1000) / 1000);
                });
            });
        })(jQuery);
    </script>
<?php }
add_action( 'wp_footer', 'footer_scripts' );


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


add_action( 'plugins_loaded', 'alter_wpcargo' );
function alter_wpcargo() {
    remove_action('wpcargo_after_package_totals', 'wpcargo_after_package_details_callback', 10, 1 );
    remove_action('wpcargo_after_package_details_script', 'wpcargo_after_package_details_script_callback', 10, 1 );
}


add_action('wpcargo_after_package_totals', 'wpcargo_after_package_details_callback_extend', 10, 1 );
function wpcargo_after_package_details_callback_extend( $shipment ){
    $class = is_admin() ? 'one-third' : 'wpcargo-col-md-4' ;
    $style = is_admin() ? 'style="display:block;overflow:hidden;margin-bottom:36px"' : '' ;
	$shipment_id = (!empty ( $shipment ) ) ? $shipment->ID : '';
	$package_volumetric = (!empty ( $shipment ) ) ? wpcargo_package_volumetric( $shipment->ID ) : '0';
	$package_actual_weight = (!empty ( $shipment ) ) ? wpcargo_package_actual_weight( $shipment->ID ) : '0';
	$package_cubicmetric = (!empty ( $shipment ) ) ? wpcargo_package_cubicmetric( $shipment->ID ) : '0.00';
	$package_actual_weight = (!empty ( $shipment ) ) ? wpcargo_package_actual_weight( $shipment->ID ) : '0';
	$package_actual_koli = (!empty ( $shipment ) ) ? wpcargo_package_actual_koli( $shipment->ID ) : '0';
	?>
	<div id="package-weight-info" class="table-responsive" <?php echo $style; ?>>
		<table class="table table-hover table-sm">
			<thead>
				<tr class="text-center">
					<th><?php echo apply_filters( 'wpcargo_package_actual_koli_label', esc_html__('Koli', 'wpcargo') ); ?></th>
					<th><?php echo apply_filters( 'wpcargo_package_actual_weight_label', esc_html__('Berat', 'wpcargo') ); ?></th>
					<th><?php echo apply_filters( 'wpcargo_package_cubicmetric_label', esc_html__('Kubikasi', 'wpcargo') ); ?></th>
					<th><?php echo apply_filters( 'wpcargo_package_volumetric_label', esc_html__('Volume', 'wpcargo') ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr class="text-center">
					<td><span id="package_actual_koli"><?php echo $package_actual_koli; ?></span></td>
					<td><span id="package_actual_weight"><?php echo $package_actual_weight.'</span> '.wpcargo_package_settings()->weight_unit; ?></td>
					<td><span id="package_cubicmetric"><?php echo $package_cubicmetric.'</span> '.wpcargo_package_settings()->weight_unit; ?></td>
					<td><span id="package_volumetric"><?php echo $package_volumetric.'</span> '.wpcargo_package_settings()->weight_unit; ?></td>
				<tr>
			</tbody>
			<?php do_action('wpcargo_after_package_totals_section', $shipment_id); ?>
		</table>
	</div>
	<?php
	do_action('wpcargo_after_package_details_script', $shipment);
}


add_action('wpcargo_after_package_details_script', 'wpcargo_after_package_details_script_callback_extend', 10, 1 );
function wpcargo_after_package_details_script_callback_extend( $shipment ){
	$dim_meta   = wpcargo_package_dim_meta();
	$qty_meta   = wpcargo_package_qty_meta();
	$weight_meta = wpcargo_package_weight_meta();
	$divisor    = wpcargo_package_settings()->divisor ? wpcargo_package_settings()->divisor : 1;
	$divisor_cubic    = wpcargo_package_settings()->divisor_cubic ? wpcargo_package_settings()->divisor_cubic : 1;
	$dim_meta   = json_encode( $dim_meta );
	?>
	<script>
		var mainContainer   = 'table tbody[data-repeater-list="<?php echo WPCARGO_PACKAGE_POSTMETA; ?>"]';
		var divisor         = <?php echo $divisor ?>;
		var divisor_cubic         = <?php echo $divisor_cubic ?>;
		var dimMeta         = <?php echo $dim_meta; ?>;
		var qtyMeta         = "<?php echo $qty_meta; ?>";
		var weightMeta      = "<?php echo $weight_meta; ?>";    
		jQuery(document).ready(function($){
			if( mainContainer.length > 0 ){
				$( mainContainer ).on( 'change keyup', 'input', function(){
					var totalQTY        = 0;
					var totalWeight     = 0;
					var totalVolumetric = 0;
					var totalVolume		= 0;
					var totalCubicmetric = 0;
					var totalCubic		= 0;
				 
					$( mainContainer + ' tr' ).each(function(){
						var currentVolumetric = 1; 
						var currentCubicmetric = 1;
						var currentQTY        = 0;
						var packageWeight     = 0;
						$(this).find('input').each(function(){
							var currentField    = $(this);
							var className       = $( currentField ).attr('name');
							// Exclude in the loop field without name attribute
							if ( typeof className === "undefined" ){
									return;
							}
							// Get the QTY
							if ( className.indexOf(qtyMeta) > -1 ){
								var pQty = $( currentField ).val() == '' ? 0 : $( currentField ).val() ;
								totalQTY += parseFloat( pQty );
								currentQTY = parseFloat( pQty );
							}
							// Get the weight
							if ( className.indexOf(weightMeta) > -1 ){
								var pWeight = $( currentField ).val() == '' ? 0 : $( currentField ).val() ;
								packageWeight += parseFloat( pWeight );
							}
							
							// Calculate the volumetric                       
							$.each( dimMeta, function( index, value ){   
												  
								if ( className.indexOf(value) == -1 ){
									return;
								}
								currentVolumetric *= $( currentField ).val();
							} );

							// Calculate the cubicmetric                       
							$.each( dimMeta, function( index, value ){   

								if ( className.indexOf(value) == -1 ){
									return;
								}
								currentCubicmetric *= $( currentField ).val();
							} );
						});
						totalVolumetric += currentQTY * ( currentVolumetric / divisor );
						totalCubicmetric += currentQTY * ( currentCubicmetric / divisor_cubic );
						totalWeight     += currentQTY * packageWeight;
						totalVolume		+= currentQTY * currentVolumetric;
						totalCubic		+= currentQTY * currentCubicmetric;
					});
					$('#package-weight-info #total_volume_metric_output').text( totalVolume.toFixed(2) );
					$('#package-weight-info #package_volumetric').text( Math.round(totalVolumetric.toFixed(2)) );
					$('#package-weight-info #total_cubic_metric_output').text( totalCubic.toFixed(2) );
					$('#package-weight-info #package_cubicmetric').text( totalCubicmetric.toFixed(2) );
					$('#package-weight-info #package_actual_weight').text( Math.ceil(totalWeight.toFixed(2)) );
					$('#package-weight-info #package_actual_koli').text( totalQTY );
				});
			}
		});
	</script>
    <?php
}