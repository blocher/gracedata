<?php namespace App\Scrapers;

use Sunra\PhpSimple\HtmlDomParser;
use Carbon\Carbon;
use Storage;
use Smalot\PdfParser\Parser as PdfParser;



class SermonScraper extends \App\Scrapers\Scraper {

  public $url = 'http://www.gracealex.org/Worship/Sermons/';

  function __construct() {
    //$sermons = $this->getSermonList();
  }


  public function scrape() {
    $i = 0;

    $response = $this->get($this->url);
    $html  = HtmlDomParser::str_get_html($response);
    $sermons = $html->find('#content ul a');

    $results = [];

    foreach($sermons as $sermon) {

      $result = [];
      $result['link'] = 'http://www.gracealex.org' . $sermon->href;
      $result['filename'] = basename($result['link']);


      $result['title'] = $sermon->plaintext;

      $parent =  $sermon->parent();
      $plaintext = $parent->plaintext;

      $plaintext = explode('-', $plaintext);

      $result['preacher'] = $this->clean($plaintext[1]);

      $plaintext = $plaintext[0];

      $plaintext = explode(',',$plaintext);
      array_pop($plaintext);

      $occasion = array_pop($plaintext);
      $result['occasion'] = $this->clean($occasion);

      $date_time = implode(',',$plaintext);

      $spanish = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre',  'Noviembre','Diciembre', 'Agusto'];
      $english   = ["January","February","March","April","May","June","July","August","September","October","November","December", "August"];

      $date_time = str_ireplace($spanish, $english, $date_time);

      if (strpos($date_time,' pm')===false) {
        $date_time = $date_time . ' 9 am';
      }

      $result['day'] = new Carbon($date_time, 'America/New_York');


      $contents = file_get_contents($result['link']);
      Storage::disk('local')->put('sermons/' . $result['filename'], $contents);

      $result['text'] = '';
      $result['localfile'] = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'];

      try {
        // Parse pdf file and build necessary objects.
        $parser = new PdfParser();
        $pdf    = $parser->parseContent($contents);
        $result['text'] = $pdf->getText();
     } catch (\Exception $e) {
        echo "ERROR getting PDF: " . $e->getMessage() . PHP_EOL;
        try {
          $fileName = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'];
          $phpWord = \PhpOffice\PhpWord\IOFactory::load($fileName);
          $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
          $objWriter->save(Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'] . '.html');
          $result['text'] = file_get_contents(Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'] . '.html');
        } catch (\Exception $e) {
          echo "ERROR getting Word: " . $e->getMessage() . PHP_EOL;
        }

      }
      $result['text'] = html_entity_decode($result['text']);

      echo PHP_EOL . $result['title'] . PHP_EOL;
      $i++;
      echo $i . PHP_EOL;
      $results[] = $result;
      // unset($result['text']);
      // var_dump($result);
    }


    echo PHP_EOL . 'TOTAL: ' . $i;
    Storage::disk('local')->put('sermons.phpserial', serialize($results));
    return $results;
  }

  public function clean($text) {
    $text = str_replace('&nbsp;', '', $text);
    $text = html_entity_decode($text);
    $text = trim($text," ,-—\t\n\r\0\x0B");
    return $text;
  }

  /* if resume functionality is available, overwrite in child class */
  public function resume() {
    echo  'No resume functionality is supported for this scraper. Starting from beginning.' . "\n";
    $this->scrape();
  }


  public function save() {

  }



}