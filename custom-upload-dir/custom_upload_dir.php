<?php
/**
 * Plugin Name: Custom Upload Dir
 * Plugin URI: https://wordpress.org/plugins/custom-upload-dir/
 * Description: Keeps your uploaded files organized in smart folder structures.
 * Version: 3.5.0
 * Requires at least: 5.7
 * Requires PHP: 7.4
 * Author: Ulf Benjaminsson
 * Author URI: https://www.ulfbenjaminsson.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-upload-dir
 * Domain Path: /lang
 *
 * @package CustomUploadDir
 */

add_action( 'plugins_loaded', 'cud_init' );
add_action( 'init', 'cud_load_textdomain' );
register_activation_hook( __FILE__, 'cud_install_options' );
register_uninstall_hook( __FILE__, 'cud_delete_options' );

/** Register upload and administration hooks. */
function cud_init() {
	define( 'CUD_BASENAME', plugin_basename( __FILE__ ) );
	add_filter( 'wp_handle_upload_prefilter', 'cud_prepare_upload', PHP_INT_MAX );
	add_filter( 'wp_handle_sideload_prefilter', 'cud_prepare_upload', PHP_INT_MAX );
	add_filter( 'wp_handle_upload_overrides', 'cud_upload_overrides', PHP_INT_MAX, 2 );
	add_filter( 'wp_handle_sideload_overrides', 'cud_upload_overrides', PHP_INT_MAX, 2 );
	add_filter( 'pre_move_uploaded_file', 'cud_before_upload_move', 10, 2 );
	add_filter( 'rest_dispatch_request', 'cud_rest_upload_context', 10, 2 );
	add_filter( 'rest_request_after_callbacks', 'cud_rest_upload_complete', 10, 3 );
	add_action( 'xmlrpc_call', 'cud_xmlrpc_call', 10, 2 );
	add_action( 'admin_init', 'cud_register_settings' );
	add_action( 'admin_menu', 'cud_register_menu_item' );
	add_filter( 'plugin_row_meta', 'cud_set_plugin_meta', 10, 2 );
}

/** Register bundled translations after WordPress has initialized its locale. */
function cud_load_textdomain() {
	load_plugin_textdomain( 'custom-upload-dir', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}

/** Register the settings page and load assets only on that screen. */
function cud_register_menu_item() {
	$page = add_options_page( __( 'Custom Upload Dir Settings', 'custom-upload-dir' ), 'Custom Upload Dir', 'manage_options', CUD_BASENAME, 'cud_option_page' );
	add_action( 'admin_print_styles-' . $page, 'cud_admin_assets' );
}

/** Enqueue the settings page's small, self-contained controls. */
function cud_admin_assets() {
	wp_enqueue_style( 'cud-settings', plugins_url( 'settings.css', __FILE__ ), array(), '3.5.0' );
	wp_enqueue_script( 'cud-settings', plugins_url( 'settings.js', __FILE__ ), array(), '3.5.0', true );
}

/** Register the option with the WordPress Settings API. */
function cud_register_settings() {
	register_setting(
		'cud-settings-group',
		'custom_upload_dir',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'cud_sanitize_settings',
		)
	);
}

/**
 * Add the settings link to the plugin listing.
 *
 * @param array  $links Existing links.
 * @param string $file Plugin basename.
 * @return array Links.
 */
function cud_set_plugin_meta( $links, $file ) {
	if ( CUD_BASENAME === $file ) {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . CUD_BASENAME ) ) . '">' . esc_html__( 'Settings', 'custom-upload-dir' ) . '</a>';
	}
	return $links;
}

/** Delete settings on uninstall; uploaded files are retained. */
function cud_delete_options() {
	delete_option( 'custom_upload_dir' );
}

/**
 * Default option schema.
 *
 * @return array Defaults.
 */
function cud_default_options() {
	return array(
		'template'          => '',
		'test_ids'          => '',
		'all_parents'       => true,
		'flatten_hierarchy' => false,
		'only_leaf_nodes'   => false,
		'only_base_nodes'   => false,
	);
}

/**
 * Read settings with defaults, including installations with incomplete options.
 *
 * @return array Known settings with safe types.
 */
function cud_get_options() {
	$stored  = get_option( 'custom_upload_dir' );
	$options = cud_default_options();
	if ( is_array( $stored ) ) {
		foreach ( $options as $key => $default ) {
			if ( isset( $stored[ $key ] ) ) {
				if ( is_bool( $default ) ) {
					$options[ $key ] = in_array( $stored[ $key ], array( true, 1, '1', 'on' ), true );
				} elseif ( is_string( $stored[ $key ] ) ) {
					$options[ $key ] = $stored[ $key ];
				}
			}
		}
		$options['test_ids'] = cud_normalize_test_ids( isset( $stored['test_ids'] ) ? $stored['test_ids'] : '' );
	}
	return $options;
}

/** Initialize missing settings without rewriting an existing path template. */
function cud_install_options() {
	update_option( 'custom_upload_dir', cud_get_options() );
}

/**
 * Remember the current REST request after core's permission check.
 *
 * @param mixed           $result  Existing dispatch result.
 * @param WP_REST_Request $request Current request.
 * @return mixed Unchanged dispatch result.
 */
function cud_rest_upload_context( $result, $request ) {
	global $cud_rest_requests;
	$cud_rest_requests[] = $request;
	return $result;
}

/**
 * Restore context after a REST callback, including nested requests.
 *
 * @param mixed           $response Response from the endpoint.
 * @param array           $handler  Endpoint handler.
 * @param WP_REST_Request $request  Completed request.
 * @return mixed Unchanged response.
 */
function cud_rest_upload_complete( $response, $handler, $request ) {
	global $cud_rest_requests;
	if ( ! empty( $cud_rest_requests ) && end( $cud_rest_requests ) === $request ) {
		array_pop( $cud_rest_requests );
	}
	return $response;
}

/**
 * Resolve a parent without reading or changing WordPress's loop globals.
 *
 * @param array $file File being uploaded.
 * @return int Parent ID, or zero for an unattached upload.
 */
function cud_upload_post_id( $file = array() ) {
	global $cud_rest_requests;
	$post_id = 0;
	if ( ! empty( $cud_rest_requests ) ) {
		$request = end( $cud_rest_requests );
		$post_id = $request->get_param( 'post' );
	} elseif ( isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Core authorizes the upload; this value only supplies directory context.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = is_scalar( $_REQUEST['post_id'] ) ? (int) $_REQUEST['post_id'] : 0;
	} else {
		$query = wp_parse_url( wp_get_referer(), PHP_URL_QUERY );
		if ( is_string( $query ) ) {
			parse_str( $query, $params );
			$post_id = isset( $params['post'] ) ? $params['post'] : 0;
		}
	}
	$post_id = is_scalar( $post_id ) ? max( 0, (int) $post_id ) : 0;
	// Importers can provide the parent which core does not pass to upload_dir.
	return max( 0, (int) apply_filters( 'cud_upload_post_id', $post_id, $file ) );
}

/**
 * Compute and validate a destination before core's directory creation step.
 *
 * @param array $file File being uploaded.
 * @return array File with private routing context, or an upload error.
 */
function cud_prepare_upload( $file ) {
	global $cud_rest_requests;
	$customize = true;
	if ( ! empty( $cud_rest_requests ) ) {
		$request = end( $cud_rest_requests );
		// Other endpoints keep core's directory, even inside an enclosing upload.
		$customize = 'POST' === $request->get_method() && '/wp/v2/media' === untrailingslashit( $request->get_route() );
	}
	$context = (object) array(
		'post_id'   => $customize ? cud_upload_post_id( $file ) : 0,
		'file'      => $file,
		'customize' => $customize,
		'subdir'    => '',
	);
	if ( $customize && empty( $file['error'] ) ) {
		$path = cud_custom_upload_dir(
			array(
				'basedir' => '',
				'baseurl' => '',
				'subdir'  => '',
			),
			$context->post_id,
			$file
		);
		if ( ! empty( $path['error'] ) ) {
			$file['error'] = $path['error'];
		} else {
			$context->subdir = $path['subdir'];
		}
	}
	// Core preserves extra file fields between its prefilter and overrides hooks.
	$file['_cud_context'] = $context;
	return $file;
}

/**
 * Scope directory routing to one core upload or sideload.
 *
 * @param array|false $overrides Core upload overrides.
 * @param array       $file      File prepared by cud_prepare_upload().
 * @return array Upload overrides with failure cleanup.
 */
function cud_upload_overrides( $overrides, $file ) {
	global $cud_uploads;
	$overrides     = is_array( $overrides ) ? $overrides : array();
	$context       = $file['_cud_context'];
	$cud_uploads[] = $context;
	add_filter( 'upload_dir', 'cud_custom_upload_dir' );
	$error_handler = isset( $overrides['upload_error_handler'] ) ? $overrides['upload_error_handler'] : 'wp_handle_upload_error';

	$overrides['upload_error_handler'] = static function ( &$failed_file, $message ) use ( $context, $error_handler ) {
		cud_finish_upload( $context );
		return call_user_func_array( $error_handler, array( &$failed_file, $message ) );
	};
	return $overrides;
}

/**
 * Remove only this upload's context, restoring any enclosing upload.
 *
 * @param object $context Upload context to remove.
 */
function cud_finish_upload( $context ) {
	global $cud_uploads;
	if ( ! empty( $cud_uploads ) ) {
		$key = array_search( $context, $cud_uploads, true );
		if ( false !== $key ) {
			array_splice( $cud_uploads, $key, 1 );
		}
	}
	if ( empty( $cud_uploads ) ) {
		remove_filter( 'upload_dir', 'cud_custom_upload_dir' );
	}
}

/**
 * Release routing once core has selected the final destination.
 *
 * @param mixed $move Core's move override, usually null.
 * @param array $file File being moved.
 * @return mixed Unchanged move override; core still moves the file.
 */
function cud_before_upload_move( $move, $file ) {
	global $cud_uploads;
	if ( ! empty( $cud_uploads ) ) {
		$context = end( $cud_uploads );
		if ( $context->file['tmp_name'] === $file['tmp_name'] ) {
			cud_finish_upload( $context );
		}
	}
	return $move;
}

/**
 * Capture XML-RPC arguments without enabling directory routing prematurely.
 *
 * @param string $method XML-RPC method.
 * @param array  $args   Method arguments.
 */
function cud_xmlrpc_call( $method, $args ) {
	global $cud_rpc_file;
	$cud_rpc_file = null;
	remove_filter( 'pre_upload_error', 'cud_xmlrpc_prepare_upload', PHP_INT_MAX );
	remove_filter( 'upload_dir', 'cud_xmlrpc_upload_dir' );
	if ( 'metaWeblog.newMediaObject' === $method && isset( $args[3] ) && is_array( $args[3] ) ) {
		$cud_rpc_file = $args[3];
		add_filter( 'pre_upload_error', 'cud_xmlrpc_prepare_upload', PHP_INT_MAX );
	}
}

/**
 * Arm XML-RPC routing immediately before wp_upload_bits selects its directory.
 *
 * @param mixed $error Existing XML-RPC upload error.
 * @return mixed Unchanged error.
 */
function cud_xmlrpc_prepare_upload( $error ) {
	global $cud_rpc_file;
	remove_filter( 'pre_upload_error', 'cud_xmlrpc_prepare_upload', PHP_INT_MAX );
	$name = isset( $cud_rpc_file['name'] ) && is_string( $cud_rpc_file['name'] ) ? sanitize_file_name( $cud_rpc_file['name'] ) : '';
	$type = wp_check_filetype( $name );
	if ( ! $error && '' !== $name && ( ! empty( $type['ext'] ) || current_user_can( 'unfiltered_upload' ) ) ) {
		$post_id = isset( $cud_rpc_file['post_id'] ) && is_scalar( $cud_rpc_file['post_id'] ) ? max( 0, (int) $cud_rpc_file['post_id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}
		$path = cud_custom_upload_dir(
			array(
				'basedir' => '',
				'baseurl' => '',
				'subdir'  => '',
			),
			$post_id,
			$cud_rpc_file
		);
		if ( ! empty( $path['error'] ) ) {
			return $path['error'];
		}
		$cud_rpc_file['subdir'] = $path['subdir'];
		add_filter( 'upload_dir', 'cud_xmlrpc_upload_dir' );
	}
	return $error;
}

/**
 * XML-RPC only needs one lookup; later write failures cannot leave routing active.
 *
 * @param array $path Core upload directory data.
 * @return array Custom directory data.
 */
function cud_xmlrpc_upload_dir( $path ) {
	global $cud_rpc_file;
	remove_filter( 'upload_dir', 'cud_xmlrpc_upload_dir' );
	$file         = $cud_rpc_file;
	$cud_rpc_file = null;
	if ( empty( $path['error'] ) ) {
		$path['subdir'] = $file['subdir'];
		$path['path']   = $path['basedir'] . $path['subdir'];
		$path['url']    = $path['baseurl'] . $path['subdir'];
	}
	return $path;
}

/**
 * Select a relative destination; core creates directories and moves files.
 *
 * @param array    $path    Core upload directory data.
 * @param int|null $post_id Explicit parent, or current upload context.
 * @param array    $file    Explicit file context.
 * @return array Directory data, or an error for an invalid generated path.
 */
function cud_custom_upload_dir( $path, $post_id = null, $file = array() ) {
	global $cud_uploads;
	if ( ! empty( $path['error'] ) ) {
		return $path;
	}
	if ( null === $post_id && ! empty( $cud_uploads ) ) {
		$context = end( $cud_uploads );
		if ( ! $context->customize ) {
			return $path;
		}
		$subdir = $context->subdir;
	} else {
		$subdir = cud_generate_path( $post_id, $file );
	}
	$subdir = cud_normalize_subdir( $subdir );
	if ( false === $subdir ) {
		$path['error'] = __( 'Custom Upload Dir: use a relative directory without a drive, URL, control characters, or . and .. segments.', 'custom-upload-dir' );
		return $path;
	}
	$path['subdir'] = $subdir;
	$path['path']   = $path['basedir'] . $path['subdir'];
	$path['url']    = $path['baseurl'] . $path['subdir'];
	return $path;
}

/**
 * Normalize and validate a path relative to the site's upload base.
 *
 * @param mixed $subdir Generated path or saved template.
 * @return string|false Normalized path, or false for an unsafe/invalid value.
 */
function cud_normalize_subdir( $subdir ) {
	if ( ! is_string( $subdir ) ) {
		return false;
	}
	$subdir = str_replace( '\\', '/', $subdir );
	if ( preg_match( '#[\x00-\x1f\x7f]|^/*[a-zA-Z][a-zA-Z0-9+.-]*:#', $subdir ) || preg_match( '#(^|/)\.\.?(/|$)#', $subdir ) ) {
		return false;
	}
	$subdir = trim( preg_replace( '#/+#', '/', $subdir ), '/' );
	return '' === $subdir ? '' : '/' . $subdir;
}

/**
 * Read a configured template.
 *
 * @param string|false $post_type Option key; currently only template is supported.
 * @return string Template.
 */
function cud_get_template( $post_type = false ) {
	$settings = cud_get_options();
	return isset( $settings[ $post_type ] ) && is_string( $settings[ $post_type ] ) ? $settings[ $post_type ] : '';
}

/**
 * Expand a template from explicit post and file data without changing globals.
 *
 * @param int|null $post_id Parent ID; zero means unattached, null resolves request context.
 * @param array    $file    File data, including its original name.
 * @return string Relative upload subdirectory.
 */
function cud_generate_path( $post_id = null, $file = array() ) {
	$post_id      = null === $post_id ? cud_upload_post_id( $file ) : max( 0, (int) $post_id );
	$my_post      = $post_id ? get_post( $post_id ) : null;
	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;
	$customdir    = cud_get_template( 'template' );
	$customdir    = strtr(
		$customdir,
		array(
			'%ID%'          => '%post_id%',
			'%post_name%'   => '%postname%',
			'%post_author%' => '%author%',
			'%post_parent%' => '%parent_name%',
			'%mime_type%'   => '%file_type%',
		)
	);
	$post_type    = '';
	$post_name    = '';
	$author       = '';
	$date         = current_time( 'Y m d H i s' );
	if ( $my_post ) {
		$post_id   = $my_post->ID;
		$post_type = $my_post->post_type;
		$author    = $my_post->post_author;
		$post_name = $my_post->post_name;
		if ( '' === $post_name ) {
			$post_name = $my_post->post_title ? sanitize_title( $my_post->post_title ) : (string) $post_id;
		}
		if ( '0000-00-00 00:00:00' !== $my_post->post_date ) {
			$post_date = mysql2date( 'Y m d H i s', $my_post->post_date, false );
			if ( $post_date ) {
				$date = $post_date;
			}
		}
	} else {
		$post_id = '';
	}
	$date         = explode( ' ', $date );
	$filetype     = ! empty( $file['name'] ) ? wp_check_filetype( $file['name'] ) : array();
	$replacements = array(
		'%post_id%'   => (string) $post_id,
		'%postname%'  => $post_name,
		'%post_type%' => $post_type,
		'%year%'      => $date[0],
		'%monthnum%'  => $date[1],
		'%month%'     => $date[1],
		'%day%'       => $date[2],
		'%hour%'      => $date[3],
		'%minute%'    => $date[4],
		'%second%'    => $date[5],
		'%file_ext%'  => ! empty( $filetype['ext'] ) ? $filetype['ext'] : '',
		'%file_type%' => ! empty( $filetype['type'] ) ? $filetype['type'] : '',
	);
	// Only query user, parent and taxonomy data when the template needs it.
	$resolvers = array(
		'%author%'          => array( 'cud_get_user_name', $author ),
		'%author_role%'     => array( 'cud_get_user_role', $author ),
		'%current_user%'    => array( 'cud_get_user_name', $user_id ),
		'%parent_name%'     => array( 'cud_get_parent_slug', $my_post ),
		'%post_category%'   => array( 'cud_get_category_name', $post_id ),
		'%post_categories%' => array( 'cud_get_categories_name', $post_id ),
	);
	foreach ( $resolvers as $tag => $resolver ) {
		if ( false !== strpos( $customdir, $tag ) ) {
			$replacements[ $tag ] = call_user_func( $resolver[0], $resolver[1] );
		}
	}
	/**
	 * Supply custom token values before unknown tokens are removed.
	 *
	 * @param array $tokens  Map of %token% names to scalar replacements.
	 * @param int|string $post_id Parent ID, or an empty string for unattached uploads.
	 * @param array $file Original file context, including its name when available.
	 */
	$tokens = apply_filters( 'cud_path_tokens', array(), $post_id, $file );
	if ( is_array( $tokens ) ) {
		foreach ( $tokens as $token => $value ) {
			if ( is_string( $token ) && preg_match( '/^%[^%\/]+%$/', $token ) && is_scalar( $value ) && ! array_key_exists( $token, $replacements ) ) {
				$replacements[ $token ] = (string) $value;
			}
		}
	}
	if ( preg_match_all( '/%(.*?)%/', $customdir, $matches ) ) {
		foreach ( $matches[1] as $taxonomy ) {
			$token = '%' . $taxonomy . '%';
			if ( ! array_key_exists( $token, $replacements ) ) {
				$replacements[ $token ] = taxonomy_exists( $taxonomy ) ? cud_get_taxonomies( $post_id, $taxonomy ) : '';
			}
		}
	}
	// Expand once: percent-encoded slugs and replacement values are literal data.
	$customdir = strtr( $customdir, $replacements );
	$customdir = untrailingslashit( cud_leadingslashit( $customdir ) );
	$customdir = preg_replace( '#/+#', '/', $customdir );
	return apply_filters( 'cud_generate_path', $customdir, $post_id );
}
/**
 * Resolve the parent's URL component or the post type's rewrite slug.
 *
 * @param WP_Post|null $post Upload parent.
 * @return string Parent component.
 */
function cud_get_parent_slug( $post ) {
	if ( empty( $post ) ) {
		return '';}
	$parent_slug = '';
	if ( $post->post_parent ) {
		$parent_url  = get_permalink( $post->post_parent );
		$parent_slug = $parent_url ? basename( $parent_url ) : '';
	} else {
		$post_type = get_post_type_object( $post->post_type );
		if ( $post_type && is_array( $post_type->rewrite ) && ! empty( $post_type->rewrite['slug'] ) ) {
			$parent_slug = $post_type->rewrite['slug'];
		}
	}
	return $parent_slug;
}
/**
 * Build a path from the post's taxonomy terms and hierarchy preferences.
 *
 * @param int|string $post_id Parent post ID.
 * @param string     $taxonomy Taxonomy name.
 * @param int        $count Optional term limit; -1 includes all terms.
 * @return string Term path.
 */
function cud_get_taxonomies( $post_id, $taxonomy, $count = -1 ) {
	if ( '' === $post_id ) {
		return '';}
	$terms = wp_get_object_terms(
		$post_id,
		$taxonomy,
		array(
			'orderby' => 'slug',
			'order'   => 'ASC',
			'fields'  => 'all',
		)
	);
	if ( ! is_array( $terms ) ) {
		return '';}
	$terms   = array_filter(
		$terms,
		static function ( $term ) {
			return $term instanceof WP_Term;
		}
	);
	$options = cud_get_options();
	if ( $options['all_parents'] && ! $options['only_leaf_nodes'] ) {
		$terms = cud_get_parents( $terms, $taxonomy );
	}
	if ( $count > 0 ) {
		$terms = array_slice( $terms, 0, $count );
	}
	$levels = array( 0 => $terms );
	if ( is_taxonomy_hierarchical( $taxonomy ) ) {
		$levels = cud_sort_by_levels( $terms, $taxonomy );
		if ( $options['only_leaf_nodes'] ) {
			$levels = cud_find_leafs( $levels );
		} elseif ( $options['only_base_nodes'] ) {
			$levels = array_slice( $levels, 0, 1 ); // get rid of all levels beyond the first.
		}
	}
	return cud_build_term_path( $levels, $taxonomy, $options['flatten_hierarchy'] );
}
/**
 * Join sorted term slugs by hierarchy level.
 *
 * @param array  $levels Terms grouped by depth.
 * @param string $taxonomy Taxonomy name, retained for existing callers.
 * @param bool   $flatten_hierarchy Whether to join levels with hyphens.
 * @return string Term path.
 */
function cud_build_term_path( $levels, $taxonomy, $flatten_hierarchy ) {
	$path = '';
	foreach ( $levels as $level => $terms ) {
		$slugs = array_unique( wp_list_pluck( $terms, 'slug' ) );
		sort( $slugs, SORT_STRING );
		$path .= implode( '-', $slugs ) . '/';
	}
	$path = untrailingslashit( $path );
	if ( $flatten_hierarchy ) {
		$path = str_replace( '/', '-', $path );
	}
	return $path;
}
/**
 * Remove terms that are parents of other included terms.
 *
 * @param array $levels Terms grouped by depth.
 * @return array Remaining levels, in their original order.
 */
function cud_find_leafs( $levels ) {
	$parents = array();
	$leafs   = array();
	$levels  = array_reverse( $levels );
	foreach ( $levels as $level => $terms ) {
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->term_id, $parents, true ) ) {
				if ( empty( $leafs[ $level ] ) ) {
					$leafs[ $level ] = array();
				}
				$leafs[ $level ][] = $term;
			}
			$parents[] = $term->parent;
		}
	}
	return array_reverse( $leafs );
}
/**
 * Include existing ancestor terms, skipping deleted or invalid ancestors.
 *
 * @param WP_Term[] $terms Assigned terms.
 * @param string    $taxonomy Taxonomy name.
 * @return WP_Term[] Terms and their ancestors.
 */
function cud_get_parents( $terms, $taxonomy ) {
	$hierarcy = array();
	foreach ( $terms as $term ) {
		$parent_ids = get_ancestors( $term->term_id, $taxonomy );
		$parents    = array();
		if ( is_array( $parent_ids ) ) {
			foreach ( $parent_ids as $id ) {
				$parent = get_term( $id, $taxonomy );
				if ( $parent instanceof WP_Term ) {
					$parents[] = $parent;
				}
			}
		}
		$parents[] = $term;
		$hierarcy  = array_merge( $hierarcy, $parents );
	}
	return $hierarcy;
}
/**
 * Group terms by hierarchy depth.
 *
 * @param WP_Term[] $terms Terms to group.
 * @param string    $taxonomy Taxonomy name.
 * @return array Terms keyed by depth.
 */
function cud_sort_by_levels( $terms, $taxonomy ) {
	$levels = array();
	foreach ( $terms as $term ) {
		$level = count( get_ancestors( $term->term_id, $taxonomy ) );
		if ( empty( $levels[ $level ] ) ) {
			$levels[ $level ] = array();
		}
		$levels[ $level ][] = $term;
	}
	ksort( $levels );
	return $levels;
}
/**
 * Legacy ancestor helper, retained for external callers.
 *
 * @param int    $term_id Term ID.
 * @param string $taxonomy Taxonomy name.
 * @return string Ancestor slugs.
 */
function cud_get_term_parents( $term_id, $taxonomy ) {
	$parent_ids = get_ancestors( $term_id, $taxonomy );
	if ( ! is_array( $parent_ids ) ) {
		return '';}
	$slugs = array();
	foreach ( $parent_ids as $parent_id ) {
		$term = get_term( $parent_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$slugs[] = $term->slug;
		}
	}
	return implode( '/', $slugs );
}
/**
 * Get a user's slug without assuming the user still exists.
 *
 * @param int|string $user_id User ID.
 * @return string User slug.
 */
function cud_get_user_name( $user_id ) {
	if ( ! is_numeric( $user_id ) ) {
		return '';}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';}
	return sanitize_title( $user->user_nicename );
}
/**
 * Get the user's first role, allowing users with no roles.
 *
 * @param int|string $user_id User ID.
 * @return string Role slug.
 */
function cud_get_user_role( $user_id ) {
	if ( ! is_numeric( $user_id ) ) {
		return '';}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';}
	$roles = is_array( $user->roles ) ? array_values( $user->roles ) : array();
	return empty( $roles ) ? '' : sanitize_title( $roles[0] );
}
/**
 * Resolve the legacy single-category token.
 *
 * @param int|string $post_id Post ID.
 * @return string Category slug.
 */
function cud_get_category_name( $post_id ) {
	if ( '' === $post_id ) {
		return '';}
	$categories = get_the_category( $post_id );
	if ( ! $categories ) {
		return '';}
	return $categories[0]->category_nicename;
}
/**
 * Resolve the legacy flat-category token.
 *
 * @param int|string $post_id Post ID.
 * @return string Category slugs.
 */
function cud_get_categories_name( $post_id ) {
	if ( '' === $post_id ) {
		return '';}
	$categories = get_the_category( $post_id );
	if ( ! $categories ) {
		return '';}
	$cats = array();
	foreach ( $categories as $cat ) {
		$cats[] = $cat->category_nicename;
	}
	return implode( '-', $cats );
}
/**
 * Add a leading directory separator to a non-empty path.
 *
 * @param string $s Path.
 * @return string Path with a leading slash.
 */
function cud_leadingslashit( $s ) {
	return ( $s && '/' !== $s[0] ) ? '/' . $s : $s;
}
/**
 * Normalize the preview's comma-separated positive post IDs.
 *
 * @param mixed $ids Saved or submitted IDs.
 * @return string Unique positive IDs.
 */
function cud_normalize_test_ids( $ids ) {
	$items = is_array( $ids ) ? $ids : ( is_scalar( $ids ) ? explode( ',', (string) $ids ) : array() );
	$clean = array();
	foreach ( $items as $id ) {
		if ( is_scalar( $id ) && preg_match( '/^\d+$/', trim( (string) $id ) ) && (int) $id > 0 ) {
			$clean[] = (int) $id;
		}
	}
	return implode( ',', array_unique( $clean ) );
}

/**
 * Validate settings without depending on a submit button or changing core options.
 * Missing fields retain their current values; the form submits explicit checkbox zeros.
 *
 * @param mixed $options Submitted settings.
 * @return array Valid settings, or the previous settings on invalid input.
 */
function cud_sanitize_settings( $options ) {
	$previous = cud_get_options();
	if ( ! is_array( $options ) ) {
		add_settings_error( 'custom_upload_dir', 'cud_invalid_options', __( 'Settings were not saved: expected a settings array.', 'custom-upload-dir' ) );
		return $previous;
	}
	$clean = array_merge( $previous, array_intersect_key( $options, $previous ) );
	$path  = cud_normalize_subdir( $clean['template'] );
	if ( false === $path ) {
		add_settings_error( 'custom_upload_dir', 'cud_invalid_template', __( 'Settings were not saved: use a path inside the upload folder, without a drive, URL, control characters, or . and .. segments.', 'custom-upload-dir' ) );
		return $previous;
	}
	$clean['template'] = $path;
	$clean['test_ids'] = cud_normalize_test_ids( $clean['test_ids'] );
	foreach ( array( 'all_parents', 'flatten_hierarchy', 'only_leaf_nodes', 'only_base_nodes' ) as $key ) {
		if ( ! in_array( $clean[ $key ], array( true, false, 1, 0, '1', '0', 'on', '' ), true ) ) {
			add_settings_error( 'custom_upload_dir', 'cud_invalid_checkbox', __( 'Settings were not saved: a checkbox value was invalid.', 'custom-upload-dir' ) );
			return $previous;
		}
		$clean[ $key ] = in_array( $clean[ $key ], array( true, 1, '1', 'on' ), true );
	}
	if ( $clean['only_leaf_nodes'] ) {
		$clean['all_parents']     = false;
		$clean['only_base_nodes'] = false;
	}
	return $clean;
}

/** Render the settings form and read-only previews. */
function cud_option_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage these settings.', 'custom-upload-dir' ) );
	}
	$options    = cud_get_options();
	$tokens     = array(
		'file_type'    => __( 'The file MIME type (for example image/jpeg)', 'custom-upload-dir' ),
		'file_ext'     => __( 'The file extension', 'custom-upload-dir' ),
		'post_id'      => __( 'The post ID', 'custom-upload-dir' ),
		'author'       => __( 'The post author', 'custom-upload-dir' ),
		'author_role'  => __( "The post author's role", 'custom-upload-dir' ),
		'postname'     => __( "The post's URL slug", 'custom-upload-dir' ),
		'parent_name'  => __( 'The parent URL slug', 'custom-upload-dir' ),
		'post_type'    => __( 'The post type', 'custom-upload-dir' ),
		'year'         => __( "The post's year (YYYY)", 'custom-upload-dir' ),
		'monthnum'     => __( "The post's month (MM)", 'custom-upload-dir' ),
		'day'          => __( "The post's day (DD)", 'custom-upload-dir' ),
		'hour'         => __( 'The hour (HH)', 'custom-upload-dir' ),
		'minute'       => __( 'The minute (MM)', 'custom-upload-dir' ),
		'second'       => __( 'The second (SS)', 'custom-upload-dir' ),
		'current_user' => __( 'The currently logged in user', 'custom-upload-dir' ),
		'category'     => __( "The post's categories (see: Taxonomies)", 'custom-upload-dir' ),
		'post_tag'     => __( "The post's tags (see: Taxonomies)", 'custom-upload-dir' ),
	);
	$checkboxes = array(
		'all_parents'       => __( 'Always get all parents', 'custom-upload-dir' ),
		'flatten_hierarchy' => __( 'Flat filesystem (no subfolders for children)', 'custom-upload-dir' ),
		'only_leaf_nodes'   => __( 'Ignore parents (use only leaf nodes)', 'custom-upload-dir' ),
		'only_base_nodes'   => __( 'Ignore children (use only the root of hierarchy)', 'custom-upload-dir' ),
	);
	?>
	<div class="wrap cud-settings">
		<h1>Custom Upload Dir</h1>
		<p><?php esc_html_e( 'Choose where new uploads are stored. Existing files and their URLs are not moved or renamed.', 'custom-upload-dir' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'cud-settings-group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="template"><?php esc_html_e( 'Build a path template:', 'custom-upload-dir' ); ?></label></th>
					<td>
						<input id="template" name="custom_upload_dir[template]" type="text" class="large-text code" value="<?php echo esc_attr( $options['template'] ); ?>" aria-describedby="cud-template-help" />
						<p class="description" id="cud-template-help"><?php esc_html_e( 'Separate folders with /. This template applies to all post types. An empty template uses the upload base folder.', 'custom-upload-dir' ); ?></p>
						<p class="description"><?php esc_html_e( 'Include /%year%/%monthnum%/ if you want dated folders. Saving here does not change the WordPress Media settings or automatically add date folders.', 'custom-upload-dir' ); ?></p>
						<ul class="cud-tokens">
							<?php foreach ( $tokens as $token => $description ) : ?>
								<li><button type="button" class="button cud-token" data-token="<?php echo esc_attr( '%' . $token . '%' ); ?>"><code><?php echo esc_html( '%' . $token . '%' ); ?></code></button> <?php echo esc_html( $description ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p class="description"><?php esc_html_e( 'Custom taxonomy names also work as %taxonomy% tokens. Dates use the post date; uploads without a post use current site-local time and omit post-specific tokens.', 'custom-upload-dir' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Settings for taxonomy hierarchies:', 'custom-upload-dir' ); ?></th>
					<td>
						<?php foreach ( $checkboxes as $key => $label ) : ?>
							<input type="hidden" name="custom_upload_dir[<?php echo esc_attr( $key ); ?>]" value="0" />
							<p><label><input type="checkbox" name="custom_upload_dir[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $options[ $key ] ); ?> /> <?php echo esc_html( $label ); ?></label></p>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'For example: /animals/cats, /animals-cats when flattened, /cats for leaves only, or /animals for roots only. Same-level terms are sorted alphabetically. Leaves-only takes precedence over the parent and root options.', 'custom-upload-dir' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="test_ids"><?php esc_html_e( 'Preview post IDs', 'custom-upload-dir' ); ?></label></th>
					<td><input id="test_ids" name="custom_upload_dir[test_ids]" type="text" class="regular-text" value="<?php echo esc_attr( $options['test_ids'] ); ?>" /><p class="description"><?php esc_html_e( 'Enter comma-separated post IDs, then save to refresh the preview. Previews use the saved template and a sample file named example.jpg; no folders are created.', 'custom-upload-dir' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Saved template preview', 'custom-upload-dir' ); ?></h2>
		<p><?php esc_html_e( 'Try to always name and save your post before uploading attachments.', 'custom-upload-dir' ); ?></p>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Post', 'custom-upload-dir' ); ?></th><th><?php esc_html_e( 'Upload directory', 'custom-upload-dir' ); ?></th></tr></thead>
			<tbody>
				<?php
				$uploads = wp_get_upload_dir();
				$ids     = array_merge( array( 0 ), array_filter( explode( ',', $options['test_ids'] ) ) );
				foreach ( $ids as $id ) :
					$preview_post = $id ? get_post( (int) $id ) : null;
					$preview      = cud_custom_upload_dir( $uploads, (int) $id, array( 'name' => 'example.jpg' ) );
					?>
					<tr><td>
						<?php
						if ( ! $id ) {
							esc_html_e( 'Unattached upload', 'custom-upload-dir' );
						} elseif ( $preview_post ) {
							printf( '<a href="%s">%s (#%d)</a>', esc_url( get_permalink( $preview_post ) ), esc_html( get_the_title( $preview_post ) ), (int) $id );
						} else {
							/* translators: %d: A post ID entered for preview. */
							printf( esc_html__( 'Post #%d was not found; showing an unattached upload.', 'custom-upload-dir' ), (int) $id );
						}
						?>
					</td><td><code><?php echo esc_html( $preview['error'] ? $preview['error'] : $preview['path'] ); ?></code></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
