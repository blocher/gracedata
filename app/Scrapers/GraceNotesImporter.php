<?php namespace App\Scrapers;

use Carbon\Carbon;
use Storage;

class GraceNotesImporter {

    public $categories = [
      "119"=>'bulletin_insert',
      "96"=>'grace_notes',
      "95"=>'other_publication',
    ];

function __construct() {
  $this->setup_wordpress();
}

function setup_wordpress() {
  $_SERVER['SERVER_NAME'] = 'localhost';
  date_default_timezone_set('America/New_York');
  require_once( env('WORDPRESS_PATH') );
  require_once(ABSPATH . 'wp-admin/includes/media.php');
  require_once(ABSPATH . 'wp-admin/includes/file.php');
  require_once ( ABSPATH . 'wp-admin/includes/image.php' );
}

function import() {

  $import_file = Storage::disk('local')->get('news.phpserial');
  $import_file = unserialize($import_file);

  $i=0;
  foreach ($import_file as $record) {

    if (empty($record['localfile'])) {
      continue;
    }
    $i++;
    $args = [
       'meta_query' => [
           [
               'key' => 'old_site_url',
               'value' => 'http://gracealex.org' . $record['link'],
               'compare' => '=',
           ]
       ],
       'post_type' => 'any',
       'posts_per_page' => 1,
    ];
    $old_posts = get_posts($args);
    // echo PHP_EOL . PHP_EOL .  'http://gracealex.org' . $record['link'] . PHP_EOL . PHP_EOL ;
    // var_dump($old_posts);
    $cat_id = $record['category_id'];
    $post_type = $this->categories[$cat_id];
    $post = array(
        'post_content' => iconv('ISO-8859-1','UTF-8', $record['text']),
        'post_title' => $record['title'],
        'post_status' => 'publish',
        'post_type' => $post_type,
        'post_date' => $record['published_date']->format("Y-m-d H:i:s"),
    );

    if (count($old_posts)>0) {
      $post['ID'] = $old_posts[0]->ID;
      $old_pdf = get_field('pdf', $post['ID'], false);
      if (!empty($old_pdf)) {
        wp_delete_attachment( $old_pdf );
      }

    }

    $id = wp_insert_post($post, true);

    if (is_wp_error($id)){
      dd($id);
    }

    if (!empty($record['date'])) {
      update_field( 'date', $record['published_date']->format("Y-m-d H:i:s"), $id );
    }

    if (!empty($record['published_date'])) {
      update_field( 'published_date', $record['published_date']->format("Y-m-d H:i:s"), $id );
    }

    if (!empty($record['month_description'])) {
      update_field( 'month_description', $record['month_description'], $id );
    }

    if (!empty($record['date_string'])) {
      update_field( 'date_string', $record['date_string'], $id );
    }

    // if (!empty($record['month'])) {
    //   update_field( 'month', $record['month'], $id );
    // }


    $attachment_title = $record['title'];
    $attachment_date =  $record['uploaded']->format("Y-m-d H:i:s");

    $this->attach_featured_image($record['localfile'],$id,$attachment_title, $attachment_date,'media_category',[4]);
    update_field( 'pdf_content', iconv('ISO-8859-1','UTF-8', $record['text']), $id );
    update_field( 'old_site_url', 'http://gracealex.org' . $record['link'], $id );

    echo $id . ' ' . $record['title'] . PHP_EOL;


  }

}


function attach_featured_image($full_url, $parent_post_id=0,$title='',$date='', $tax='', $terms=[]) {

  $filename = basename ( $full_url );
  try {
    $file_contents = file_get_contents($full_url);
  } catch (ErrorException $e) {
    echo 'ERROR:' . 'Attached ' . $full_url . ' to post: ' . $parent_post_id . '; ' . $full_url . ' does not exist.' . $e->getMessage() . PHP_EOL;
    return;
  }

  $upload_file = wp_upload_bits($filename, null, $file_contents);
  if (!$upload_file['error']) {
    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => empty($title) ? $filename : $title,
      'post_content' => '',
      'post_status' => 'inherit',
      'post_date' => empty($date) ? date("Y-m-d H:i:s") : $date,
    );
    $attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );
    if (!is_wp_error($attachment_id)) {
      $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
      wp_update_attachment_metadata( $attachment_id,  $attachment_data );
      if ($parent_post_id != 0) {
        //set_post_thumbnail($parent_post_id, $attachment_id);
        update_field('pdf', $attachment_id, $parent_post_id);
      }

      if (!empty($tax) && !empty($terms)) {
        wp_set_post_terms( $attachment_id, $terms, $tax, true );
      }


    } else {
      echo 'ERROR:' . 'Attached ' . $full_url . ' to post: ' . $parent_post_id . '; ' . $attachment_id->get_error_message() . PHP_EOL;
      return;
    }

    echo 'Attached ' . $full_url . ' to post: ' . $parent_post_id . ' with attachment id of ' . $attachment_id . PHP_EOL;

  }
}


}