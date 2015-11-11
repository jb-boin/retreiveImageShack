<?php
//Script that downloads the Imageshack images of a page that are having the old URL format and put them in a Zip file

// Requires the ZipStream-PHP library from https://github.com/maennchen/ZipStream-PHP and PHP5.3+

// Only the URLs matching this regexp can be used on the script to avoid anyone using the script to download images from any websites
$urlFilter = "/^https?:\/\/forums.audipassion.com\//";

// Adds to the images filename a prefix with its position in the page (eg. the 3rd image on the page will have the name 3-XXX.jpg)
$prefixImageIndex = false;


// ZipStream is used to directly create in memory the zip file without having to use a temporary file
require_once("ZipStream-PHP/src/ZipStream.php");

$originPage = $originPageErr = "";

if(!empty($_POST["originPage"])) {
	// An URL has been posted on the form, it needs to be cleaned and validated
	$originPage = htmlspecialchars(stripslashes(trim($_POST["originPage"])));

     // Check if the URL address syntax is valid (this regular expression also allows dashes in the URL)
//     if (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $originPage)) {
	if(!filter_var($originPage, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//", $originPage)) {
		// Only RFC valid URLs and using the HTTP/HTTPS protocol are accepted (no FTP or local files for example)
		$originPageErr = "Invalid URL";
	}
	if(!empty($urlFilter) && !preg_match($urlFilter, $originPage)) {
		$originPageErr = "The URL does not match the specified filter";
	}
}

// HTML for the form
echo "<!DOCTYPE HTML>
<html>
<body>
	<h2>Imageshack images retriever</h2>
	<br />";

if(!empty($urlFilter)) echo "URL Filter : $urlFilter<br /><br/>";

echo "	<form method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
		Source page: <input type='text' name='originPage' value='$originPage'>
		<input type='submit' name='submit' value='Submit'>
		<span style='color: red;'>$originPageErr</span>
	</form>";

if(!empty($originPage) && empty($originPageErr)) {
	// A valid source page URL has been provided

	// Retreival of the original page
	$html = file_get_contents($originPage);

	// domDocument is used to easily parse the HTML code to retreive only <img> tags and extract their "src" value
	$dom = new domDocument;

	// The @ is used to avoid warnings due to non standard HTML code
	@$dom->loadHTML($html);
	$dom->preserveWhiteSpace = false;

	// Retrieval of the <img> tags
	$images = $dom->getElementsByTagName('img');

	unset($dom);

	$i = 1;

	foreach ($images as $image) {
		// Loop on each <img> tags of the page

		if(preg_match("/^https?:\/\/img[0-9]+\.imageshack\.us\/img([0-9]+)/", $image->getAttribute("src"))) {
			// Only the images using the old Imageshack URLs are retrieved

			// Creation of a new zipstream object if it hasnt been created yet
			if(!isset($zip)) $zip = new ZipStream\ZipStream("imageShackRetriever_".time());

			// Modification of the image URL from the olf format to the new one
			$imgUrl = preg_replace("/^https?:\/\/img[0-9]+\.imageshack\.us\/img([0-9]+)\/[0-9]+\/(.+)$/", 'http://imageshack.com/download/\1/\2', $image->getAttribute("src"));

			// Replacement of all special characters on the filename by an underscore
			$fileName = preg_replace("/[^a-z0-9\._-]/", "_", strtolower(basename($imgUrl)));

			// If $prefixImageIndex is True, the image filename will have its position in the source page as a prefix
			if($prefixImageIndex) $fileName = $i."-".$fileName;

			// Retrieval of the image
			$img = file_get_contents($imgUrl);

			// Adding the image to the zip file
			$zip->addFile($fileName, $img);

			unset($img);

			$i++;
		}
	}

	if($i == 1) {
		//
		echo "<span style='color: red;'>No Imageshack images have been found on the page.</span>";
	} elseif(isset($zip)) {
		// At least one image was found and zipped, once $zip-finish() is executed the script ends
		$zip->finish();
	}
}

echo "
</body>
</html>";

?>
