<?php
	/* 	--------------------------------------
		Updater script
		--------------------------------------
		This script will update the swarm directories for those these two files:
		- swarming.js.php
		- swarming_container.js.php
	
		The script works by taking a template copy from the templates/ directory,
		and searching and replacing each file with the following parameters:
		- ###SWARMID###
		- ###SWARMNAME###
	*/
	// A. Warn the user
	echo "\n Swarm Updater v.1 ";
	echo "\n Running this script will change files.";
	echo "\n Did you backup the swarm directories? (n/y)";
	$stdin = fopen('php://stdin', 'r');
	$input = fgets($stdin,2);
	if ($input != "y") {
		exit;
	}
	
	$run_mode = false; //for windows else linux
	if($run_mode) {
		$m_server   = 'localhost';
		$m_user     = 'root';
		$m_pwd      = 'password';
		$m_database = 'eyebees';
	    	$swarmDirectory = "D:\Projects\www.eyebees.com\web\swarms\\";
	} else {
	    	$m_server   = 'localhost';
		$m_user     = 'dbadmin';
		$m_pwd      = 'xs3swarm';
		$m_database = 'eyebees';
	    	$swarmDirectory ="/data/www/www.eyebees.com/web/swarms";
	    	//$swarmDirectory = "/data/www/test.eyebees.com/web/swarm/swarm/";
    	}
	$link = mysql_connect($m_server, $m_user, $m_pwd);
	if (!$link) {
	    die('Could not connect: ' . mysql_ERROR());
	}
	$db_selected = mysql_select_db('eyebees', $link);
	if (!$db_selected) {
	    die ('Can\'t use eyebees database : ' . mysql_error());
	}
// C. Recursively go through the directory with swarms to get their names
    		
    	$stack = array();
 	$err_count = array();
    	$updated_count = array();
    	$swarmtype_count = array();
  	// The directory that contains all swarm names
   // 	$swarmDirectory = "D:\source\swarm\swarms\\";

    	$dh = opendir($swarmDirectory);		
    	// Scan the directory and update each swarm that we find
    	while (($file = readdir($dh)) !== false) 
    	{	
    		if ("dir" == filetype($swarmDirectory . $file)) 
    		{		// OK, we found a directory
    			if ("." != $file && ".." != $file) 
    			{				
    				// This is a swarm, update it
    				UpdateSwarm($swarmDirectory, $file);
    				array_push($stack,$file);
    				
    			}
    		}
    	}
        echo "\n UPDATER SUMMARY";
   	echo "\n  Total number of directories :" .sizeof($stack);
 	echo "\n  Total number of directories updated :" .sizeof($updated_count);
 	echo "\n  Total number of ERRORs :" .sizeof($err_count);
   	echo "\n  Total number of static type  :".GetSwarmTypeTotal('static');
   	echo "\n  Total number of dynamic type :".GetSwarmTypeTotal('move');
        closedir($dh);
	mysql_close($link);
   	exit;		// We're done!
   	/*	----------------------------------
   		GetSwarmTypeTotal($type)
   		----------------------------------
   		Get total number of swarm type 
   		$type = can be either move or static
   		@return total number of swarm 
   	*/
   	function GetSwarmTypeTotal($type)
   	{
   	    global $swarmtype_count;
   	    $static_type = array();
   	    $move_type = array();
   	    if(is_array($swarmtype_count)) {
   	       foreach($swarmtype_count as $i) {
		   if($i == 'static') {
   	           	array_push($static_type,$i);
   	           } else if($i =='move') {
   	                array_push($move_type,$i);
   	           }
   	       }
   	    }
   	    switch($type)
   	    {
   	    	case 'static' : return sizeof($static_type); break;
   	    	case 'move'   : return sizeof($move_type); break;
   	    }
	}		
   
   	/*	--------------------------------------
   		UpdateSwarm($swarmdirectory, $swarmname)
   		--------------------------------------
   		Update a specific swarm
   		
   		$swarmdirectory = the main swarm directory
   		$swarmname = the name of the url_code and also the name of the directory
   	*/
   	
	function UpdateSwarm($swarmDirectory, $swarmName) 
	{
		//echo "Updating " . $swarmName . "\n";
		
		// Check if this swarm exists, and get its ID
		// If the swarm does not exist the ID will be 0
		global $err_count,$updated_count,$swarmtype_count;
		$swarmID = GetSwarmID($swarmName);
		if (0 == $swarmID) {
			echo "ERROR " . $swarmName . " - does not exist\n";
			array_push($err_count,$swarmName);
			return;	// Do not continue, the swarm does not exist
		} 
		
		// Hooray, the swarm exists! Let's go ...
		
		//Get Title Bar Name
		$swarmTitleBarName = GetSwarmTitleBarName($swarmID);
		/*
		if (false == $swarmTitleBarName) {
			
			$swarmTitleBarName = $swarmName;
                }			
  		*/
		//Get Swarm Settings
		$swarmSettings = GetSwarmSettings($swarmDirectory, $swarmID, $swarmName);
	        if (false == $swarmSettings) {
		   echo "ERROR " . $swarmName . " - cannot retrieve swarm settings\n";
		   array_push($err_count,$swarmName);
		   return;   
  		}
		  // get the swarm type
		$swarmType = $swarmSettings['position_type'];
	
		if ('static' != $swarmType && 'move' != $swarmType) {
		   echo "ERROR " . $swarmName . " - unknown swarm type: $smarmType\n";
		   array_push($err_count,$swarmType);
		   return;   
 	        }
		echo "Updating " .$swarmName ." ID: ". $swarmID ." TYPE: ".$swarmType."\n";
		// Get the swarm type, if we don't know the type then skip this swarm
		$result = false;
		if ('move' == $swarmType) {
			$result = UpdateSwarmDynamic($swarmDirectory, $swarmID, $swarmName,$swarmTitleBarName);
			array_push($swarmtype_count,$swarmType);
		} elseif ('static' == $swarmType) {
			$result = UpdateSwarmStatic($swarmDirectory, $swarmID, $swarmName,$swarmTitleBarName);
			array_push($swarmtype_count,$swarmType);
		}
		if (false == $result) {
			echo "ERROR " . $swarmName . " - is not updated\n";
			array_push($err_count,$result);
		} else {
			echo "OK " . $swarmName . " - has been  updated\n";
			array_push($updated_count,$result);
		}
	}	
	/*	--------------------------------------
	   		GetSwarmTitleBarName($swarmID)
	   	--------------------------------------
	   	Gets the Title Bar Name of a swarm based on its id
	   	Returns false if the Title Bar Name does not exist
   	*/
	function GetSwarmTitleBarName($swarmID)
	{
		$sql ="SELECT `swarm_id`,`name` FROM `swarm`
		       WHERE `swarm_id` = '$swarmID' LIMIT 0 , 30";
		$result = mysql_query($sql) or die(mysql_ERROR());
		$num_rows = mysql_num_rows($result);
		if($num_rows > 0 ) {
		  while($row = mysql_fetch_object($result))
		  {
		      
		       return $row->name;
		  } 	
		}
	
	}
	/*	--------------------------------------
   		GetSwarmID($swarmname)
   		--------------------------------------
   		Gets the ID of a swarm based on its name
   		Returns 0 if the swarm does not exist
   	*/
   	function GetSwarmID($swarmname) 
   	{
		$sql ="SELECT `swarm_id`,`url_code`
		       FROM `swarm`
		       WHERE `url_code` = '$swarmname'
		       LIMIT 0 , 30";
		$result = mysql_query($sql) or die(mysql_ERROR());
		$num_rows = mysql_num_rows($result);
		if($num_rows > 0 ) {
		   while($row = mysql_fetch_object($result))
		   {
		   	return $row->swarm_id;
		   } 	
		}
		else {
		   return 0;
  	  	}
	}	
	/*	--------------------------------------
   		GetSwarmSettings($swarmDirectory, $swarmID, $swarmName)
   		--------------------------------------
   		Gets the swarm settings of a swarm based on the following parameter $swarmDirectory, $swarmID, $swarmName
   		Return an array of swarm settings else 
   		Returns 0 if the swarm does not exist
   	*/	
	function GetSwarmSettings($swarmDirectory, $swarmID, $swarmName)
	{
		$sql = "SELECT * FROM `swarm_settings_version`
			WHERE `version_id` =1 AND `swarm_id` ='$swarmID'";
		$result = mysql_query($sql) or die(mysql_ERROR());
		$num_rows = mysql_num_rows($result);
		if($num_rows) 
		{
		   $row = mysql_fetch_object($result);
		   $swarm_settings = $row->settings;
		   $swarm_array = unserialize($swarm_settings);
		   if(is_array($swarm_array)) {
		   	return $swarm_array;
		   } else {
		   	return false;
		   }
		}
		else {
		       return false;
		}
	}				
	
	/*	--------------------------------------
   		GetSwarmType($swarmID, $swarmname)
   		--------------------------------------
   		Returns the following types of swarms:
   		- static
   		- dynamic
   		- ERROR
   	*/
   	function GetSwarmType($swarmID, $swarmname) 
   	{
		$sql = "SELECT * FROM `swarm_settings_version`
			WHERE `version_id` =1 AND `swarm_id` ='$swarmID'";
		$result = mysql_query($sql) or die(mysql_ERROR());
		$num_rows = mysql_num_rows($result);
		if($num_rows) 
		{
		  $row = mysql_fetch_object($result);
		  $swarm_settings = $row->settings;
		  $swarm_arr = unserialize($swarm_settings);
		  if($swarm_arr[position_type] =='move') {
		     return 'dynamic';
		  } else if($swarm_arr[position_type] =='static') {
		     return 'static';
		  }
		  else {
		     return 0;
		  }
		  //return $swarm_arr[position_type];
		}
		else {
		  return 0;
		}
	}
	/*	--------------------------------------
   		UpdateSwarmDynamic($swarmDirectory, $swarmID, $swarmName)
   		--------------------------------------
   		Updates a dynamic swarm
   	*/
	
	function UpdateSwarmDynamic($swarmDirectory, $swarmID, $swarmName,$swarmTitleBarName) 
	{
		// Load the remplate files
		//C:\eyebees_updater\templates
		//$swarmingJS = file_get_contents('./templates/dynamic/swarming.js.php');
		$swarmingJS = file_get_contents('C:\eyebees_updater\templates\dynamic\swarming.js.php');
		$swarmingContainerJS = file_get_contents('C:\eyebees_updater\templates\dynamic\swarming_container.js.php');
				
		// Search and replace variables in the template files
		//str_replace(search_this,replace_with,search_here);
		$swarmingJS = str_replace('###SWARMID###', $swarmName, $swarmingJS);
		//$swarmingJS = str_replace('###SWARMNAME###', $swarmName, $swarmingJS);
		$swarmingContainerJS = str_replace('###SWARMID###', $swarmID, $swarmingContainerJS);
		$swarmingContainerJS = str_replace('###SWARMNAME###', $swarmTitleBarName, $swarmingContainerJS);
		
	//	return true;
		
		// Backup the files and write the new files
		$file1 = $swarmDirectory . $swarmName . "\swarming.js.php";
		if (!FileBackup($file1)) {
			return false;
		}
		//file_put_contents(write_here,the_data)
		$result1 = file_put_contents($file1, $swarmingJS);
		
		$file2 = $swarmDirectory . $swarmName . "\swarming_container.js.php";
		if (!FileBackup($file2)) {
			return false;
		}
		$result2 = file_put_contents($file2, $swarmingContainerJS);

		if (false == $result1 || false == $result2) {		
		// There was an ERROR writing the files
			return false;				
		} else {
			return true;
		}
		
	}
	/*	--------------------------------------
   		UpdateSwarmStatic($swarmDirectory, $swarmID, $swarmName)
   		--------------------------------------
   		Updates a static swarm
   	*/
	
	function UpdateSwarmStatic($swarmDirectory, $swarmID, $swarmName) 
	{
		
		// Load the remplate files
		$swarmingJS = file_get_contents('C:\eyebees_updater\templates\static\swarming.js.php');
		$swarmingContainerJS = file_get_contents('C:\eyebees_updater\templates\static\swarming_container.js.php');
		
		
		// Search and replace variables in the template files
		//$swarmingJS = str_replace('###SWARMID###', $swarmID, $swarmingJS);
		$swarmingJS = str_replace('###SWARMID###', $swarmName, $swarmingJS);
		//$swarmingJS = str_replace('###SWARMNAME###', $swarmName, $swarmingJS);
		$swarmingContainerJS = str_replace('###SWARMID###', $swarmID, $swarmingContainerJS);
		$swarmingContainerJS = str_replace('###SWARMNAME###', $swarmTitleBarName, $swarmingContainerJS);
		
		//return true;
		
		// Backup the files and write the new files
		$file1 = $swarmDirectory . $swarmName . "\swarming.js.php";
		if (!FileBackup($file1)) {
			return false;
		}
		$result1 = file_put_contents($file1, $swarmingJS);
		
		$file2 = $swarmDirectory . $swarmName . "\swarming_container.js.php";
		if (!FileBackup($file2)) {
			return false;
		}
		$result2 = file_put_contents($file2, $swarmingContainerJS);

		if (false == $result1 || false == $result2) {		// There was an ERROR writing the files
			return false;				
		} else {
			return true;
		}
	}
	/*	--------------------------------------
   		FileBackup($filename)
   		--------------------------------------
   		This function backs up a file by 
   		copying it to a new file with the 
   		unix timestamp attached to the 
   		filename
   		
   		Returns true if the backup was 
   		successful, false otherwise
   	*/
	function FileBackup($filename)
	{
		// Add linux timestamp to filename for the backup
		$backupFilename = $filename . "." . time();
		if (!$fileContents = file_get_contents($filename)) {
			return false;
		}
		if (!file_put_contents($backupFilename, $fileContents)) {
			return false;
		}
		return true;
	}
	function ShowProgressBar()
	{
		$c = "|";
		while (1)
		{
		  if ($c == "|") $c = "-";
		  else if ($c == "-") $c = "/";
		  else if ($c == "/") $c = "\\";
		  else if ($c == "\\") $c = "|";
		
		  print "$c\r";
		  sleep(1);
		
		}
	}
?>
