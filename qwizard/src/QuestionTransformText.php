<?php

namespace Drupal\qwizard;

use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileSystemInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwrest\QwRestGeneral;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Class QwizardGeneral.
*/
class QuestionTransformText {
  protected $merck_prod = 'https://www.msdvetmanual.com';
  protected $merck_backup_enabled = null;
  public $reference_removal_enabled = null;
  public $is_special_char_handling_enabled = null;
  protected $merck_config_setting = null;
  protected $base_url = null;


  public function __construct(){
    $this->base_url = \Drupal::request()->getSchemeAndHttpHost();

  }

  public function create(ContainerInterface $container)
  {
    return new static(
    );
  }

  /**
   * Given an HTML string will parse it and return a modified string.
   *
   * @param $str
   * @param $node
   * @param $field_name
   *
   * @return array|false|mixed|mixed[]|string|string[]|null
   */
  function transformQuizText($str, $node, $field_name = ''){
    $this->setupConfig();

    $str = $this->transformRootRelativeUrlsToAbsolute($str);
    $str = $this->transformMerckLinks($str, $this->isMerckBackupEnabled());
    $str = _enforceBrandText($str);

    if($this->reference_removal_enabled) {
      $str = $this->removeQuestionReferences($str, $node);
    }

    $image_rewriting = true;
    if($image_rewriting){
      $str = $this->handleImages($str, $node);
    }

    $table_rewriting = true;
    if($table_rewriting){
      $str = $this->handleTables($str, $node);
    }

    $french_rewriting = true;
    if($french_rewriting){
      $str = $this->handleTranslationReplacement($str, $node);
    }

    // Enforce HTTPS
    $str = $this->enforceHTTPS($str);

    // allow stringoverride module to hook in here
    $str = $this->stringOverrides($str, $node, $field_name);


    // Keep this at the end
    if($this->is_special_char_handling_enabled){
      $str = $this->handleSpecialChars($str, $node);
    }

    return $str;
  }

  public function enforceHTTPS($html){
    $search_strings = ['http://zukureview.com' => 'https://zukureview.com'];

    foreach($search_strings as $search_string=>$string_replace) {
      if (str_contains($html, $search_string)) {
        $html = str_replace($search_string, $string_replace, $html);
      }
    }

    return $html;
  }

  public function stringOverrides($str, $node_or_language, $field_name = '', $context = 'q'){
    if(is_object($node_or_language)) {
      $current_lang = $node_or_language->get('langcode')->value;
    }else{
      $current_lang = $node_or_language;
    }
    $string_replacements = $this->getStringOverrides($current_lang, $field_name, $context);
    if(!empty($string_replacements) && is_array($string_replacements)) {
      foreach($string_replacements as $context) {
        foreach ($context as $from => $to) {
          if (str_contains($str, $from)) {
            $str = str_replace($from, $to, $str);
          }
        }
      }
    }

    return $str;
  }

  /**
   * Copied from StringOverridesTranslation->getStringOverrides
   * Uses string_translation configuration to get an array of from/tos by language
   * @param $langcode
   * @return false|mixed
   */
  public function getStringOverrides($langcode, $field_name = '', $context_to_use = 'q') {
    $field_name = str_replace('field_', '', $field_name);
    $cid = 'stringoverides:translation_for_' . $langcode.'_'.$field_name;

    $cache_backend = \Drupal::cache();
    if ($cache = $cache_backend->get($cid)) {
      return $cache->data;
    }
    else {
      $translations = [];
      // Drupal's configuration array structure is different from translations
      // array structure, lets transform configuration array.
      $config = \Drupal::config('stringoverrides.string_override.' . $langcode);
      $contexts = $config->get('contexts');
      if (!empty($contexts)) {
        foreach ($contexts as $context) {
          // only include where context is empty or q for questions
          if($context['context'] == $context_to_use || empty($context['context']) || str_starts_with($context['context'], $context_to_use.'_')) {

            if(str_starts_with($context['context'], $context_to_use.'_') && $context['context'] != $context_to_use.'_'.$field_name){
              continue;
            }

            foreach ($context['translations'] as $word) {
              $translations[$context['context']][$word['source']] = $word['translation'];
            }
          }
        }
      }
      $cache_backend->set($cid, $translations);
      #dpm($translations);
      return $translations;
    }
  }

  /**
   * Designed to make image tags share a standard lightbox format
   * <a class="zuku-lightbox-image-link" href="https://zukureview.com/sites/default/files/vet/cowcv2big.jpg" rel="lightbox">
   * <img class="zuku-test-image" src="https://zukureview.com/sites/default/files/vet/cowcv2sm.jpg" title="image"></a>
   *
   * Tested on /node/14076/tester
   *
   * @param $html
   * @param $node
   * @return mixed
   */
  public function handleImages($html, $node){

    //$html_without_whitespace = preg_replace('/\s+/', '', $html);

    if (str_contains($html, '<img') && !str_contains($html, 'zuku-lightbox-image-link')) {

      $doc = _qwizard_LoadDomDocument($html);

      $tags = $doc->getElementsByTagName('img');
      foreach ($tags as $tag) {
        $tag_html = $this->getDomElementHTML($tag);
        $newelement = $doc->createDocumentFragment();
        $image_src = $tag->getAttribute('src');
        $newelement->appendXML('<a class="zuku-lightbox-image-link" href="'.$image_src.'" rel="lightbox">'.$tag_html.'</a>');
        $tag->parentNode->replaceChild($newelement, $tag);
        $html = $this->saveDomDocument($doc);
      }
    }


    return $html;
  }

  /**
   * Tested against /api-v1/question?_format=json&question_id=21579 - shouldnt have 500px table
   * /fr/node/14524/tester - shouldnt be all blue
   * @param $html
   * @param $node
   * @return array|mixed|string|string[]
   */
  public function handleTables($html, $node){
    $valid_nodes_to_ignore = [11522];
    if(in_array($node->id(), $valid_nodes_to_ignore)){
      return $html;
    }

    if (str_contains($html, '<table')) {
      // Get rid of all <th> elements after the first row'
      if(str_contains($html, '</tr>')){
        $table_rows = explode('</tr>', $html);
        $body_string = '';
        $max_headers = 2;
        foreach($table_rows as $key=>$row){
          if($key >= $max_headers){
            $body_string .= '</tr>'.$row;
          }
        }
        $fixed_body_string = str_replace('<th', '<td', $body_string);
        $fixed_body_string = str_replace('</th>', '</td>', $fixed_body_string);
        $html = str_replace($body_string, $fixed_body_string, $html);
      }

      $doc = _qwizard_LoadDomDocument($html);
      $tags = $doc->getElementsByTagName('table');
      foreach ($tags as $tag) {
        if(!empty($tag->getAttribute('style'))){
          $tag->setAttribute('style', '');

        $tag_html = '<table>'.$this->getInnerHTML($tag).'</table>';
        $newelement = $doc->createDocumentFragment();
        $newelement->appendXML($tag_html);
        $tag->parentNode->replaceChild($newelement, $tag);
        $html = $this->saveDomDocument($doc);
        }
      }
    }


    return $html;
  }

  public function handleTranslationReplacement($html, $node){
    //So "Digits" in English gets translated to "Numbers" (Chifres) when it should be instead "digiteaux"
    //BUN in English gets translated to "Hairdo" (Chignon) when it should be instead"Azote uréique sanguin" in all the tables
    $search_strings = ['chifres' => 'digiteaux', 'chignon' => 'azote uréique sanguin'];
    $modified_search_strings = [];
    foreach($search_strings as $search_string=>$string_replace){
      $modified_search_strings[$search_string] = $string_replace;
      $modified_search_strings[ucfirst($search_string)] = ucfirst($string_replace);
      $modified_search_strings[ucwords($search_string)] = ucwords($string_replace);
      $modified_search_strings[strtoupper($search_string)] = ucwords($string_replace);
    }
    #var_dump($modified_search_strings); exit;

    foreach($modified_search_strings as $search_string=>$string_replace) {
      if (str_contains($html, $search_string)) {
        $html = str_replace($search_string, $string_replace, $html);
      }
    }

    return $html;
  }

  /**
   * Used so that config is only checked once
   *
   * @return bool
   */
  private function isMerckBackupEnabled(): bool
  {
    $return = false;

    $config = \Drupal::config('zukuadmin.zukugeneralsettings');
    $config_setting = $config->get('merckvetmanual');
    $merck_prod = 'https://www.msdvetmanual.com';
    if (empty($config_setting)) {
      $config_setting = $merck_prod;
    }
    $this->merck_config_setting = $config_setting;

    if ($config_setting != $this->merck_prod) {
      $this->merck_backup_enabled = true;
      $return =  true;
    }else{
      $this->merck_backup_enabled = false;
    }

    return $return;
  }

  public function setupConfig(){
    if($this->reference_removal_enabled === null || $this->is_special_char_handling_enabled === null) {
      $config = \Drupal::config('zukuadmin.zukugeneralsettings');
      $config_setting = $config->get('is_reference_removal_enabled');
      $this->reference_removal_enabled = $config_setting;

      $config_setting = $config->get('is_special_char_handling_enabled');
      $this->is_special_char_handling_enabled = $config_setting;
    }
  }


  /**
   * Impements transformRootRelativeUrlsToAbsolute() using base url from site.
   *
   * @param $html
   *
   * @return string
   */
  public function transformRootRelativeUrlsToAbsolute($html) {
    //Check for strings in need of transforming first, the HTML function is quite slow as it loads DOM
    if(strpos($html, '"/') !== false){
      $html = Html::transformRootRelativeUrlsToAbsolute($html, $this->base_url);
    }
    return $html;
  }

  /**
   * Used to replace links to 'https://www.msdvetmanual.com' to 'https://zuku-mvbup.s3.us-east-2.amazonaws.com' if merckvetmanual is changed in /admin/zuku/config/general-settings
   * @param $str
   * @return array|mixed|string|string[]
   */
  public function transformMerckLinks($str, $use_backup = false){
    #if($this->merck_backup_enabled){
      $config_setting = $this->merck_config_setting;
      $merck_prod = 'https://www.msdvetmanual.com';
      if(empty($config_setting)){
        $config_setting = $merck_prod;
      }

      $doc = _qwizard_LoadDomDocument($str);

      $tags = $doc->getElementsByTagName('img');
      foreach ($tags as $tag) {
        $link = $tag->getAttribute('src');
        $new_link = $this->transformMerckSingleLink($tag->getAttribute('src'), $config_setting, $use_backup);
        if($new_link != $link){
          $str = str_replace($link, $new_link, $str);
        }
      }

      $tags = $doc->getElementsByTagName('a');
      foreach ($tags as $tag) {
        $link = $tag->getAttribute('href');
        $new_link = $this->transformMerckSingleLink($tag->getAttribute('href'), $config_setting, $use_backup);
        if($new_link != $link){
          $str = str_replace($link, $new_link, $str);
        }
      }
    #}

    return $str;
  }

  /**
   *  Transforms the merck link to redirected version, or alter to use backup.
   *
   * @param $str
   * @param $config_setting
   * @param $use_backup
   *
   * @return array|mixed|string|string[]
   */
  private function transformMerckSingleLink($str, $config_setting, $use_backup = true){
    // Only affect Merck URL's
    if(!str_starts_with($str, 'https://www.msdvetmanual.com')){
      return $str;
    }

    if(!$use_backup){
      $str = $this->transformMerckSingleLinkUsingMapping($str);
    }
    else {
      if (str_starts_with($str, 'https://www.msdvetmanual.com/multimedia')) {
        $str = str_replace('https://www.msdvetmanual.com/multimedia/zk/', $config_setting . '/', $str);
        // Some don't have /zk/, str_replace for these too
        $str = str_replace('https://www.msdvetmanual.com/multimedia/', $config_setting . '/', $str);

        // Merk versions start with v, backup ones typically do not. Replace
        $str = str_replace('.com/v', '.com/', $str);

        // If image does not end with an extension already, add JPG
        if (!preg_match('/(\.jpg|\.png|\.pdf)$/i', $str)) {
          $str = $str . '.jpg';
        }
      }
    }

    return $str;
  }

  /**
   * Alters a merck link using CSV mapping
   */
  function transformMerckSingleLinkUsingMapping($str){
    $url_parts = parse_url($str);

    $merck_suffix = explode('/', $url_parts['path']);
    $merck_suffix = end($merck_suffix);

    $merck_suffix = explode('#', $merck_suffix);
    $merck_suffix = reset($merck_suffix);


    // Only check for numeric looking suffixes
    if(is_numeric(substr($merck_suffix, -4))) {
      $merck_redirect_data = $this->getMerckRedirectData();
      if (!empty($merck_redirect_data[$merck_suffix])) {
        $str = 'https://www.msdvetmanual.com' . $merck_redirect_data[$merck_suffix];
      }
      else {
        if (qwizard_in_debug_mode()) {
          // Avoid many log entries.
          \Drupal::logger('merck_missing_redirect')
            ->notice('A redirect was looked up for ' . $str . ' but was not found in CSV');
        }
      }
    }

    return $str;
  }

  /**
   * Loads merck redirect CSV. Stored in cache
   * @return array
   */
  function getMerckRedirectData(){
    $QwCache = \Drupal::service('qwizard.cache');
    $cache_key = 'merck_old_to_new_url_mapping';
    $cache = $QwCache->checkCache($cache_key, true);
    $redirect_data = [];
    if(!empty($cache)){
      $redirect_data = $cache;
    }else {
      $csv_filename = getcwd() . '/modules/custom/qwizard/assets/merck_old_to_new_url_mapping.csv';
      $fileHandler = fopen($csv_filename, "r");
      $i=0;
      while (($csv_row = fgetcsv($fileHandler)) !== FALSE) {
        $i++;
        if($i <= 1){
          continue;
        }
        $from = $csv_row[0];
        $to = $csv_row[1];
        $redirect_data[$from] = $to;
      }

      #dpm($redirect_data);
      if(!empty($redirect_data)) {
        \Drupal::cache()->set($cache_key, $redirect_data, strtotime('+30 day'));
      }
    }

    return $redirect_data;
  }

  public function handleSpecialChars($html, $node){
    if($this->doesStringNeedSpecialCharHandling($html)){
      $html = $this->transformSpecialChars($html);
    }
    return $html;
  }

  private function doesStringNeedSpecialCharHandling($html){
    $string_has_special_chars = false;

    $encoding = mb_detect_encoding($html);
    if($encoding != 'UTF-8'){
      $string_has_special_chars = true;
      return $string_has_special_chars;
    }

    /*$chars_to_check = ['â', 'â', 'Ã','&#13;', 'â', '&#13'];
    foreach($chars_to_check as $char){
      if(str_contains($html, $char)){
        $string_has_special_chars = true;
        return $string_has_special_chars;
      }
    }*/

    return $string_has_special_chars;
  }
  private function transformSpecialChars($html){
    //echo 'STARTS as: '.PHP_EOL.$html.PHP_EOL;
    $encoding = mb_detect_encoding($html);
    if(!empty($encoding)) {
      $html = mb_convert_encoding($html, 'utf-8', $encoding);
    }
    $html = mb_convert_encoding($html, 'html-entities', 'utf-8');
    $html = mb_convert_encoding($html, 'utf-8', 'html-entities');

    //echo 'ENDS as: '.PHP_EOL.$html.PHP_EOL;
    return $html;
  }

  public function removeQuestionReferences($html, $node){
    if($this->isQuestionSupposedToHaveReferencesRemoved($node)){
      $html = $this->removeReferencesFromString($html);
    }

    return $html;
  }

  private function isQuestionSupposedToHaveReferencesRemoved($node){
    $return = true;

    // False if in a certain category
    $editor_tags_to_ignore = [];
    if($node->hasField('field_editor_quiz_tags')) {
      foreach ($node->field_editor_quiz_tags as $target_id) {
        if (in_array($target_id->target_id, $editor_tags_to_ignore)) {
          $return = false;
        }
      }
    }

    // Ignores "Diagnostic Imaging"
    if($return) {
      $topics_to_ignore = [265];
      if($node->hasField('field_topics')) {
        foreach ($node->field_topics as $target_id) {
          if (in_array($target_id->target_id, $topics_to_ignore)) {
            $return = false;
          }
        }
      }
    }

    return $return;
  }

  /**
   * Looks for strings like this and removes them:
   * <p>
   * Refs: <a href="https://www.aaha.org/" target="_blank">AAHA</a> and <a href="https://aaep.org/" target="_blank">AAEP</a>.
   * </p>
   */
  private function removeReferencesFromString($html){
    //$html_without_whitespace = preg_replace('/\s+/', '', $html);
    $search_strings = ['<p>Refs:' => 'Refs:', '<p>Ref:' => 'Ref:'];

    foreach($search_strings as $search_string=>$stripped_search_string) {
      if (str_contains($html, $search_string)) {

        $doc = _qwizard_LoadDomDocument($html);

        $tags = $doc->getElementsByTagName('p');
        foreach ($tags as $tag) {
          $tag_html = $this->getInnerHTML($tag);
          $tag_html_lower = strtolower($tag_html);
          if (str_contains($tag_html, $stripped_search_string)) {
            if(str_contains($tag_html_lower, 'zwingenberger')){
              // We need to preserve the Ref stuff in any of the 191 Image Qs that contain the word "Zwingenberger" in the Refs
              // Do nothing
            }
            elseif(str_contains($tag_html_lower, 'image courtesy') || str_contains($tag_html_lower, 'images courtesy')){
              // preserve image courtesy text
              // Start by splitting the paragraph on the text "image courtesy"
              $courtesy_text = 'image courtesy';
              if(str_contains($tag_html_lower, 'images courtesy')){
                $courtesy_text = 'images courtesy';
              }
              $courtesy_parts = explode($courtesy_text, $tag_html_lower);
              if(!empty($courtesy_parts[1]))
                // Using the text as two halves, replace the entire original <p> tag with just the second half
                $chars_to_courtesy = strlen($courtesy_parts[0]);
                $courtesy_remainder_text = str_replace('</p>', '', substr($tag_html, $chars_to_courtesy));

                // just replaces the original <p> tag with remainder text
                $newelement = $doc->createDocumentFragment();
                $newelement->appendXML($courtesy_remainder_text);
                $tag->parentNode->replaceChild($newelement, $tag);
                $html = $this->saveDomDocument($doc);
                #echo 'image courtesy left,';
            }else{
              #echo 'removing references,';
              // Found the <p> tag to remove. Strip it and regenerate HTML
              $tag->parentNode->removeChild($tag);
              $html = $this->saveDomDocument($doc);
            }

            // Fixes a bug where NID 9962 had them swapped, may be a sign of a larger issue
            $html = str_replace('Â', '&nbsp;', $html);
          }
        }
      }
    }

    return $html;
  }

  /**
   * Helper function, takes a DOMDocument and returns HTML without the junk it inserts
   */
  public function saveDomDocument($doc){
    $doc = mb_convert_encoding(utf8_decode(str_replace(['<body>', '</body>'], '', $doc->saveHTML(
      $doc->getElementsByTagName('body')->item(0)
    ))), 'utf-8', 'html-entities');
    return $doc;
  }

  /**
   * Taken from https://www.php.net/manual/en/class.domelement.php
   */
  function getInnerHTML($node)
  {
    $innerHTML= '';
    $children = $node->childNodes;
    foreach ($children as $child) {
      $innerHTML .= $child->ownerDocument->saveXML( $child );
    }

    return $innerHTML;
  }
  /**
   * Taken from https://www.php.net/manual/en/class.domelement.php
   */
  function getDomElementHTML($domElement){
    return $domElement->ownerDocument->saveXML($domElement);
  }
}
