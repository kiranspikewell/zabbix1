<?php 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	period
#	from

	$sizeX=200;
	$sizeY=15;

//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	check_authorisation();

	$im = imagecreate($sizeX,$sizeY); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 
	$white=ImageColorAllocate($im,255,255,255); 

	ImageFilledRectangle($im,0,0,$sizeX,$sizeY,$darkgreen);
	ImageRectangle($im,0,0,$sizeX-1,$sizeY-1,$black);

	$now=time(NULL);
	$period_start=$now-7*24*3600;
	$period_end=$now;
	$service=get_service_by_serviceid($HTTP_GET_VARS["serviceid"]);
	$stat=calculate_service_availability($HTTP_GET_VARS["serviceid"],$period_start,$period_end);
		
	$true=$stat["true"];
	$false=$stat["false"];

//	echo $true," ",$false;

	ImageFilledRectangle($im,$sizeX-$sizeX*$true/100-1,1,$sizeX-1,$sizeY-1,$darkred);
	ImageLine($im,$sizeX*$service["goodsla"]/100,1,$sizeX*$service["goodsla"]/100,$sizeY-1,$yellow);

	$s=sprintf("%2.2f%%",$false);
	ImageString($im, 2,1,1, $s , $white);
	$s=sprintf("%2.2f%%", $true);
	ImageString($im, 2,$sizeX-45,1, $s , $white);
	ImagePng($im); 
	ImageDestroy($im); 
?>
