<?php namespace App\Scrapers;

use Carbon\Carbon;
use Storage;

class SermonImporter {

function __construct() {
  $this->setup_wordpress();
}





function setup_wordpress() {
  date_default_timezone_set('America/New_York');
  $_SERVER['SERVER_NAME'] = 'IMPORTER';
  require_once( env('WORDPRESS_PATH') );
  require_once(ABSPATH . 'wp-admin/includes/media.php');
  require_once(ABSPATH . 'wp-admin/includes/file.php');
  require_once ( ABSPATH . 'wp-admin/includes/image.php' );
}

function import() {
  $import_file = Storage::disk('local')->get('sermons.phpserial');
  $sermons = unserialize($import_file);

  echo PHP_EOL . 'STARTING' . PHP_EOL;
  $i=0;
  foreach ($sermons as $sermon) {

    $i++;
    $args = [
       'meta_query' => [
           [
               'key' => 'old_site_url',
               'value' => $sermon['link'],
               'compare' => '=',
           ]
       ],
       'post_type' => 'sermon',
       'posts_per_page' => 1,
    ];
    $old_posts = get_posts($args);

    $post = array(
        'post_content' => $sermon['text'],
        'post_title' => $sermon['title'],
        'post_status' => 'publish',
        'post_type' => 'sermon',
        'post_date' => $sermon['day']->format("Y-m-d H:i:s"),
    );
    echo $sermon['day']->format("Y-m-d H:i:s") . PHP_EOL;
    if (count($old_posts)>0) {
      $post['ID'] = $old_posts[0]->ID;
      $old_pdf = get_field('pdf', $post['ID'], false);
      if (!empty($old_pdf)) {
        wp_delete_attachment( $old_pdf );
      }

    }

    $id = wp_insert_post($post);

    update_field( 'sermon_title', $sermon['title'], $id );
    update_field( 'date_given', $sermon['day']->format("Y-m-d H:i:s"), $id );
    $time = $sermon['day']->format("H:i");
    if ($time!='09:00') {
      update_field( 'time_given', $time, $id );
    }

    update_field( 'occasion', $sermon['occasion'], $id );
    update_field( 'preacher', $sermon['preacher'], $id );

    $attachment_title = $sermon['day']->format("Y-m-d") . ' - ' . $sermon['title'] . ' - ' . $sermon['preacher'];
    $attachment_date =  $sermon['day']->format("Y-m-d H:i:s");

    $this->attach_featured_image($sermon['localfile'],$id,$attachment_title, $attachment_date,'media_category',[4]);
    update_field( 'sermon_content', $sermon['text'], $id );
    update_field( 'old_site_url', $sermon['link'], $id );


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
        update_field('field_57f30e9b409dd', $attachment_id, $parent_post_id);
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