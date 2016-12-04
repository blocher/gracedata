<?php namespace App\Scrapers;

use Sunra\PhpSimple\HtmlDomParser;

abstract class Scraper {

  /**
  * Send a POST requst using cURL
  * @param string $url to request
  * @param array $post values to send
  * @param array $options for cURL
  * @return string
  */
  protected function post($url, $fields = [], $options = [])
  {

    $this->log($url, 'POST');

    $defaults = [
      CURLOPT_POST => 1,
      CURLOPT_HEADER => 0,
      CURLOPT_URL => $url,
      CURLOPT_FRESH_CONNECT => 1,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_FORBID_REUSE => 1,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_POSTFIELDS => http_build_query($fields),
      CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest']
    ];

    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));

    $result = $this->runCurl($ch);

    curl_close($ch);
    sleep(1);

    return $result;

  }

  /**
  * Send a GET requst using cURL
  * @param string $url to request
  * @param array $get values to send
  * @param array $options for cURL
  * @return string
  */
  protected function get($url, $fields = [], $options = [])
  {
    $hash = md5($url . serialize($fields) . serialize($options));

    $this->log($url, 'GET');

    $defaults = [
      CURLOPT_URL => $url . (strpos($url, '?') === FALSE ? '?' : '') . http_build_query($fields),
      CURLOPT_HEADER => 0,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));

    $result = $this->runCurl($ch);

    curl_close($ch);
    sleep(1);

    return $result;

  }

  private function runCurl($command, $retry = 3) {

    $result = curl_exec($command);

    while (!$result && $retry !== 0) {
      // No valid response, sleep 10 min and retry.
      $this->log("No valid response, sleep 10 min and retry.");
      sleep(60*10);
      $result = curl_exec($command);
      $retry--;
    }

    if (!$result) {
      throw new \Exception('Failed after ' . static::$requestsCount . ' on ' . $url . PHP_EOL . print_r($fields, true) . PHP_EOL . curl_error($ch));
    }
    else {
      return $result;
    }
  }

  protected function log($text, $subject = null) {
    if ($subject) {
      echo chr(27) . '[32m' . $subject . chr(27) . '[0m: ' . $text;
    } else {
      echo $text;
    }

    echo PHP_EOL;
  }



}