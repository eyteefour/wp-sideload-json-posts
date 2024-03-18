<?php
/**
 * Plugin Name:       Sideload JSON Posts
 * Description:       Sideloads posts from a preformatted and predefined JSON source.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            Eightyfour
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eightyfour
 *
 * @package           eightyfour
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( !class_exists( 'EightyfourSideloadJsonPosts' ) ) {

  class EightyfourSideloadJsonPosts {
  
    public static $engine = 'basic';
    public static $now;
    public const JSON_DATA_URL = 'https://jsonplaceholder.org/posts';
  
    public static function run( $engine = 'basic' ) {
  
      if ( !empty( self::$now ) ) return;

      self::$now = current_time( 'U' );

      // Two methods of storing and presenting data.
      // 'basic' (default) simply grabs, caches, and uses the JSON data to render out post data on the page.
      // 'post' grabs the JSON data and stores it as WP 'posts' in order to best leverage native styles, taxonomy, media, permalinks, archives, pagination, etc. as desired.
      if ( in_array( $engine, [ 'post' ] ) ) self::$engine = $engine;
  
      // Action hooks
      add_action( 'init', [ __CLASS__, 'init' ] );
      add_action( 'rest_api_init', [ __CLASS__, 'rest_api_init' ] );

      // Filter hooks

      // Scheduled actions
      add_action( 'EightyfourSideloadJsonPosts::__update_json', [ __CLASS__, '__update_json' ] );

      register_activation_hook( __FILE__, function() {
        // Off-load polling of the JSON data to scheduled task.
        if ( !wp_next_scheduled( 'EightyfourSideloadJsonPosts::__update_json' ) ) {
          wp_schedule_event( self::$now, 'hourly', 'EightyfourSideloadJsonPosts::__update_json' );
        }
      } );
  
      register_deactivation_hook( __FILE__, function() {
        // Clear scheduled task.
        wp_clear_scheduled_hook( 'EightyfourSideloadJsonPosts::__update_json' );
      } );
    }
  
    public static function init() {

      if ( 'basic' === self::$engine ) {

        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {

          // Register block if theme support exists.
          register_block_type( __DIR__ . '/build' );

        } else {

          // Register assets for legacy shortcode implementation.
          wp_register_style( 'eightyfour-sideload-json-posts-view-style', plugins_url( 'build/style-index.css', __FILE__ ) );
          wp_register_script( 'eightyfour-sideload-json-posts-view-script', plugins_url( 'build/view.js', __FILE__ ), [], false, [ 'in_footer' => true ] );

        }

        // Register shortcode.
        add_shortcode( 'eightyfour-sideload-json-posts', [ __CLASS__, '__shortcode_json_posts' ] );

        // Only enqueue assets when needed. Using "the_posts" hook as it's early enough that header scripts and styles can still be enqueued.
        add_filter( 'the_posts', function( $posts, $wp_query ) {
          if ( $wp_query->is_main_query() && !empty( $posts ) ) {
            foreach ( $posts as $post ) {
              if ( false !== strpos( $post->post_content, '[eightyfour-sideload-json-posts]' ) ) {
                wp_enqueue_style( 'eightyfour-sideload-json-posts-view-style' );
                wp_enqueue_script( 'eightyfour-sideload-json-posts-view-script' );
                break;
              }
            }
          }
          return $posts;
        }, 10, 2 );

      }
    }

    public static function rest_api_init() {

      register_rest_route( 'eightyfour/v1', '/json', [
        'methods' => 'GET',
        'callback' => function( $request ) {

          $json = get_transient( 'eightyfourSideloadJsonPosts' );
          if ( !is_null( $json ) ) {
            $posts = [];
            foreach( $json as $post ) {

              $date = esc_html( wp_date( 'F, jS Y', strtotime( $post->publishedAt ) ) );

              $posts[] = '<a href="' . $post->url . '" target="_blank"><aside><figure><img src="' . esc_attr( $post->thumbnail ) . '" aria-hidden="true"></figure></aside><main>' . ( !empty( $date ) ? '<date>' . $date . '</date>' : '' ) . '<header><h2>' . esc_html( $post->title ) . '</h2></header><article><p>' . wp_strip_all_tags( preg_replace( '/[\r\n+]{2,}/', '</p><p>', $post->content ) ) . '</p></article><footer aria-hidden="true"><span>Read more...</span></footer></main></a>';
            }
            // Assumes a 200 response will always be valid application/json data.
            return new WP_REST_Response( $posts, 200 );
          }

          return new WP_REST_Response( [ 'status' => 'error' ], 400 );

        },
        'permission_callback' => '__return_true',
      ] );

    }

    public static function __shortcode_json_posts( $atts = [], $content = '' ) {
      return '<div class="wp-block-eightyfour-sideload-json-posts">Loading…</div>';
    }
  
    public static function __update_json() {
  
      // Check whether an update is already running so as not to overwhelm the server.
      if ( false === get_transient( 'eightyfourSideloadJsonPostsIsUpdating' ) ) {

        set_transient( 'eightyfourSideloadJsonPostsIsUpdating', true );
  
        try {
  
          // Assumes this isn't a valid REST endpoint and isn't expecting well formed request headers.
          $response = wp_remote_get( self::JSON_DATA_URL, [
            'timeout' => 4,
          ] );
          if ( $response instanceof WP_Error ) {
  
            throw $response;
  
          } else if ( is_array( $response ) ) {
    
            // Assumes a 200 response will always be valid application/json data.
            if ( 200 === wp_remote_retrieve_response_code( $response ) && $json = json_decode( wp_remote_retrieve_body( $response ) ) ) {
    
              // Assumes the JSON data will always be an array of posts.
              if ( is_array( $json ) ) {
  
                if ( 'basic' === self::$engine ) {

                  // Leverege the Transients API to handle persistent data storange. It will automatically use an external object
                  // cache linke Redis or Memcached, if available, through the `wp_cache_set` method, otherwise the data is
                  // stored in the options table.
                  set_transient( 'eightyfourSideloadJsonPosts', $json );

                } else if ( 'post' === self::$engine ) {
  
                  global $wpdb;
                  
                  // Required to sideload media files.
                  require_once( ABSPATH . 'wp-admin/includes/media.php');
                  require_once( ABSPATH . 'wp-admin/includes/file.php');
                  require_once( ABSPATH . 'wp-admin/includes/image.php');
    
                  $posts = [];
                  foreach( (array)$wpdb->get_results( "SELECT p.ID AS post_id, pm.meta_value AS json_id, p.post_date, p.post_modified FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_json_id' WHERE p.post_type = 'post'" ) as $post ) {
                    // Assumes id values in the json data are always unique.
                    $posts[ $post->json_id ] = $post;
                  }
  
                  // Delete any posts whose id's aren not in the current JSON dataset. Assume they've been deleted.
                  foreach( array_diff( array_keys( $posts ), wp_list_pluck( $json, 'id' ) ) as $deleted_id ) {
                    wp_delete_post( $posts[ $deleted_id ]->post_id, true ); // Bypass trash.
                  }
    
                  // Loop through posts and create or update data as needed.
                  foreach( $json as $item ) {
    
                    if ( is_object( $item ) ) {
    
                      $images = [ 'thumbnail' => false, 'image' => false ];
                      foreach( array_keys( $images ) as $key ) {
                        if ( !empty( $item->{$key} ) ) {
    
                          // Grab attachment if it already exists.
                          $images[ $key ] = $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_json_src' WHERE p.post_type = 'attachment' AND pm.meta_value = %s LIMIT 1", $item->{$key} ) );
    
                          if ( is_null( $images[ $key ] ) ) {
                            // Create the attachment from remote url.
                            $images[ $key ] = media_sideload_image( $item->{$key}, 0, null, 'id' );
                            if ( is_int( $images[ $key ] ) ) {
                              // Store remote to check against future updates to avoid redownloading.
                              update_post_meta( $images[ $key ], '_json_src', $item->{$key} );
                            }
                          } else if ( !is_int( $images[ $key ] ) && filter_var( $images[ $key ], FILTER_VALIDATE_INT ) ) {
                            // Cast as integer. Just in case.
                            $images[ $key ] = (int)$images[ $key ];
                          }
    
                        }
    
                      }
    
                      $content = '';
    
                      // Insert image at the top of the post if it exists.
                      if ( !empty( $images['image'] ) ) $content .= '<figure>' . wp_get_attachment_image( $images['image'], 'full' ) . '</figure>';
    
                      // Assumes content is plain-text. Do some basic sanitization anyway.
                      if ( !empty( $item->content ) ) $content .= '<p>' . preg_replace( '/[\r\n]+/', '</p><p>', wp_strip_all_tags( $item->content ) ) . '</p>';
    
                      $post_data = [
                        'post_author' => 1,
                        'post_type' => 'post',
                        'post_status' => 'published' === $item->status ? 'publish' : 'draft', // Only two valid core statuses considered.
                        'post_title' => wp_strip_all_tags( $item->title ),
                        'post_name' => sanitize_title( $item->slug ),
                        'post_content' => $content,
                        'post_date' => wp_date( 'Y/m/d H:i:s', strtotime( $item->publishedAt ) ), // Assumes mysql datetime format Y/m/d H:i:s
                        'post_modified' => wp_date( 'Y/m/d H:i:s', strtotime( $item->updatedAt ) ), // Assumes mysql datetime format Y/m/d H:i:s
                      ];
    
                      if ( !isset( $posts[ $item->id ] ) ) {
    
                        // Create new post.
                        $post_id = wp_insert_post( $post_data, true );
                        $post_thumbnail = false;
    
                      } else if ( strtotime( $item->updatedAt ) > strtotime( $posts[ $item->id ]->post_modified ) ) {
    
                        // Update existing post.
                        $post_data['ID'] = (int)$posts[ $item->id ]->post_id;
                        $post_id = wp_update_post( (object)$post_data );
                        $post_thumbnail = get_post_meta( $post_id, '_thumbnail_id', true );
    
                      }
    
                      if ( 0 !== $post_id ) {
  
                        // Set taxonomy terms.
                        wp_set_object_terms( $post_id, !empty(  $item->category ) ?  [ 'sideload', $item->category ] : 'sideload', 'category' );
    
                        if ( !empty( $images['thumbnail'] ) ) update_post_meta( $post_id, '_thumbnail_id', $images['thumbnail'] ); // Update thumbnail.
                        else if ( !empty( $post_thumbnail) ) delete_post_meta( $post_id, '_thumbnail_id' ); // Remove thumbnail if empty but was previously set.
    
                        // Store JSON data because why not.
                        foreach( (array)$item as $key => $value ) {
                          update_post_meta( $post_id, '_json_' . $key, $value );
                        }
                      }
                    }
                  }
                }
              }
            }
          }
  
        } catch( WP_Error $e ) {
          // Error handling, debugging, logging...
        }
  
        delete_transient( 'eightyfourSideloadJsonPostsIsUpdating' );
      }

    }

  }

  EightyfourSideloadJsonPosts::run();

}