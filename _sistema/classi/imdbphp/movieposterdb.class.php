<?php
 #############################################################################
 # IMDBPHP                                               (c) Itzchak Rehberg #
 # written by Itzchak Rehberg <izzysoft AT qumran DOT org>                   #
 # http://www.izzysoft.de/                                                   #
 # ------------------------------------------------------------------------- #
 # Get images from movieposterdb.com                                         #
 # ------------------------------------------------------------------------- #
 # This program is free software; you can redistribute and/or modify it      #
 # under the terms of the GNU General Public License (see doc/LICENSE)       #
 #############################################################################

 /* $Id: movieposterdb.class.php 466 2011-09-11 20:19:49Z izzy $ */

 require_once (dirname(__FILE__)."/mdb_base.class.php");

#================================================[ The MoviePosterDB class ]===
/** Accessing posters, covers, etc. from movieposterdb.com
 * @package MDBApi
 * @class movieposterdb
 * @extends mdb_base
 * @author Izzy (izzysoft AT qumran DOT org)
 * @copyright (c) 2009 by Itzchak Rehberg and IzzySoft
 * @version $Revision: 466 $ $Date: 2011-09-11 22:19:49 +0200 (So, 11. Sep 2011) $
 */
class movieposterdb extends mdb_base {

#------------------------------------------------------------[ Constructor ]---
  /** Initialize class
   * @constructor movieposterdb
   * @param string id IMDBID to use for data retrieval
   * @param optional integer limit maximum amount of images to retrieve
   *        (default: 20). Can be changed via set_limit()
   * @param optional boolean recurse whether to recurse if multiple versions
   *        are available (default: TRUE). Can be changed via set_recurse()
   */
  function __construct($id,$limit=20,$recurse=TRUE) {
    parent::__construct($id);
    $this->setid($id);
    $this->reset_lang();
    if ($this->force_agent) $this->user_agent = $this->force_agent;
    elseif ( in_array('HTTP_USER_AGENT',array_keys($_SERVER)) ) $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
    else $this->user_agent = $this->default_agent;
    $this->image_exts = array('jpg','png','gif','bmp');
  }

#----------------------------------------------------------------[ Helpers ]---
  /** Parse page for images
   * @method protected parse_list
   * @param optional string type (what image URLs to retrieve: 'poster' (default),
   *        'cover', 'textless', 'logo', 'other', 'unset')
   * @return array array of arrays[lang,url]
   */
  protected function parse_list($type='poster',$page_url='') {
    $url  = 'http://www.movieposterdb.com/movie/'.$this->imdbid().'/';
    $page = file_get_contents($url);
    $doc = new DOMDocument();
    @$doc->loadHTML($page);
    $xp = new DOMXPath($doc);
    $nodes = $xp->query("//a[contains(@href,\"/poster/\")]");
    $nodes2 = $xp->query("//img[@class=\"flag\"]");
    $nodes3 = $xp->query("//img[starts-with(@alt,\"Category\")]");
    foreach ($nodes as $node) {
      $img = $node->firstChild;
      $att = $img->tagName;
      $xx=0;
      while (strtolower($att)!='img') {
        $img = $img->nextSibling;
        $att = $img->tagName;
        $xx++; if ($xx>5) break; // prevent endless loops
      }
      $baseimgs[] = $img->getAttribute('src');
    }
    foreach ($nodes2 as $node) $baselangs[] = $node->getAttribute('alt');
    foreach ($nodes3 as $node) {
      $l = explode(': ',$node->getAttribute('alt'));
      $basecats[]  = $l[1];
    }
    $urls = array();
    for ($i=0;$i<count($baselangs);++$i) {
      if (!empty($this->langs)) {
        if ( !in_array(strtolower($baselangs[$i]),$this->langs) ) continue;
      }
      if ( strtolower($basecats[$i]) != $type ) continue;
      $im['lang'] = $baselangs[$i];
      $im['url']  = $baseimgs[$i];
      $urls[] = $im;
    }
    return $urls;
  }

#---------------------------------------------------------[ public methods ]---
  /** Get the posters
   * @method public posters
   * @return array array of arrays[lang,url]
   */
  public function posters() {
    return $this->parse_list('poster');
  }

  /** Get the cover images
   * @method public covers
   * @return array array of arrays[lang,url]
   */
  public function covers() {
    return $this->parse_list('cover');
  }

  /** Get the logos
   * @method public logos
   * @return array array of arrays[lang,url]
   */
  public function logos() {
    return $this->parse_list('logo');
  }

  /** Get the textless images
   * @method public textless
   * @return array array of arrays[lang,url]
   */
  public function textless() {
    return $this->parse_list('textless');
  }

  /** Get the images having no category set
   * @method public unsets
   * @return array array of arrays[lang,url]
   */
  public function unsets() {
    return $this->parse_list('unset');
  }

  /** Get the "other" images
   * @method public others
   * @return array array of arrays[lang,url]
   */
  public function others() {
    return $this->parse_list('other');
  }

  /** Reset everything to start a new search
   * @method public reset_vars
   * @see just a dummy to keep it compatible with previous versions.
   */
  public function reset_vars() {
    return;
  }

  /** Set a limit (maximum images to retrieve)
   * @method public set_limit
   * @param optional integer limit (default: 20)
   * @see just a dummy to keep it compatible with previous versions.
   */
  public function set_limit($limit=20) {
    return;
  }

  /** Shall we recurse if multiple versions are offered?
   * @method public set_recurse
   * @param optional boolean recurse (default: TRUE)
   * @see just a dummy to keep it compatible with previous versions.
   */
  public function set_recurse($recurse=TRUE) {
    return;
  }

  /** Restrict images to given languages. By default, no restriction is active;
   *  you may call this method multiple times to add multiple languages (e.g.
   *  your native plus EN plus US), and use the reset_lang method to clean.
   * @method public add_lang
   * @param string lang two-letter ISO language code
   */
  public function add_lang($lang) {
    $this->langs[] = strtolower($lang);
  }

  /** Reset the language filter built by the add_lang method to its default (i.e. empty)
   * @method public reset_lang
   */
  public function reset_lang() {
    $this->langs = array();
  }

  /** Save an image to the image dir
   *  The image will be retrieved from the passed URL and stored in the configured
   *  mdb::photodir, using the filename part of the URL specified.
   * @method public save_image
   * @param string url full URL to the image
   * @return boolean success
   */
  public function save_image($url) {
    if ( empty($this->photodir) ) return FALSE;
    $furl  = explode('/',$url);
    $fname = $furl[count($furl)-1];
    $ext   = explode('.',$fname);
    $fext  = $ext[count($ext)-1];
    if ( !in_array(strtolower($fext),$this->image_exts) ) return FALSE;
    echo "File: $fname<br>Ext: $fext<br>";
    if ( file_put_contents($this->photodir.$fname, file_get_contents($url)) ) return TRUE;
    return FALSE;
  }

}