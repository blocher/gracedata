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
    $i = $j = 0;
    $response = $this->get($this->url);
    $html  = HtmlDomParser::str_get_html($response);
    $sermons = $html->find('#content ul a');
    $results = [];
    $current_date = new Carbon();
    $year = $current_date->format('Y');
    foreach($sermons as $sermon) {
      $a = $sermon->href;
      $title = $sermon->plaintext;
      $parent =  $sermon->parent();
      if ($parent->tag == 'span') {
        $parent = $parent->parent();
        if ($parent->tag=='span') {
          $parent=$parent->parent();
          if ($parent->tag=='span') {
            $parent=$parent->parent();
          }
        }
      }
      $plaintext = $parent->plaintext;
      $pos = strrpos($plaintext, $title);
      if ($pos !== false) {
          $plaintext = substr_replace($plaintext, '$$$$$', $pos, strlen($title));
      }
      $plaintext = explode('$$$$$',$plaintext);

      $result = [];
      $day = explode(',',$this->clean($plaintext[0]));
      if (trim(end($day))=='"') {
        array_pop($day);
      }


      $time = '9 a.m.';
      if (strpos(trim(end($day)),'pm') !== false) {
        $time = array_pop($day);
      }

      $day = array_map([$this,'clean'], $day);
      $result['occasion'] = array_pop($day);

      $day[0] = $day[0] . ',';
      if (count($day) == 1) {
        $day[] = $year;
      } else {
        $year = end($day);
      }

      $day = implode(' ',$day);
      $time = $this->clean($time);
      $day .= ' ' . $time;

      $spanish = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre',  'Noviembre','Diciembre', 'Agusto'];
      $english   = ["January","February","March","April","May","June","July","August","September","October","November","December", "August"];

      $day = str_ireplace($spanish, $english, $day);
      $day = str_replace('2103', '2013', $day);

      $result['day'] = new Carbon($day, 'America/New_York');
      $result['title'] = $this->clean($title);
      $result['preacher'] = $this->clean($plaintext[1]);
      $result['link'] = 'http://www.gracealex.org' . $a;
      $result['filename'] = basename($result['link']);


      if (empty($result['preacher'])) {
        $i++;
      }
      $j++;

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
        $fileName = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'];
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($fileName);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save(Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'] . '.html');
        $result['text'] = file_get_contents(Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . 'sermons/' . $result['filename'] . '.html');
      }
      $result['text'] = html_entity_decode($result['text']);
      // echo $text;
      //var_dump($result);
      echo $result['title'] . PHP_EOL;

      $results[] = $result;
    }

    echo PHP_EOL . 'TOTAL: ' . $j;
    echo PHP_EOL . 'MISSING PREACHER: ' . $i . PHP_EOL;
    return $results;
  }

  public function clean($text) {
    $text = str_replace('&nbsp;', '', $text);
    $text = html_entity_decode($text);
    $text = trim($text," ,-—\t\n\r\0\x0B");
    $text = str_replace('June 5.', 'June 5,', $text);
    //$text = str_replace('pm', ' p.m.', $text);
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