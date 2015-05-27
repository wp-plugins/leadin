<?php

require(dirname(__FILE__) . '/Consumer.php');
require(dirname(__FILE__) . '/QueueConsumer.php');
require(dirname(__FILE__) . '/Consumer/File.php');
require(dirname(__FILE__) . '/Consumer/ForkCurl.php');
require(dirname(__FILE__) . '/Consumer/Socket.php');

class Segment_Client {

  /**
   * VERSION
   */

  const VERSION = "1.1.0";

  private $consumer;

  /**
   * Create a new analytics object with your app's secret
   * key
   *
   * @param string $secret
   * @param array  $options array of consumer options [optional]
   * @param string Consumer constructor to use, socket by default.
   */
  public function __construct($secret, $options = array()) {

    $consumers = array(
      "socket"     => "Segment_Consumer_Socket",
      "file"       => "Segment_Consumer_File",
      "fork_curl"  => "Segment_Consumer_ForkCurl"
    );

    # Use our socket consumer by default
    $consumer_type = isset($options["consumer"]) ? $options["consumer"] :
                                                   "socket";
    $Consumer = $consumers[$consumer_type];

    $this->consumer = new $Consumer($secret, $options);
  }

  public function __destruct() {
    $this->consumer->__destruct();
  }

  /**
   * Tracks a user action
   *
   * @param  array $message
   * @return [boolean] whether the track call succeeded
   */
  public function track(array $message) {
    $message = $this->message($message, "properties");
    $message["type"] = "track";
    return $this->consumer->track($message);
  }

  /**
   * Tags traits about the user.
   *
   * @param  [array] $message
   * @return [boolean] whether the track call succeeded
   */
  public function identify(array $message) {
    $message = $this->message($message, "traits");
    $message["type"] = "identify";
    return $this->consumer->identify($message);
  }

  /**
   * Tags traits about the group.
   *
   * @param  [array] $message
   * @return [boolean] whether the group call succeeded
   */
  public function group(array $message) {
    $message = $this->message($message, "traits");
    $message["type"] = "group";
    return $this->consumer->group($message);
  }

  /**
   * Tracks a page view.
   *
   * @param  [array] $message
   * @return [boolean] whether the page call succeeded
   */
  public function page(array $message) {
    $message = $this->message($message, "properties");
    $message["type"] = "page";
    return $this->consumer->page($message);
  }

  /**
   * Tracks a screen view.
   *
   * @param  [array] $message
   * @return [boolean] whether the screen call succeeded
   */
  public function screen(array $message) {
    $message = $this->message($message, "properties");
    $message["type"] = "screen";
    return $this->consumer->screen($message);
  }

  /**
   * Aliases from one user id to another
   *
   * @param  array $message
   * @return boolean whether the alias call succeeded
   */
  public function alias(array $message) {
    $message = $this->message($message);
    $message["type"] = "alias";
    return $this->consumer->alias($message);
  }

  /**
   * Flush any async consumers
   */
  public function flush() {
    if (!method_exists($this->consumer, 'flush')) return;
    $this->consumer->flush();
  }

  /**
   * Formats a timestamp by making sure it is set
   * and converting it to iso8601.
   *
   * The timestamp can be time in seconds `time()` or `microseconds(true)`.
   * any other input is considered an error and the method will return a new date.
   *
   * Note: php's date() "u" format (for microseconds) has a bug in it
   * it always shows `.000` for microseconds since `date()` only accepts
   * ints, so we have to construct the date ourselves if microtime is passed.
   * 
   * @param  time $timestamp - time in seconds (time())
   */
  private function formatTime($ts) {
    if ($ts == null) $ts = time();
    if ("integer" == gettype($ts)) return date("c", $ts);
    if (-1 == ($pos = strrpos($ts, "."))) return date("c");
    $sec = substr($ts, 0, $pos);
    $usec = substr($ts, $pos);
    $fmt = sprintf("Y-m-d\TH:i:s%sP", $usec);
    return date($fmt, (int)$sec);
  }

  /**
   * Add common fields to the gvien `message`
   *
   * @param array $msg
   * @param string $def
   * @return array
   */

  private function message($msg, $def = ""){
    if ($def && !isset($msg[$def])) $msg[$def] = array();
    if ($def && empty($msg[$def])) $msg[$def] = (object)$msg[$def];
    if (!isset($msg["context"])) $msg["context"] = array();
    if (!isset($msg["timestamp"])) $msg["timestamp"] = null;
    $msg["context"] = array_merge($msg["context"], $this->getContext());
    $msg["timestamp"] = $this->formatTime($msg["timestamp"]);
    $msg["messageId"] = self::messageId();
    return $msg;
  }

  /**
   * Generate a random messageId.
   *
   * https://gist.github.com/dahnielson/508447#file-uuid-php-L74
   *
   * @return string
   */

  private static function messageId(){
    return sprintf("%04x%04x-%04x-%04x-%04x-%04x%04x%04x"
      , mt_rand(0, 0xffff)
      , mt_rand(0, 0xffff)
      , mt_rand(0, 0xffff)
      , mt_rand(0, 0x0fff) | 0x4000
      , mt_rand(0, 0x3fff) | 0x8000
      , mt_rand(0, 0xffff)
      , mt_rand(0, 0xffff)
      , mt_rand(0, 0xffff));
  }

  /**
   * Add the segment.io context to the request
   * @return array additional context
   */
  private function getContext () {
    return array(
      "library" => array(
        "name" => "analytics-php",
        "version" => self::VERSION
      )
    );
  }
}
