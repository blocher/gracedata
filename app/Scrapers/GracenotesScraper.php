<?php namespace App\Scrapers;

use Sunra\PhpSimple\HtmlDomParser;
use Carbon\Carbon;
use Storage;
use Smalot\PdfParser\Parser as PdfParser;



class GracenotesScraper extends \App\Scrapers\Scraper {

  public $url = 'http://www.gracealex.org/News/CategoryView.asp?CategoryID=';
  public $categories = [
    "119"=>'Sunday Bulletins',
    "96"=>'Grace Notes',
    "95"=>'Special Announcements',


  ];
  function __construct() {
    //$sermons = $this->getSermonList();
  }


  public function scrape() {

    $results = [];
    foreach ($this->categories as $category=>$category_name) {
      $base_url = $this->url . $category;
      $response = $this->get($base_url);
      $html  = HtmlDomParser::str_get_html($response);
      $last = $html->find('a[title=Last]',0);
      $last = $last->href;
      $last = explode('=',$last);
      $last = array_pop($last);


      for ($i=0; $i<=$last; $i++) {

        echo '======' . $base_url . '&page=' . $i . '======' . PHP_EOL;

        $response = $this->get($base_url . '&page=' . $i);
        $html  = HtmlDomParser::str_get_html($response);
        $items = $html->find('table[class="text"]');

        foreach ($items as $item) {

          $result = [];
          $result['category_id'] = $category;
          $result['category'] = $category_name;
          $img = $item->find('img',0);
          if ($img->src != '/images/icons/16x16/mime-pdf.gif') {
            continue;
          }
          $a = $item->find('a',0);

          $title = $a->plaintext;
          $result['title'] = $title;

          $link = $a->href;
          $result['link'] = $link;

          $uploaded = $item->find('i',0)->plaintext;
          $uploaded = new Carbon($uploaded, new \DateTimeZone('America/New_York'));
          $result['uploaded'] = $uploaded;

          $month = $date = '';
          if ($category == 96) {
            $month = str_ireplace('Grace Notes', '', $title);
            $month = trim ($month,',');
            $month = trim($month);
            $result['month'] = $month;
          }

          if ($category == 119) {
            $date = str_ireplace('Bulletin Insert for ', '', $title);
            $date = str_ireplace('Bulletin Insert ', '', $date);
            $result['date_string'] = $date;
            try {
              $date = new Carbon($date, new \DateTimeZone('America/New_York'));
            } catch (\Exception $e) {
              $date = $uploaded;
            }
            $result['date'] = $date;


          }


          $filename = basename($link);
          $result['filename'] = $filename;

          try {
            $contents = file_get_contents('http://gracealex.org' . $link);
            Storage::disk('local')->put(str_slug($category_name) . '/' . $filename, $contents);

            $result['text'] = '';
            $result['localfile'] = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . str_slug($category_name) . '/' . $filename;

            $parser = new PdfParser();
            $pdf    = $parser->parseContent($contents);
            $text = $pdf->getText();
            $result['text'] = $text;
          } catch (\Exception $e) {
            $result['localfile'] = '';
            $result['text'] = '';
          }


          echo $title . ' ' . $link . ' ' . $uploaded .' ' . $month  . $date . PHP_EOL;
          $results[] = $result;

        }

      }
    }
    $results = serialize($results);

    Storage::disk('local')->put('news.phpserial', $results);


  }

  /* if resume functionality is available, overwrite in child class */
  public function resume() {
    echo  'No resume functionality is supported for this scraper. Starting from beginning.' . "\n";
    $this->scrape();
  }


  public function save() {

  }



}