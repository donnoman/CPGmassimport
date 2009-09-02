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
if (!defined('IN_COPPERMINE')) { die('Cannot be called directly. Use the admin menu.');}

define('ADDPIC_PHP', true);

require('include/init.inc.php');
require('include/picmgmt.inc.php');

if (!GALLERY_ADMIN_MODE) die('Access denied');



register_shutdown_function('reload');

function dir_parse($path)
{
    global $CONFIG;
    $blockdirs=array('.','..','CVS',str_replace('/','',$CONFIG['userpics']),'edit','_vti_cnf','.svn');
    if (substr($path,-1,1)!="/") $path=$path."/";
	if ($dir = opendir($path))
	{
	    // echo "\nSuccess: path: $path utf8_path: ".utf8_decode($path);
		$thisdir = array();
		while (false !== ($file = readdir($dir)))
		{
			if  (!in_array($file,$blockdirs))
			{
		//	    echo "\nchecking: ".$path.$file;
				if (is_dir($path.$file))
				{
					$thisdir[utf8_encode($file)] = dir_parse($path.$file);
				} else {
					$thisdir[] = utf8_encode($file);
				}
			}
		}
		return $thisdir;
	} else {
	   // echo "\nFail: path: $path utf8_path: ".utf8_decode($path);
	}
}

function createstructure($data, $parent, $path)
{
	global $CONFIG, $filelist;

	$i = 0;

	$names = array_keys($data);

	$cat_names = array();

	foreach ($data as $set)
	{
		if (is_array($set)) $cat_names[] = $names[$i];
		$i++;
	}

	$i = 0;

	foreach ($cat_names as $name)
	{
		unset($aid);

		$base = true;

		foreach ($data[$name] as $lower)
		{
			if (is_array($lower)){
				$base = false;
				break;
			}
		}

		if ($base){
			$directory = $name;
		} else {
			$parent2 = createcategory($parent, $name);
			$directory = $data[$name];
		}

		if (is_array($directory))
		{
			createstructure($directory, $parent2, "$path/$name");
		} else {
			if (!isset($aid)) $aid = createalbum($parent, $name);
			$contents = dir_parse(utf8_decode($path."/".$name));
			foreach ($contents as $file) {
				if (strncmp($file,$CONFIG['thumb_pfx'],strlen($CONFIG['thumb_pfx'])) != 0  &&  strncmp($file,$CONFIG['normal_pfx'],strlen($CONFIG['normal_pfx'])) != 0 &&  strncmp($file,'orig_',strlen('orig_')) != 0 && strncmp($file,'mini_',strlen('mini_')) != 0 && strpos('Thumbs.db',$file) === false ) {
					$filelist[utf8_encode("$path/$name/$file")] = $aid;
				}
			}
		}
	}
}

function cleanupfilelist() {
	global $filelist, $CONFIG,  $lang_CPGMassImport;

	$sql = "SELECT aid, CONCAT('./" . addslashes($CONFIG['fullpath']) . "',filepath,filename) As filepath FROM {$CONFIG['TABLE_PICTURES']} ORDER BY filepath";
    $result = cpg_db_query($sql);
	while($row = mysql_fetch_row($result)) {
		$arr[$row[1]] = $row[0];
	}
	flush();

	echo count($filelist) ." ". $lang_CPGMassImport['pics_found'] . "<br />";
	echo count($arr) ." ". $lang_CPGMassImport['pics_indb'] . "<br />";
	if (is_array($arr)) {
	   $filelist = array_diff_assoc($filelist,$arr);
	}
	echo $lang_CPGMassImport['pics_afterfilter']. " " . count($filelist) . " ". $lang_CPGMassImport['pics_to_add'] ."<br />";
	//var_dump($filelist); //debug
}

function populatealbums()
{
	global $filelist, $counter;

	$lim = $_POST['hardlimit'] > 0 ? $_POST['hardlimit'] : getrandmax();

	foreach ($filelist as $filename => $aid)
	{
		if ($counter < $lim)
		{
			set_time_limit(180);
			//echo "$filename - $aid<br />"; //chatty debug
			addpic($aid, $filename);
			$filelist = array_diff_assoc($filelist, array($filename => $aid));
			usleep($_POST['sleep'] * 1000);
			$counter++;
		}
	}
}

function createcategory($parent, $name)
{
	global $CONFIG,  $lang_CPGMassImport;

	$parent=(int)$parent;

	$sql = "SELECT cid " . "FROM {$CONFIG['TABLE_CATEGORIES']} " . "WHERE name='" . addslashes($name) . "' AND parent=" . $parent . " LIMIT 1";
    $result = cpg_db_query($sql);

    if (mysql_num_rows($result)) {
		echo $lang_CPGMassImport['cat_exists']." : $name<br />";
		$row = mysql_fetch_row($result);
		$cid = $row[0];
	} else {
		cpg_db_query("INSERT INTO {$CONFIG['TABLE_CATEGORIES']} (pos, parent, name, description) VALUES ('10000', '$parent', '" . addslashes($name) . "','')");
		echo $lang_CPGMassImport['cat_create'].": $name<br/>";
		$cid = mysql_insert_id();
	}
	flush();

	return $cid;
}

function createalbum($category, $title)
{
	global $CONFIG,  $lang_CPGMassImport;

	$sql = "SELECT aid " . "FROM {$CONFIG['TABLE_ALBUMS']} " . "WHERE title='" . addslashes($title) . "' AND category=" . (INT)$category . " LIMIT 1";
    $result = cpg_db_query($sql);

    if (mysql_num_rows($result)) {
		echo $lang_CPGMassImport['album_exists']." : $title<br />";
		$row = mysql_fetch_row($result);
		$aid = $row[0];
	} else {
		cpg_db_query("INSERT INTO {$CONFIG['TABLE_ALBUMS']} (category, title, pos,description) VALUES ('".(INT)$category."', '" . addslashes($title) . "', '10000','')");
		echo $lang_CPGMassImport['album_create']." : $title<br/>";
		$aid = mysql_insert_id();
	}
	flush();
	return $aid;
}

function addpic($aid, $pic_file)
{
	global $CONFIG, $lang_CPGMassImport;

	$pic_file = utf8_decode(str_replace('./' . $CONFIG['fullpath'], '', $pic_file));
	$dir_name = dirname($pic_file) . "/";
	$dir_name = ( substr($dir_name,0,1) == "/" ) ? substr($dir_name,1) : $dir_name;
	$file_name = basename($pic_file);
	$sane_name = str_replace('%20', '_', $file_name);
	$sane_name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $sane_name);
	$sane_name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $sane_name);
	while ( strpos($sane_name,'__') !== FALSE ) {
		$sane_name = str_replace('__', '_', $sane_name);
	}
	$c = 0;
	$sane_name2 = $sane_name;

//	$sql = "SELECT pid " . "FROM {$CONFIG['TABLE_PICTURES']} " . "WHERE filepath='" . addslashes($dir_name) . "' AND filename='" . addslashes($sane_name) . "' " . "LIMIT 1";
//	$result = cpg_db_query($sql);

//	$extra = strstr($pic_file, $sane_name) ? '' : " (as $sane_name)";
//	if (mysql_num_rows($result)) {
//		echo $lang_CPGMassImport['picture']." '$pic_file' ".$lang_CPGMassImport['already']."$extra<br />";
//	} else {
		while (($sane_name != $file_name) && file_exists("./" . $CONFIG['fullpath'] . $dir_name . $sane_name))
		{
			$c++;
			$sane_name = $c . '_' . $sane_name2;
		}

		$source = "./" . $CONFIG['fullpath'] . $dir_name . $file_name;
		rename($source, "./" . $CONFIG['fullpath'] . $dir_name . $sane_name);

		if (add_picture($aid, utf8_encode($dir_name), $sane_name)) {
			echo $lang_CPGMassImport['picture']." '$pic_file' ".$lang_CPGMassImport['added']."$extra<br />";
		} else {
			echo $lang_CPGMassImport['picture']." '$pic_file' ".$lang_CPGMassImport['failed']."$extra<br />";
		}
//	}
	flush();
}

function reload()
{
	global $filelist, $counter, $lang_CPGMassImport, $lang_continue;

	if (count($_POST) == 0) exit();

	$remaining = countup($filelist);

	$filelist = base64_encode(serialize($filelist));
	$auto = (isset($_POST['auto']) && $_POST['auto']) ? 'checked = "checked"' : '';
	$counter = $counter ? "$counter ".$lang_CPGMassImport['files_added'] : $lang_CPGMassImport['structure_created'];
	$directory = isset($_POST['directory'])  ? $_POST['directory'] : '';
	$sleep = isset($_POST['sleep'])  ? $_POST['sleep'] : '1000';
	$hardlimit = isset($_POST['hardlimit'])  ? $_POST['hardlimit'] : '0';
	$js = ($_POST['auto'] && $remaining) ? '<script type="text/javascript"> onload = document.form.submit();</script>' : '';
	$scriptname = 'index.php?file=CPGMassImport/import';

	if (!connection_aborted()) {
        echo <<< EOT
        	</br>
        	$counter, $remaining {$lang_CPGMassImport['files_to_add']}.<br />
        	<form name="form" method="POST" action="$scriptname">
        		<input name="filelist" type="hidden" value="$filelist">
        		<input type="hidden" name="directory" value="$directory">
        		{$lang_CPGMassImport['sleep']}: <input type="text" name="sleep" value="$sleep">
        		{$lang_CPGMassImport['hardlimit']}: <input type="text" name="hardlimit" value="$hardlimit">
        		<input type="submit" value="$lang_continue">
        		{$lang_CPGMassImport['autorun']}: <input type="checkbox" name="auto" value="1" $auto>
        	</form>

EOT;
    }
pagefooter();
echo $js;
}

function countup($array)
{
	$result = 0;

	foreach ($array as $a)
		$result += is_array($a) ? countup($a) : count($a);

	return $result;
}

pageheader($lang_CPGMassImport['admin_title']);

if (isset($_POST['filelist'])){

	$filelist = unserialize(base64_decode($_POST['filelist']));

	$counter = 0;
	echo '</br>';
	populatealbums();

} elseif (isset($_POST['start'])) {

	$data = dir_parse('./' . $CONFIG['fullpath'] . trim($_POST['directory']));

	if (!$_POST['directory']) {
        echo $lang_CPGMassImport['root_use'].'<br />';
        $parent=0;
    } else {
        $sql = "SELECT cid " . "FROM {$CONFIG['TABLE_CATEGORIES']} " . "WHERE parent='0' AND name='" . $_POST['directory'] . "' " . "LIMIT 1";
    	$result = cpg_db_query($sql);
        if (mysql_num_rows($result)) {
			echo $lang_CPGMassImport['root_exists']." : " . $_POST['directory'] . "<br />";
			$row = mysql_fetch_row($result);
			$cid = $row[0];
		} else {
			cpg_db_query("INSERT INTO {$CONFIG['TABLE_CATEGORIES']} (pos, parent, name) VALUES ('10000', '0', '{$_POST['directory']}')");
			echo $lang_CPGMassImport['root_create'].'<br />';
			$cid = mysql_insert_id();
		}
	}

	$path = ( trim($_POST['directory'])=='' ) ? rtrim($CONFIG['fullpath'],"/") : $CONFIG['fullpath'] . trim($_POST['directory']);

	echo $lang_CPGMassImport['path'].": " . $path . "</br>";

	createstructure($data, $cid, './' . $path);
//echo "before:\n";
//var_dump($filelist);
	cleanupfilelist();
//echo "after: \n";
//var_dump($filelist);

} else {

	$scriptname = 'index.php?file=CPGMassImport/import';

	echo <<< EOT

<form method="POST" action="$scriptname">
	{$lang_CPGMassImport['subdir_desc']}: <input type="text" name="directory" value=""><br /><br />
	{$lang_CPGMassImport['sleep_desc']}: <input type="text" name="sleep" value="1000"><br /><br />
	{$lang_CPGMassImport['autorun_desc']}: <input type="checkbox" name="auto" value="1"><br /><br />
	{$lang_CPGMassImport['hardlimit_desc']}: <input type="text" name="hardlimit" value="10"><br /><br />
	<input type="submit" name="start" value="{$lang_CPGMassImport['begin']}">
</form>

EOT;
}

?>