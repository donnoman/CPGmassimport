<?php
/**************************************************
  Coppermine Photo Gallery 1.4.3 CPGMassImport Plugin
  *************************************************
  CPGMassImport
  *************************************************                                       //
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
***************************************************/

if (!defined('IN_COPPERMINE')) die('Not in Coppermine...');

$thisplugin->add_action('page_start', 'CPGMassImport_start');

function CPGMassImport_add_admin_button($href,$title,$target,$link)
{
  global $template_gallery_admin_menu;

  $new_template=$template_gallery_admin_menu;
  $button=template_extract_block($new_template,'documentation');
  $params = array(
      '{DOCUMENTATION_HREF}' => $href,
      '{DOCUMENTATION_TITLE}' => $title,
      'target="cpg_documentation"' => $target,
      '{DOCUMENTATION_LNK}' => $link,
   );
   $new_button="<!-- BEGIN $link -->".template_eval($button,$params)."<!-- END $link -->\n";
   template_extract_block($template_gallery_admin_menu,'documentation',"<!-- BEGIN documentation -->" . $button . "<!-- END documentation -->\n" . $new_button);
}

function CPGMassImport_start($html) {
    global $template_gallery_admin_menu, $lang_CPGMassImport, $CONFIG;
    if (GALLERY_ADMIN_MODE) {
    	if (file_exists("plugins/CPGMassImport/lang/{$CONFIG['lang']}.php")) {
    		require "plugins/CPGMassImport/lang/{$CONFIG['lang']}.php";
    	} else require 'plugins/CPGMassImport/lang/english.php';
    	
    	CPGMassImport_add_admin_button('index.php?file=CPGMassImport/import',$lang_CPGMassImport['description'],'',$lang_CPGMassImport['admin_title']);
    }
}



?>