﻿<?php

/*
MINIGAL NANO
- A PHP/HTML/CSS based image gallery script

This script and included files are subject to licensing from Creative Commons (http://creativecommons.org/licenses/by-sa/2.5/)
You may use, edit and redistribute this script, as long as you pay tribute to the original author by NOT removing the linkback to www.minigal.dk ("Powered by MiniGal Nano x.x.x")

MiniGal Nano is created by Thomas Rybak

Copyright 2010 by Thomas Rybak
Support: www.minigal.dk
Community: www.minigal.dk/forum

Please enjoy this free script!


Upgraded to https://github.com/sebsauvage/MinigalNano
by Sébastien SAUVAGE.

*/

// Do not edit below this section unless you know what you are doing!


//-----------------------
// Debug stuff
//-----------------------
//	error_reporting(E_ERROR);
//	error_reporting(E_ALL);
	error_reporting(-1);
/*
	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];
	$starttime = $mtime;
*/

header('Content-Type: text/html; charset=utf-8'); // We use UTF-8 for proper international characters handling.
$version = "0.3.7";
ini_set("memory_limit","256M");

require("config.php");
//-----------------------
// DEFINE VARIABLES
//-----------------------
$page_navigation = "";
$breadcrumb_navigation = "";
$thumbnails = "";
$new = "";
$images = "";
$exif_data = "";
$messages = "";
$comment = "";
$xmlrss="";
$rssitems="";
$rsslink="";

//-----------------------
// PHP ENVIRONMENT CHECK
//-----------------------
if (!function_exists('exif_read_data') && $display_exif == 1) {
	$display_exif = 0;
	$messages = "Error: PHP EXIF is not available. Set &#36;display_exif = 0; in config.php to remove this message";
}

//-----------------------
// FUNCTIONS
//-----------------------
function is_directory($filepath) {
	// $filepath must be the entire system path to the file
	if (!@opendir($filepath)) return FALSE;
	else {
		return TRUE;
		closedir($filepath);
	}
}

function padstring($name, $length) {
	global $label_max_length;
	if (!isset($length)) $length = $label_max_length;
	if (strlen($name) > $length) {
	  return substr($name,0,$length) . "...";
   } else return $name;
}
function getfirstImage($dirname) {
	$imageName = false;
	$ext = array("jpg", "png", "jpeg", "gif", "JPG", "PNG", "GIF", "JPEG");
	if($handle = opendir($dirname))
	{
		while(false !== ($file = readdir($handle)))
		{
			$lastdot = strrpos($file, '.');
			$extension = substr($file, $lastdot + 1);
			if ($file[0] != '.' && in_array($extension, $ext)) break;
		}
		$imageName = $file;
		closedir($handle);
	}
	return($imageName);
}
function readEXIF($file) {
		$exif_data = "";
		$exif_idf0 = exif_read_data ($file,'IFD0' ,0 );
		$emodel = $exif_idf0['Model'];

		$efocal = $exif_idf0['FocalLength'];
		list($x,$y) = preg_split('/', $efocal);
		$efocal = round($x/$y,0);
	   
		$exif_exif = exif_read_data ($file,'EXIF' ,0 );
		$eexposuretime = $exif_exif['ExposureTime'];
	   
		$efnumber = $exif_exif['FNumber'];
		list($x,$y) = preg_split('/', $efnumber);
		$efnumber = round($x/$y,0);

		$eiso = $exif_exif['ISOSpeedRatings'];
			   
		$exif_date = exif_read_data ($file,'IFD0' ,0 );
		$edate = $exif_date['DateTime'];
		if (strlen($emodel) > 0 OR strlen($efocal) > 0 OR strlen($eexposuretime) > 0 OR strlen($efnumber) > 0 OR strlen($eiso) > 0) $exif_data .= "::";
		if (strlen($emodel) > 0) $exif_data .= "$emodel";
		if ($efocal > 0) $exif_data .= " | $efocal" . "mm";
		if (strlen($eexposuretime) > 0) $exif_data .= " | $eexposuretime" . "s";
		if ($efnumber > 0) $exif_data .= " | f$efnumber";
		if (strlen($eiso) > 0) $exif_data .= " | ISO $eiso";
		return($exif_data);
}
function checkpermissions($file) {
	global $messages;
	if (substr(decoct(fileperms($file)), -1, strlen(fileperms($file))) < 4 OR substr(decoct(fileperms($file)), -3,1) < 4) $messages = "At least one file or folder has wrong permissions. Learn how to <a href='http://minigal.dk/faq-reader/items/how-do-i-change-file-permissions-chmod.html' target='_blank'>set file permissions</a>";
}

//-----------------------
// CHECK FOR NEW VERSION
//-----------------------
// New version check disabled because original author does not update it software anymore.
/*
if (ini_get('allow_url_fopen') == "1") {
	$file = @fopen ("http://www.minigal.dk/minigalnano_version.php", "r");
	$server_version = fgets ($file, 1024);
	if (strlen($server_version) == 5 ) { //If string retrieved is exactly 5 chars then continue
		if (version_compare($server_version, $version, '>')) $messages = "MiniGal Nano $server_version is available! <a href='http://www.minigal.dk/minigal-nano.html' target='_blank'>Get it now</a>";
	}
	fclose($file);
}
*/

if (!defined("GALLERY_ROOT")) define("GALLERY_ROOT", "");
//par défaut on n'est pas en mode rss -> 0
$rssMode = 0;
//on récupère le mode
if (!empty($_GET['rss'])) $rssMode = $_GET['rss'];
$requestedDir = '';
if (!empty($_GET['dir']))
{
    $requestedDir = $_GET['dir'];
    //s'il y a un dossier le lien doit en tenir compte
    $rsslink="?rss=1&dir=".$requestedDir;
}
else $rsslink="?rss=1";
$thumbdir = rtrim('photos/'.$requestedDir,'/');

$thumbdir = str_replace('/..', '', $thumbdir); // Prevent directory traversal attacks.
$currentdir = GALLERY_ROOT . $thumbdir;

//-----------------------
// READ FILES AND FOLDERS
//-----------------------
$files = array();
$dirs = array();
 if (is_directory($currentdir) && $handle = opendir($currentdir))
 {
	while (false !== ($file = readdir($handle)))
	{
// 1. LOAD FOLDERS
		if (is_directory($currentdir . "/" . $file))
			{ 
				if ($file != "." && $file != ".." )
				{
					checkpermissions($currentdir . "/" . $file); // Check for correct file permission
					// Set thumbnail to folder.jpg if found:
					if (file_exists($currentdir. '/' . $file . '/folder.jpg'))
					{
						$dirs[] = array(
							"name" => $file,
							"date" => filemtime($currentdir . "/" . $file . "/folder.jpg"),
							"html" => "<li><a href='?dir=" .ltrim($requestedDir . "/" . $file, "/") . "'><em>" . padstring($file, $label_max_length) . "</em><span></span><img src='" . GALLERY_ROOT . "createthumb.php?filename=$currentdir/" . $file . "/folder.jpg&amp;size=$thumb_size'  alt='$label_loading' /></a></li>");
					}  else
					{
					// Set thumbnail to first image found (if any):
						unset ($firstimage);
						$firstimage = getfirstImage("$currentdir/" . $file);
						if ($firstimage != "") {
						$dirs[] = array(
							"name" => $file,
							"date" => filemtime($currentdir . "/" . $file),
							"html" => "<li><a href='?dir=" . ltrim($requestedDir . "/" . $file, "/") . "'><em>" . padstring($file, $label_max_length) . "</em><span></span><img src='" . GALLERY_ROOT . "createthumb.php?filename=$thumbdir/" . $file . "/" . $firstimage . "&amp;size=$thumb_size'  alt='$label_loading' /></a></li>");
						} else {
						// If no folder.jpg or image is found, then display default icon:
							$dirs[] = array(
								"name" => $file,
								"date" => filemtime($currentdir . "/" . $file),
								"html" => "<li><a href='?dir=" . ltrim($requestedDir . "/" . $file, "/") . "'><em>" . padstring($file) . "</em><span></span><img src='" . GALLERY_ROOT . "images/folder_" . strtolower($folder_color) . ".png' width='$thumb_size' height='$thumb_size' alt='$label_loading' /></a></li>");
						}
					}
				}
			}	

// 2. LOAD CAPTIONS
$img_captions['']='';
if (file_exists($currentdir ."/captions.txt"))
{
	$file_handle = fopen($currentdir ."/captions.txt", "rb");
	while (!feof($file_handle) ) 
	{	
		$line_of_text = fgets($file_handle);
		$parts = explode('/n', $line_of_text);
		foreach($parts as $img_capts)
		{
			list($img_filename, $img_caption) = explode('|', $img_capts);	
			$img_captions[$img_filename] = $img_caption;
		}
	}
	fclose($file_handle);
}

// 3. LOAD FILES
				if ($file != "." && $file != ".." && $file != "folder.jpg")
		  		{
		  			// JPG, GIF and PNG
		  			if (preg_match("/.jpg$|.gif$|.png$/i", $file))
		  			{
						//Read EXIF
						if ($display_exif == 1) $img_captions[$file] .= readEXIF($currentdir . "/" . $file);

						// Read the optionnal image title and caption in html file (image.jpg --> image.jpg.html)
						// Format: title::caption
						// Example: My cat::My cat like to <i>roll</i> on the floor.
						// If file is not provided, image filename will be used instead.
						checkpermissions($currentdir . "/" . $file);

						$img_captions[$file] = $file;
						if (is_file($currentdir.'/'.$file.'.html')) { $img_captions[$file] = $file.'::'.htmlspecialchars(file_get_contents($currentdir.'/'.$file.'.html'),ENT_QUOTES); }
						if ($lazyload) {
							$files[] = array (
			  				"name" => $file,
							"date" => filemtime($currentdir . "/" . $file),
							"size" => filesize($currentdir . "/" . $file),
				  			"html" => "<li><a href='" . $currentdir . "/" . $file . "' rel='lightbox[billeder]' title=\"".htmlentities($img_captions[$file])."\"><img class=\"b-lazy\" src=data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw== data-src='" . GALLERY_ROOT . "createthumb.php?filename=" . $thumbdir . "/" . $file . "&amp;size=$thumb_size' alt='$label_loading' /></a></li>",
                                                        //chaque item du flux rss
                                                        "rss" => "<item><title>".$file."</title><link>".$gallery_link."/?dir=".$requestedDir."</link><description><img  src='" . $gallery_link . "createthumb.php?filename=" . $thumbdir . "/" . $file . "&amp;size=$thumb_size' alt='$label_loading' /></description></item>\n");
						} else {
							$files[] = array (
			  				"name" => $file,
							"date" => filemtime($currentdir . "/" . $file),
							"size" => filesize($currentdir . "/" . $file),
				  			"html" => "<li><a href='" . $currentdir . "/" . $file . "' rel='lightbox[billeder]' title=\"".htmlentities($img_captions[$file])."\"><img  src='" . GALLERY_ROOT . "createthumb.php?filename=" . $thumbdir . "/" . $file . "&amp;size=$thumb_size' alt='$label_loading' /></a></li>",
                                                        //chaque item du flux rss
                                                        "rss" => "<item><title>".$file."</title><link>".$gallery_link."/?dir=".$requestedDir."</link><description><img src='" . $gallery_link . "createthumb.php?filename=" . $thumbdir . "/" . $file . "&amp;size=$thumb_size' alt='$label_loading' /></description></item>\n");
						}
		  			}
					// Other filetypes
					$extension = "";
					if (preg_match("/.pdf$/i", $file)) $extension = "PDF"; // PDF
					if (preg_match("/.zip$/i", $file)) $extension = "ZIP"; // ZIP archive
					if (preg_match("/.rar$|.r[0-9]{2,}/i", $file)) $extension = "RAR"; // RAR Archive
					if (preg_match("/.tar$/i", $file)) $extension = "TAR"; // TARball archive
					if (preg_match("/.gz$/i", $file)) $extension = "GZ"; // GZip archive
					if (preg_match("/.doc$|.docx$/i", $file)) $extension = "DOCX"; // Word
					if (preg_match("/.ppt$|.pptx$/i", $file)) $extension = "PPTX"; //Powerpoint
					if (preg_match("/.xls$|.xlsx$/i", $file)) $extension = "XLXS"; // Excel
										
					if ($extension != "")
			  		{
		  				$files[] = array (
		  					"name" => $file,
							"date" => filemtime($currentdir . "/" . $file),
							"size" => filesize($currentdir . "/" . $file),
			  				"html" => "<li><a href='" . $currentdir . "/" . $file . "' title='$file'><em-pdf>" . padstring($file, 20) . "</em-pdf><span></span><img src='" . GALLERY_ROOT . "images/filetype_" . $extension . ".png' width='$thumb_size' height='$thumb_size' alt='$file' /></a></li>",
                                                        //chaque item du flux rss
                                                        "rss" => "<item><title>".$file."</title><link>".$gallery_link."/?dir=".$requestedDir."</link></item>\n");
					}
	 			}   		
	}
  closedir($handle);
  } else die("ERROR: Could not open ".htmlspecialchars(stripslashes($currentdir))." for reading!");

//-----------------------
// SORT FILES AND FOLDERS
//-----------------------
if (sizeof($dirs) > 0) 
{
	foreach ($dirs as $key => $row)
	{
		if($row["name"] == "") unset($dirs[$key]); //Delete empty array entries
		$name[$key] = strtolower($row['name']);
		$date[$key] = strtolower($row['date']);
	}	
	if (strtoupper($sortdir_folders) == "DESC") array_multisort($$sorting_folders, SORT_DESC, $name, SORT_DESC, $dirs);
	else array_multisort($$sorting_folders, SORT_ASC, $name, SORT_ASC, $dirs);
}
if (sizeof($files) > 0)
{
    foreach ($files as $key => $row)
    {
        if($row["name"] == "") unset($files[$key]); //Delete empty array entries
        $name[$key] = strtolower($row['name']);
        $date[$key] = strtolower($row['date']);
        $size[$key] = strtolower($row['size']);
    }
    if ($rssMode>0) //si en mode flux on trie toujours par date decroissante
    {
        @array_multisort($date, SORT_DESC, $name, SORT_ASC, $files);
    }
    elseif (strtoupper($sortdir_files) == "DESC") @array_multisort($$sorting_files, SORT_DESC, $name, SORT_ASC, $files);
    else @array_multisort($$sorting_files, SORT_ASC, $name, SORT_ASC, $files);
}
//calcul du nombre d'items par page qui ne concerne pas les flux
if ($rssMode==0)
{
//-----------------------
// OFFSET DETERMINATION
//-----------------------
	if (!isset($_GET["page"])) $_GET["page"] = 1;
	$offset_start = ($_GET["page"] * $thumbs_pr_page) - $thumbs_pr_page;
	if (!isset($_GET["page"])) $offset_start = 0;
	$offset_end = $offset_start + $thumbs_pr_page;
	if ($offset_end > sizeof($dirs) + sizeof($files)) $offset_end = sizeof($dirs) + sizeof($files);

	if ($_GET["page"] == "all" || $lazyload)
	{
		$offset_start = 0;
		$offset_end = sizeof($dirs) + sizeof($files);
	}

//-----------------------
// PAGE NAVIGATION
//-----------------------
if (!$lazyload && sizeof($dirs) + sizeof($files) > $thumbs_pr_page)
{
	$page_navigation .= "$label_page ";
	for ($i=1; $i <= ceil((sizeof($files) + sizeof($dirs)) / $thumbs_pr_page); $i++)
	{
		if ($_GET["page"] == $i)
			$page_navigation .= "$i";
			else
				$page_navigation .= "<a href='?dir=" . $requestedDir . "&amp;page=" . ($i) . "'>" . $i . "</a>";
		if ($i != ceil((sizeof($files) + sizeof($dirs)) / $thumbs_pr_page)) $page_navigation .= " | ";
	}
	//Insert link to view all images
	if ($_GET["page"] == "all") $page_navigation .= " | $label_all";
	else $page_navigation .= " | <a href='?dir=" . $requestedDir . "&amp;page=all'>$label_all</a>";
}

//-----------------------
// BREADCRUMB NAVIGATION
//-----------------------
if ($requestedDir != "")
{
	$breadcrumb_navigation .= "<a href='?dir='>" . $label_home . "</a> > ";
	$navitems = explode("/", $_REQUEST['dir']);
	for($i = 0; $i < sizeof($navitems); $i++)
	{
		if ($i == sizeof($navitems)-1) $breadcrumb_navigation .= $navitems[$i];
		else
		{
			$breadcrumb_navigation .= "<a href='?dir=";
			for ($x = 0; $x <= $i; $x++)
			{
				$breadcrumb_navigation .= $navitems[$x];
				if ($x < $i) $breadcrumb_navigation .= "/";
			}
			$breadcrumb_navigation .= "'>" . $navitems[$i] . "</a> > ";
		}
	}
} else $breadcrumb_navigation .= $label_home;

//Include hidden links for all images BEFORE current page so lightbox is able to browse images on different pages
for ($y = 0; $y < $offset_start - sizeof($dirs); $y++)
{	
	$breadcrumb_navigation .= "<a href='" . $currentdir . "/" . $files[$y]["name"] . "' rel='lightbox[billeder]' class='hidden' title='" . $img_captions[$files[$y]["name"]] . "'></a>";
}

//-----------------------
// DISPLAY FOLDERS
//-----------------------
if (count($dirs) + count($files) == 0) {
	$thumbnails .= "<li>$label_noimages</li>"; //Display 'no images' text
	if($currentdir == "photos") $messages = "It looks like you have just installed MiniGal Nano. Please run the <a href='system_check.php'>system check tool</a>";
}
$offset_current = $offset_start;
for ($x = $offset_start; $x < sizeof($dirs) && $x < $offset_end; $x++)
{
	$offset_current++;
	$thumbnails .= $dirs[$x]["html"];
}
}//fin du calcul qui ne concerne pas les flux

//-----------------------
// DISPLAY FILES
//-----------------------
if ($rssMode==0)//en mode normal
{
    for ($i = $offset_start - sizeof($dirs); $i < $offset_end && $offset_current < $offset_end; $i++)
    {
        if ($i >= 0)
        {
            $offset_current++;
            $thumbnails .= $files[$i]["html"];
        }
    }
}
else //en mode flux
{
    for ($i = 0; $i < min(sizeof($files), $nb_items_rss); $i++) // on parcourt tous les fichiers (au plus nb_items_rss)
    {
        $rssitems .= $files[$i]["rss"];
    }    
}

//Include hidden links for all images AFTER current page so lightbox is able to browse images on different pages
if ($i<0) $i=1;
for ($y = $i; $y < sizeof($files); $y++)
{	
	$page_navigation .= "<a href='" . $currentdir . "/" . $files[$y]["name"] . "' rel='lightbox[billeder]'  class='hidden' title='" . $img_captions[$files[$y]["name"]] . "'></a>";
}

//-----------------------
// OUTPUT MESSAGES
//-----------------------
if ($messages != "") {
$messages = "<div id=\"topbar\">" . $messages . " <a href=\"#\" onclick=\"document.getElementById('topbar').style.display = 'none';\";><img src=\"images/close.png\" /></a></div>";
}

// Read folder comment.
$comment_filepath = $currentdir . $file . "/comment.html";
if (file_exists($comment_filepath))
{
	$fd = fopen($comment_filepath, "r");
	$comment = fread($fd,filesize ($comment_filepath));
	fclose($fd);
}
//PROCESS TEMPLATE FILE
if(GALLERY_ROOT != "") $templatefile = GALLERY_ROOT . "templates/integrate.html";
else $templatefile = "templates/" . $templatefile . ".html";

if($rssMode>0) 
{
// A terme un switch/case sur le numero 1 rss, 2 atom, etc. ?

//on construit l'entete
$xmlrss="<?xml version=\"1.0\"?>\n";
$xmlrss.="<rss version=\"2.0\">\n";
$xmlrss.="  <channel>\n";
$xmlrss.="    <title>".$title."</title>\n";
$xmlrss.="    <link>".$gallery_link."</link>\n";
$xmlrss.="    <description>".$description."</description>\n";
//on ajoute tous les items
$xmlrss.=$rssitems;
//on ferme les balises
$xmlrss.="  </channel>\n";
$xmlrss.="</rss>";

//on "produit" le rss
echo $xmlrss;
}
else
{
    if(!$fd = fopen($templatefile, "r"))
    {
        echo "Template ".htmlspecialchars(stripslashes($templatefile))." not found!";
        exit();
    }
    else
    {
        $template = fread ($fd, filesize ($templatefile));
        fclose ($fd);
        $template = stripslashes($template);
        $template = preg_replace("/<% title %>/", $title, $template);
        $template = preg_replace("/<% messages %>/", $messages, $template);
        $template = preg_replace("/<% author %>/", $author, $template);
        $template = preg_replace("/<% gallery_root %>/", GALLERY_ROOT, $template);
        $template = preg_replace("/<% images %>/", "$images", $template);
        $template = preg_replace("/<% thumbnails %>/", "$thumbnails", $template);
        $template = preg_replace("/<% breadcrumb_navigation %>/", "$breadcrumb_navigation", $template);
        $template = preg_replace("/<% page_navigation %>/", "$page_navigation", $template);
        $template = preg_replace("/<% folder_comment %>/", "$comment", $template);
        $template = preg_replace("/<% bgcolor %>/", "$backgroundcolor", $template);
        $template = preg_replace("/<% gallery_width %>/", "$gallery_width", $template);
        $template = preg_replace("/<% version %>/", "$version", $template);
        $template = preg_replace("/<% rsslink %>/", "$rsslink", $template);
        echo "$template";
    }
}
//-----------------------
//Debug stuff
//-----------------------
/*   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $endtime = $mtime;
   $totaltime = ($endtime - $starttime);
   echo "This page was created in ".$totaltime." seconds";
*/
?>
