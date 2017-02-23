<?php
$version=5.3;

function encodeURL($url)
	{
	$url=str_replace(array("&","#","'"),array("%26","%23","%27"),$url);
	return $url;
	}

$fileTypes=array(
	"7z"=>"Compressed archive",
	"8xg"=>"TI-83/84+ group file",
	"avi"=>"Video file",
	"bat"=>"Batch file",
	"bmp"=>"Bitmap image",
	"btm"=>"Batch file",
	"cmd"=>"Batch file",
	"css"=>"Stylesheet",
	"doc"=>"Word document",
	"docx"=>"Word document",
	"gif"=>"8-bit palette image",
	"htm"=>"Hypertext markup language file",
	"html"=>"Hypertext markup language file",
	"ico"=>"Icon file",
	"ini"=>"Configuration file",
	"jpeg"=>"Compressed image",
	"jpg"=>"Compressed image",
	"js"=>"Javascript sourcecode file",
	"mp3"=>"MP3-audio file",
	"ogg"=>"Ogg Vorbis audio file",
	"php"=>"PHP hypertext preprocessor file",
	"png"=>"Image file",
	"ppt"=>"Powerpoint presentation",
	"pptm"=>"Powerpoint presentation",
	"pptx"=>"Powerpoint presentation",
	"pps"=>"Powerpoint presentation",
	"ppsx"=>"Powerpoint presentation",
	"psd"=>"Adobe Photoshop project",
	"psp"=>"Corel Paint Shop Pro project",
	"pspimage"=>"Corel Paint Shop Pro project",
	"rar"=>"Compressed archive",
	"txt"=>"Text file",
	"wma"=>"Windows media audio file",
	"wmv"=>"Windows media video file",
	"zip"=>"Compressed archive",
	"zipx"=>"Compressed archive",
	);

$settingsFile="browser/config.yml";
$webRoot=$_SERVER["DOCUMENT_ROOT"];
$webDir = "/browser/";

//working directory
$workingDirectory=$webRoot;
if (isset($_GET["d"]) and $_GET["d"])
	{
	$workingDirectory.=$_GET["d"]."/";

	$workingDirectory=str_replace("\\","/",$workingDirectory);
    $workingDirectory=str_replace("//","/",$workingDirectory);
	$workingDirectory=str_replace(array("/..","/."),"",$workingDirectory);
	}
$relativeWorkingDirectory=str_replace($webRoot,"",$workingDirectory);
$settingsDirectory=substr($relativeWorkingDirectory,0,-1);
if (!$settingsDirectory)
	$settingsDirectory="root";

//SETTINGS START
$isBrowsable=false;
$settings["displayWebpages"]=false;
$settings["displayDirectories"]=false;
$settings["displayFiles"]=false;
$settings["filtermode"]="blacklist";
$settings["filterWebpages"]=array();
$settings["filterDirectories"]=array();
$settings["filterFiles"]=array();
$settings["description"]=false;

//read settings into array
$settingsArray=yaml_parse(file_get_contents($settingsFile,true));

$iSettingsDirectory="root";
$slashPos=0;
$parented=false;
//check if settings for PARENT directories with useforchildren exist and load their settings in
while ($iSettingsDirectory!=$settingsDirectory)
	{
	//load settings if useforchildren isset and true
	if (isset($settingsArray[$iSettingsDirectory]["useforchildren"]) && $settingsArray[$iSettingsDirectory]["useforchildren"])
		{
		$isBrowsable=true;
		isset($settingsArray[$iSettingsDirectory]["display"]["webpages"]) && $settings["displayWebpages"]=$settingsArray[$iSettingsDirectory]["display"]["webpages"];
		isset($settingsArray[$iSettingsDirectory]["display"]["directories"]) && $settings["displayDirectories"]=$settingsArray[$iSettingsDirectory]["display"]["directories"];
		isset($settingsArray[$iSettingsDirectory]["display"]["files"]) && $settings["displayFiles"]=$settingsArray[$iSettingsDirectory]["display"]["files"];
		isset($settingsArray[$iSettingsDirectory]["filtermode"]) && $settings["filtermode"]=$settingsArray[$iSettingsDirectory]["filtermode"];
		isset($settingsArray[$iSettingsDirectory]["filter"]["webpages"]) && $settings["filterWebpages"]=$settingsArray[$iSettingsDirectory]["filter"]["webpages"];
		isset($settingsArray[$iSettingsDirectory]["filter"]["directories"]) && $settings["filterDirectories"]=$settingsArray[$iSettingsDirectory]["filter"]["directories"];
		isset($settingsArray[$iSettingsDirectory]["filter"]["files"]) && $settings["filterFiles"]=$settingsArray[$iSettingsDirectory]["filter"]["files"];

		$parented=true;
		}

	$slashPos=strpos($settingsDirectory."/","/",$slashPos+1);
	$iSettingsDirectory=substr($settingsDirectory,0,$slashPos);
	}

//check if settings for THIS directory are present and load them
if (isset($settingsArray[$settingsDirectory]))
	{
	$isBrowsable=true;
	isset($settingsArray[$settingsDirectory]["display"]["webpages"]) && $settings["displayWebpages"]=$settingsArray[$settingsDirectory]["display"]["webpages"];
	isset($settingsArray[$settingsDirectory]["display"]["directories"]) && $settings["displayDirectories"]=$settingsArray[$settingsDirectory]["display"]["directories"];
	isset($settingsArray[$settingsDirectory]["display"]["files"]) && $settings["displayFiles"]=$settingsArray[$settingsDirectory]["display"]["files"];
	isset($settingsArray[$settingsDirectory]["filtermode"]) && $settings["filtermode"]=$settingsArray[$settingsDirectory]["filtermode"];
	isset($settingsArray[$settingsDirectory]["filter"]["webpages"]) && $settings["filterWebpages"]=$settingsArray[$settingsDirectory]["filter"]["webpages"];
	isset($settingsArray[$settingsDirectory]["filter"]["directories"]) && $settings["filterDirectories"]=$settingsArray[$settingsDirectory]["filter"]["directories"];
	isset($settingsArray[$settingsDirectory]["filter"]["files"]) && $settings["filterFiles"]=$settingsArray[$settingsDirectory]["filter"]["files"];
	isset($settingsArray[$settingsDirectory]["description"]) && $settings["description"]=$settingsArray[$settingsDirectory]["description"];

	if (isset($settingsArray[$settingsDirectory]["useforchildren"]) && $settingsArray[$settingsDirectory]["useforchildren"])
		$parented=true;
	}
//SETTINGS END

//LOAD LISTS OF WEBPAGES/DIRECTORIES/FILES
$error=false;
if (file_exists($workingDirectory))
	{
	if ($isBrowsable)
		{
		//create arrays containing all the webpages/dirs/files
		$webpages=array();
		$directories=array();
		$files=array();

		//webpages & directories
		$allDirectories=glob($workingDirectory."*",GLOB_ONLYDIR);
		foreach($allDirectories as $allDirectory)
			{
			//remove workingdirectory from list to only have names remain
			$allDirectory=substr($allDirectory,strlen($workingDirectory));

			//check for an index file, filter & store webpages
			if ($settings["displayWebpages"])
				{
				//check for index file
				if (file_exists($workingDirectory.$allDirectory."/index.php") or file_exists($workingDirectory.$allDirectory."/index.htm") or file_exists($workingDirectory.$allDirectory."/index.html"))
					{
					//filter & store webpages
					$inList=in_array($allDirectory,$settings["filterWebpages"],true);
					if (($settings["filtermode"]=="blacklist" and !$inList) or ($settings["filtermode"]=="whitelist" and $inList))
						$webpages[]=$allDirectory;
					}
				}

			//check if directory is browsable, filter & store directories
			if ($settings["displayDirectories"])
				{
				if ($parented or ($settingsDirectory=="root" and isset($settingsArray[$allDirectory])) or ($settingsDirectory!="root" and isset($settingsArray[$settingsDirectory."/".$allDirectory])))
					{
					$inList=in_array($allDirectory,$settings["filterDirectories"],1);
					if (($settings["filtermode"]=="blacklist" and !$inList) or ($settings["filtermode"]=="whitelist" and $inList))
						$directories[]=$allDirectory;
					}
				}
			}

		//files
		if ($settings["displayFiles"])
			{
			$allFiles=glob($workingDirectory."*.*");
			$i=0;
			$totalFileCount = 0;
			$totalFileSize = 0;
			foreach($allFiles as $allFile)
				{
				$allFile=substr($allFile,strlen($workingDirectory));
				$inList=in_array($allFile,$settings["filterFiles"],1);
				if ((($settings["filtermode"]=="blacklist" and !$inList) or ($settings["filtermode"]=="whitelist" and $inList)) and !in_array($allFile,$directories))
					{
					$files[$i][0]=$allFile;

					$fileSize=filesize($workingDirectory.$allFile);
					if ($fileSize>1073741824)
						$files[$i][1]=floor($fileSize / 1073741824 * 10) / 10 ." GiB";
					elseif ($fileSize>1048576)
						$files[$i][1]=floor($fileSize/1048576)." MiB";
					elseif ($fileSize>1024)
						$files[$i][1]=floor($fileSize/1024)." KiB";
					else
						$files[$i][1]=$fileSize." B";

					$fileExtension=substr($allFile,strrpos($allFile,".")+1);
					if (isset($fileTypes[$fileExtension]))
						$files[$i++][2]=$fileTypes[$fileExtension];
					else
						$files[$i++][2]="Unknown";

					$totalFileCount++;
					$totalFileSize += $fileSize;
					}
				}

			if ($totalFileSize>1073741824)
				$totalFileSize=floor($totalFileSize / 1073741824 * 10) / 10 ." GiB";
			elseif ($totalFileSize>1048576)
				$totalFileSize=floor($totalFileSize/1048576)." MiB";
			elseif ($totalFileSize>1024)
				$totalFileSize=floor($totalFileSize/1024)." KiB";
			else
				$totalFileSize=$totalFileSize." B";
			}

		if (!$webpages and !$directories and !$files)
			{
			$error="No files to display.";
			}
		}
	else
		$error="You don't have access to view this directory.";
	}
else
	$error="The specified directory does not exist.";

echo "
<!doctype html>
<html>
	<head>
		<meta charset='utf-8' />
		<title>$settingsDirectory - Viller's filebrowser v$version</title>
		<link rel='stylesheet' href='{$webDir}css/default.css' type='text/css' />
		<link rel='icon' type='image/x-icon' href='{$webDir}favicon.ico' />
	</head>
	<body>
		<h1>
			Viller's filebrowser v$version<br />
			$settingsDirectory
		</h1>";

	if ($error)
		echo "<table class='error'><tr><th>$error</td></th></table>";
	else
		{
		//back button
		if ($relativeWorkingDirectory!="")
			{
			$parentDirectory=substr($relativeWorkingDirectory,0,strrpos($relativeWorkingDirectory,"/",-2));
			echo "<a href='{$webDir}{$parentDirectory}' id='back'><img src='{$webDir}css/back.png' alt='Back to parent directory' title='Back to parent directory' /></a>";
			}
		else
			echo "<a href='/' id='back'><img src='{$webDir}css/back.png' alt='Back to homepage' title='Back to homepage' /></a>";

		//description
		if ($settings["description"])
			echo "<table><tr><th>Description</th></tr><tr><td style='white-space:normal'>".$settings["description"]."</td></tr></table>";

		//directories
		if ($directories)
			{
			echo "<table><tr><th>Directories</th></tr>";
			foreach($directories as $directory)
				{
				echo "<tr class='file' onclick='window.location.href=\"{$webDir}".encodeURL($relativeWorkingDirectory.$directory)."\"'><td>$directory</td></tr>";
				}
			echo "</table>";
			}

		//webpages
		if ($webpages)
			{
			echo "<table><tr><th>Webpages</th></tr>";
			foreach($webpages as $webpage)
				{
				echo "<tr class='file' onclick='window.location.href=\"/".encodeURL($relativeWorkingDirectory.$webpage)."\"'><td>$webpage</td></tr>";
				}
			echo "</table>";
			}

		//files
		if ($files)
			{
			echo "<table><tr><th colspan='3'>Files</th></tr>";
			echo "<tr><td colspan='3'>{$totalFileSize} in {$totalFileCount} files</td></tr>";
			echo "<tr><th>Name</th><th>Size</th><th>Type</th></tr>";

			foreach($files as $file)
				{
				$fileName=$file[0];
				$fileSize=$file[1];
				$fileType=$file[2];
				echo "<tr class='file' onclick='window.location.href=\"/".encodeURL($relativeWorkingDirectory.$fileName)."\"'><td>$fileName</td><td>$fileSize</td><td>$fileType</td></tr>";
				}
			echo "</table>";
			}
		}


echo "
	</body>
</html>";
?>
