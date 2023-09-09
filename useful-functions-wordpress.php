<?php

/**
 * Removes x-powered-by header which is set by PHP
 */
header_remove('x-powered-by');

/**
 * Remove X-Pingback header
 */
add_filter('pings_open', function() {
    return false;
});

/**
 * Disables xmlrpc.php
 * Disable only if your site does not require use of xmlrpc
 */
add_filter('xmlrpc_enabled', function() {
    return false;
});

/**
 * Disables REST API completely for non-logged in users
 * Use this option only if your site does not require use of REST API
 */
// add_filter('rest_authentication_errors', function($result) {
//    return (is_user_logged_in()) ? $result : new WP_Error('rest_not_logged_in', 'You are not currently logged in.', array('status' => 401));
// });

/**
 * Disables Wordpress default REST API endpoints.
 * Use this option if your plugins require use of REST API, but would still like to disable core endpoints.
 */
add_filter('rest_endpoints', function($endpoints) {
    // If user is logged in, allow all endpoints
    if(is_user_logged_in()) {
        return $endpoints;
    }
    foreach($endpoints as $route => $endpoint) {
        if(stripos($route, '/wp/') === 0) {
            unset($endpoints[ $route ]);
        }
    }
    return $endpoints;
});

/**
 * Disable plugins auto-update email notifications
 */
add_filter( 'auto_plugin_update_send_email', function() {
    return false;
});

/**
 * Disable themes auto-update email notifications
 */
add_filter( 'auto_theme_update_send_email', function() {
    return false;
});

/**
 * Removes unnecesary information from <head> tag
 */
add_action('init', function() {
    // Remove post and comment feed link
    remove_action( 'wp_head', 'feed_links', 2 );

    // Remove post category links
	remove_action('wp_head', 'feed_links_extra', 3);

    // Remove link to the Really Simple Discovery service endpoint
	remove_action('wp_head', 'rsd_link');

    // Remove the link to the Windows Live Writer manifest file
	remove_action('wp_head', 'wlwmanifest_link');

    // Remove the XHTML generator that is generated on the wp_head hook, WP version
	remove_action('wp_head', 'wp_generator');

    // Remove start link
	remove_action('wp_head', 'start_post_rel_link');

    // Remove index link
	remove_action('wp_head', 'index_rel_link');

    // Remove previous link
	remove_action('wp_head', 'parent_post_rel_link', 10, 0);

    // Remove relational links for the posts adjacent to the current post
	remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

    // Remove relational links for the posts adjacent to the current post
    remove_action('wp_head', 'wp_oembed_add_discovery_links');

    // Remove REST API links
    remove_action('wp_head', 'rest_output_link_wp_head');

    // Remove Link header for REST API
    remove_action('template_redirect', 'rest_output_link_header', 11, 0 );

    // Remove Link header for shortlink
    remove_action('template_redirect', 'wp_shortlink_header', 11, 0 );

});

/**
 * List of feeds to disable
*/
$feeds = [
    'do_feed',
    'do_feed_rdf',
    'do_feed_rss',
    'do_feed_rss2',
    'do_feed_atom',
    'do_feed_rss2_comments',
    'do_feed_atom_comments',
];

foreach($feeds as $feed) {
    add_action($feed, function() {
        wp_die('Feed has been disabled.');
    }, 1);
}

/**
 * Remove wp-embed.js file from loading
 */
add_action( 'wp_footer', function() {
    wp_deregister_script('wp-embed');
});

/**
 * Enable WebP image type upload
 */
add_filter('mime_types', function($existing_mimes) {
    $existing_mimes['webp'] = 'image/webp';
    return $existing_mimes;
});

/**
 * Display WebP thumbnail
 */
add_filter('file_is_displayable_image', function($result, $path) {
    return ($result) ? $result : (empty(@getimagesize($path)) || !in_array(@getimagesize($path)[2], [IMAGETYPE_WEBP]));
}, 10, 2);

/* Custom Excerpt Length */
function wpfme_custom_excerpt_length( $length ) {
    //the amount of words to return
    return 25;
}
add_filter( 'excerpt_length', 'wpfme_custom_excerpt_length');

/* Year Shortcode  */
function year_shortcode() {
  $year = date('Y');
  return $year;
}
add_shortcode('year', 'year_shortcode');


/* Media Breakpoints */
/* Extra small devices (phones, 600px and down) */
@media only screen and (max-width: 600px) {...}

/* Small devices (portrait tablets and large phones, 600px and up) */
@media only screen and (min-width: 600px) {...}

/* Medium devices (landscape tablets, 768px and up) */
@media only screen and (min-width: 768px) {...}

/* Large devices (laptops/desktops, 992px and up) */
@media only screen and (min-width: 992px) {...}

/* Extra large devices (large laptops and desktops, 1200px and up) */
@media only screen and (min-width: 1200px) {...}

@media only screen and (orientation: landscape) {
body {
background-color: lightblue;
}
}

@media only screen and (max-width: 767px) and (orientation: portrait) {...}
@media only screen and (min-width: 320px) and (max-width: 479px){ ... }

/*  Change Readmore Text */
function sm_change_excerpt_more_text( $more ){
 global $post;
 return '&hellip; <a class="read-more" href="'.get_permalink($post->ID).'" title="'.esc_attr(get_the_title($post->ID)).'">'.'Read More &raquo;'.'</a>';
}
add_filter('excerpt_more', 'sm_change_excerpt_more_text');

/* Disable Image Compression */
add_filter('jpeg_quality', function($arg){return 100;});
add_filter('wp_editor_set_quality', function($arg){return 100;});

/* Get Latest Posts */
class Get_Latest_Post {
    public static function show() {
        $content = false;
        $query = new WP_Query( 'showposts=1' );
        while ( $query->have_posts() ) {
            $query->the_post();
            $content = "<p class='title'><a href='" . get_permalink() . "'>" . get_the_title() . "</a></p>\n<p class='excerpt'>" . get_the_excerpt() . '</p>';
        }
        wp_reset_postdata();

        if ( $content ) {
            return "<div class='latest-post'>$content</div>";
        }
    }
}

add_shortcode( 'get_latest_post', array( 'Get_Latest_Post', 'show' ) );

/* Get Latest Tweet */
class Get_Latest_Tweets {

    const CACHE_LIVE_TIME = 30; // 24 seconds == 150 (rate limit per hour) / 60 (minutes)

    private static $cache_path;

    public static function time_ago( $then ) {
        $diff = time() - strtotime( $then );

        $second = 1;
        $minute = $second * 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        $week = $day * 7;

        if ( is_nan( $diff ) || $diff < 0 ) {
            return ''; // Return blank string if unknown.
        }

        if ( $diff < $second * 2 ) {
            return 'right now';
        }

        if ( $diff < $minute ) {
            return floor( $diff / $second ) . ' seconds ago';
        }

        if ( $diff < $minute * 2 ) {
            return 'about 1 minute ago';
        }

        if ( $diff < $hour ) {
            return floor( $diff / $minute ) . ' minutes ago';
        }

        if ( $diff < $hour * 2 ) {
            return 'about 1 hour ago';
        }

        if ( $diff < $day ) {
            return  floor( $diff / $hour ) . ' hours ago';
        }

        if ( $diff > $day && $diff < $day * 2 ) {
            return 'yesterday';
        }

        if ( $diff < $day * 365 ) {
            return floor( $diff / $day ) . ' days ago';
        }

        return 'over a year ago';
    }

    public static function get_json_from_twitter( $username, $count ) {
        require 'tmhOAuth.php';

        $configFilePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'twitter.api.keys.json';
        if ( ! file_exists( $configFilePath ) ) {
            die( esc_html( "Could not find config file ($configFilePath)" ) );
        }

        $rawConfig = file_get_contents( $configFilePath );
        if ( ! $rawConfig ) {
            die( esc_html( "Could not read config file ($configFilePath)" ) );
        }

        $config = json_decode( $rawConfig );

        $tmhOAuth = new tmhOAuth(array(
            'consumer_key'    => $config->consumer_key,
            'consumer_secret' => $config->consumer_secret,
            'user_token'      => $config->user_token,
            'user_secret'     => $config->user_secret,
        ));

        $code = $tmhOAuth->request('GET', $tmhOAuth->url( '1.1/statuses/user_timeline' ), array(
            'screen_name' => $username,
        ));
        $json = $tmhOAuth->response['response'];

        if ( ! $json ) {
            die( 'Could not get JSON data from Twitter' );
        }

        return $json;
    }

    public static function cache_file_name( $username ) {
        return self::$cache_path . "/$username.json";
    }

    public static function cache_json( $username, $count ) {
        $cacheDirectory = dirname( self::$cache_path );

        if ( ! file_exists( $cacheDirectory ) ) {
            if ( ! mkdir( $cacheDirectory ) ) {
                die( 'Could not create cache directory. Make sure ' . esc_html( dirname( $cacheDirectory ) ) . ' is writable by the web server.' );
            }
        }

        if ( ! file_exists( self::$cache_path ) ) {
            if ( ! mkdir( self::$cache_path ) ) {
                die( 'Could not create cache directory. Make sure ' . esc_html( dirname( self::$cache_path ) ) . ' is writable by the web server.' );
            }
        }

        $json = self::get_json_from_twitter( $username, $count );
        return file_put_contents( self::cache_file_name( $username ), $json );
    }

    public static function read_cached_json( $username ) {
        return file_get_contents( self::cache_file_name( $username ) );
    }

    public static function get_json( $username, $count ) {
        $cacheFile = self::cache_file_name( $username );
        $staleCache = true;
        clearstatcache();
        if ( (file_exists( $cacheFile ) && filesize( $cacheFile )) ) {
            $cacheInfo = stat( $cacheFile );
            $modTime = $cacheInfo[9];

            if ( (time() - $modTime) < self::CACHE_LIVE_TIME ) {
                $staleCache = false;
            }
        }

        if ( $staleCache ) {
            if ( ! self::cache_json( $username, $count ) ) {
                die( 'Could not write to JSON cache. Make sure ' . esc_html( self::$cache_path ) . ' is writeable by the web server' );
            }
        }

        return self::read_cached_json( $username );
    }

    public static function format_tweet( $tweet ) {
        // Add @reply links.
        $tweet_text = preg_replace('/\B[@＠]([a-zA-Z0-9_]{1,20})/',
            "@<a class='atreply' href='http://twitter.com/$1'>$1</a>",
        $tweet);

        // Make other links clickable.
        $matches = array();
        $link_info = preg_match_all( "/\b(((https*\:\/\/)|www\.)[^\"\']+?)(([!?,.\)]+)?(\s|$))/", $tweet_text, $matches, PREG_SET_ORDER );

        if ( $link_info ) {
            foreach ( $matches as $match ) {
                $http = preg_match( '/w/', $match[2] ) ? 'http://' : '';
                $tweet_text = str_replace($match[0],
                    "<a href='" . $http . $match[1] . "'>" . $match[1] . '</a>' . $match[4],
                $tweet_text);
            }
        }

        return $tweet_text;
    }


    public static function show( $attributes ) {
        self::$cache_path = WP_CONTENT_DIR . '/cache/latest_tweets/';

        $attributes = shortcode_atts( array(
            'username' => null,
            'count' => 5,
        ), $attributes );

        $count = intval( $attributes['count'] );
        if ( $count < 1 or $count > 100 ) {
            return 'Numbers of tweets must be between 1 and 100.';
        }

        if ( ! $attributes['username'] ) {
            return 'Please specify a twitter username';
        }

        $json = self::get_json( $attributes['username'], $count );
        $tweetData = json_decode( $json, true );

        if ( isset( $tweetData['errors'] ) ) {
            return esc_html( 'Error: ' . $tweetData['errors'][0]['message'] . ' (' . $tweetData['errors'][0]['code'] . ')' );
        }

        $content = "<ul class='tweets'>\n";
        foreach ( $tweetData as $index => $tweet ) {
            if ( $index === $count ) { break; }
            $content .= '<li>' . self::format_tweet( $tweet['text'] ) . " <span class='date'><a href='http://twitter.com/" . $attributes['username'] . '/status/' . $tweet['id_str'] . "'>" . self::time_ago( $tweet['created_at'] ) . "</a></span></li>\n";
        }
        $content .= "</ul>\n";

        return $content;
    }
}

add_shortcode( 'get_latest_tweets', array( 'Get_Latest_Tweets', 'show' ) );


/**
 * Create shortcode to get WordPress menu in HTML format.
 * How to use? [sm_wp_menu name="Main Menu"]
 *
 * @param array $atts Array of attributes.
 *
 * @return string HTML menu.
 */
function sm_wp_menu_shortcode( $atts ) {
    $menu = shortcode_atts( array(
        'name' => null,
    ), $atts );

    return wp_nav_menu( array(
        'menu' => $menu['name'],
        'echo' => false,
    ) );
}
add_shortcode( 'sm_wp_menu', 'sm_wp_menu_shortcode' );

<?php 

/* Redirect users to the checkout page when they add a product to cart on the single product page */

add_filter( 'woocommerce_add_to_cart_redirect', 'sm_add_to_cart_checkout_redirect' );

function sm_add_to_cart_checkout_redirect() {
   return wc_get_checkout_url();
}

/* Customise the price range display of variable products with text */ 

add_filter( 'woocommerce_get_price_html', 'sm_change_variable_price_range_display', 10, 2 );

function sm_change_variable_price_range_display( $price, $product ) {
        if( ! $product->is_type('variable') ) return $price;
        $prices = $product->get_variation_prices( true );

        if ( empty( $prices['price'] ) )
        return apply_filters( 'woocommerce_variable_empty_price_html', '', $product );

        $min_price = current( $prices['price'] );
        $max_price = end( $prices['price'] );

        return apply_filters( 'woocommerce_variable_price_html', 'From ' . wc_price( $min_price ) . $product->get_price_suffix() . " to " . $max_price, $product );
}
?>