<?php
if ( !defined('LEADIN_PLUGIN_VERSION') )
{
  header( 'HTTP/1.0 403 Forbidden' );
  wp_die();
}

if ( is_admin() ) {
  add_action('wp_ajax_leadin_admin_ajax', 'leadin_admin_ajax'); // Call when user logged in
}

function leadin_admin_ajax() {
  $baseUrl = constant('LEADIN_API_BASE_URL');
  $_REQUEST['csurl'] = $baseUrl.$_GET['path'];
  leadin_proxy_ajax();
  wp_die();
}

function leadin_proxy_ajax ()
{
  /**
   * AJAX Cross Domain (PHP) Proxy 0.8
   *    by Iacovos Constantinou (http://www.iacons.net)
   * 
   * Released under CC-GNU GPL
   */

  /**
   * Enables or disables filtering for cross domain requests.
   * Recommended value: true
   */
  define( 'CSAJAX_FILTERS', true );

  /**
   * If set to true, $valid_requests should hold only domains i.e. a.example.com, b.example.com, usethisdomain.com
   * If set to false, $valid_requests should hold the whole URL ( without the parameters ) i.e. http://example.com/this/is/long/url/
   * Recommended value: false (for security reasons - do not forget that anyone can access your proxy)
   */
  define( 'CSAJAX_FILTER_DOMAIN', true );

  /**
   * Set debugging to true to receive additional messages - really helpful on development
   */
  define( 'CSAJAX_DEBUG', false );

  /**
   * A set of valid cross domain requests
   */
  $valid_requests = array(
    'api.leadin.com',
    'api.leadinqa.com',
    'local.leadinqa.com'
  );

  /* * * STOP EDITING HERE UNLESS YOU KNOW WHAT YOU ARE DOING * * */

  // identify request headers
  $request_headers = array( );
  foreach ( $_SERVER as $key => $value ) {
    if ( substr( $key, 0, 5 ) == 'HTTP_' ) {
      $headername = str_replace( '_', ' ', substr( $key, 5 ) );
      $headername = str_replace( ' ', '-', ucwords( strtolower( $headername ) ) );
      if ( !in_array( $headername, array( 'Host', 'X-Proxy-Url' ) ) ) {
        $request_headers[] = "$headername: $value";
      }
    } else if ($key == 'CONTENT_TYPE') {
      $request_headers[] = "Content-Type: $value";
    }
  }

  // identify request method, url and params
  $request_method = $_SERVER['REQUEST_METHOD'];
  if ( 'GET' == $request_method ) {
    $request_params = $_GET;
  } elseif ( 'POST' == $request_method ) {
    $request_params = $_POST;
    if ( empty( $request_params ) ) {
      $data = file_get_contents( 'php://input' );
      if ( !empty( $data ) ) {
        $request_params = $data;
      }
    }
  } elseif ( 'PUT' == $request_method || 'DELETE' == $request_method || 'PATCH' == $request_method) {
    $request_params = file_get_contents( 'php://input' );
  } else {
    $request_params = null;
  }

  // Get URL from `csurl` in GET or POST data, before falling back to X-Proxy-URL header.
  if ( isset( $_REQUEST['csurl'] ) ) {
      $request_url = urldecode( $_REQUEST['csurl'] );
  } else if ( isset( $_SERVER['HTTP_X_PROXY_URL'] ) ) {
      $request_url = urldecode( $_SERVER['HTTP_X_PROXY_URL'] );
  } else {
      header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
      header( 'Status: 404 Not Found' );
      $_SERVER['REDIRECT_STATUS'] = 404;
      exit;
  }

  $p_request_url = parse_url( $request_url );

  // csurl may exist in GET request methods
  if ( is_array( $request_params ) && array_key_exists('csurl', $request_params ) )
    unset( $request_params['csurl'] );

  // ignore requests for proxy :)
  if ( preg_match( '!' . $_SERVER['SCRIPT_NAME'] . '!', $request_url ) || empty( $request_url ) || count( $p_request_url ) == 1 ) {
    csajax_debug_message( 'Invalid request - make sure that csurl variable is not empty' );
    exit;
  }

  // check against valid requests
  if ( CSAJAX_FILTERS ) {
    $parsed = $p_request_url;
    if ( CSAJAX_FILTER_DOMAIN ) {
      if ( !in_array( $parsed['host'], $valid_requests ) ) {
        csajax_debug_message( 'Invalid domain - ' . $parsed['host'] . ' does not included in valid requests' );
        exit;
      }
    } else {
      $check_url = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
      $check_url .= isset( $parsed['user'] ) ? $parsed['user'] . ($parsed['pass'] ? ':' . $parsed['pass'] : '') . '@' : '';
      $check_url .= isset( $parsed['host'] ) ? $parsed['host'] : '';
      $check_url .= isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
      $check_url .= isset( $parsed['path'] ) ? $parsed['path'] : '';
      if ( !in_array( $check_url, $valid_requests ) ) {
        csajax_debug_message( 'Invalid domain - ' . $request_url . ' does not included in valid requests' );
        exit;
      }
    }
  }

  // append query string for GET requests
  if ( $request_method == 'GET' && count( $request_params ) > 0 && (!array_key_exists( 'query', $p_request_url ) || empty( $p_request_url['query'] ) ) ) {
    $request_url .= '?' . http_build_query( $request_params );
  }

  // let the request begin
  $ch = curl_init( $request_url );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, $request_headers );   // (re-)send headers
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );  // return response
  curl_setopt( $ch, CURLOPT_HEADER, true );    // enabled response headers
  // add data for POST, PUT or DELETE requests
  if ( 'POST' == $request_method ) {
    $post_data = is_array( $request_params ) ? http_build_query( $request_params ) : $request_params;
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS,  $post_data );
  } elseif ( 'PUT' == $request_method || 'DELETE' == $request_method || 'PATCH' == $request_method) {
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $request_method );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $request_params );
  }

  // retrieve response (headers and content)
  $response = curl_exec( $ch );

  if ( curl_error( $ch ) ) {
    $error = curl_error ( $ch );
    $errorNoNewlines = trim(preg_replace('/\s+/', ' ', $error));
    header( 'HTTP/1.0 500 Internal Server Error' );
    wp_die( '{"error": "Bad cURL response: '. $errorNoNewlines .'"}' );
  }

  curl_close( $ch );

  // split response to header and content
  list($response_headers, $response_content) = array_pad(preg_split( '/(\r\n){2}/', $response, 2 ), 2, '');

  // (re-)send the headers
  $response_headers = preg_split( '/(\r\n){1}/', $response_headers );
  foreach ( $response_headers as $key => $response_header ) {
    // Rewrite the `Location` header, so clients will also use the proxy for redirects.
    if ( preg_match( '/^Location:/', $response_header ) ) {
      list($header, $value) = preg_split( '/: /', $response_header, 2 );
      $response_header = 'Location: ' . $_SERVER['REQUEST_URI'] . '?csurl=' . $value;
    }
    if ( !preg_match( '/^([T|t]ransfer-[E|e]ncoding):/', $response_header ) ) {
      header( $response_header, false );
    }
  }

  // finally, output the content
  print( $response_content );
}

function csajax_debug_message( $message )
{
  if ( true == CSAJAX_DEBUG ) {
    print $message . PHP_EOL;
  }
}

?>