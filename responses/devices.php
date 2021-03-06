<?php
	/* 		
		*
		* Vulkan hardware capability database server implementation
		*	
		* Copyright (C) by Sascha Willems (www.saschawillems.de)
		*	
		* This code is free software, you can redistribute it and/or
		* modify it under the terms of the GNU Affero General Public
		* License version 3 as published by the Free Software Foundation.
		*	
		* Please review the following information to ensure the GNU Lesser
		* General Public License version 3 requirements will be met:
		* http://www.gnu.org/licenses/agpl-3.0.de.html
		*	
		* The code is distributed WITHOUT ANY WARRANTY; without even the
		* implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
		* PURPOSE.  See the GNU AGPL 3.0 for more details.		
		*
	*/

    include '../dbconfig.php';
    include '../functions.php';

    DB::connect();

    $data = array();
    $params = array();    
    $ostype = null;

    if (isset($_REQUEST["platform"])) {
        $ostype = ostype($_REQUEST["platform"]);
    }
    
    // Ordering
    $orderByColumn = '';
    $orderByDir = '';
    if (isset($_REQUEST['order'])) {
        $orderByColumn = $_REQUEST['order'][0]['column'];
        $orderByDir = $_REQUEST['order'][0]['dir'];
        if (strcasecmp($orderByColumn, 'driver') == 0) {
            $orderByColumn = 'driverversionraw';
        }
    }

    // Paging
    $paging = '';
    if (isset($_REQUEST['start'] ) && $_REQUEST['length'] != '-1') {
        $paging = "LIMIT ".$_REQUEST["length"]. " OFFSET ".$_REQUEST["start"];
    }  

    // Filtering
    $searchColumns = ['device', 'api', 'driverversion', 'reportversion', 'reportcount'];

    $minversion = false;
    if (isset($_REQUEST['minversion'])) {
        $minversion = true;
        $searchColumns = ['device', 'vendor', 'driverversion'];
    }

    // Per-column filtering
    $filters = array();
    for ($i = 0; $i < count($_REQUEST['columns']); $i++) {
        $column = $_REQUEST['columns'][$i];
        if (($column['searchable'] == 'true') && ($column['search']['value'] != '')) {
            if ($searchColumns[$i] == 'api') {
                $filters[] = 'VkVersion(api) like :filter_'.$i;               
            } else {
                $filters[] = $searchColumns[$i].' like :filter_'.$i;
            }
            $params['filter_'.$i] = '%'.$column['search']['value'].'%';
        }
    }
    if (sizeof($filters) > 0) {
        $searchClause = 'having '.implode(' and ', $filters);
    }        

    $whereClause = '';
    $selectAddColumns = '';
    $negate = false;
	if (isset($_REQUEST['filter']['option'])) {
		if ($_REQUEST['filter']['option'] == 'not') {
			$negate = true;
		}
    }        
	// Filters
    // Extension
	if (isset($_REQUEST['filter']['extension'])) {
	    $extension = $_REQUEST['filter']['extension'];
        if ($extension != '') {
            if ($negate) {
                $whereClause = "where r.devicename not in (select r.devicename from reports r join deviceextensions de on de.reportid = r.id join extensions ext on de.extensionid = ext.id where ext.name = :filter_extension)";
            } else {               
                $whereClause = "where r.id in (select distinct(reportid) from deviceextensions de join extensions ext on de.extensionid = ext.id where ext.name = :filter_extension)";
            }
            $params['filter_extension'] = $extension;
        }
	}
    // Feature
	if (isset($_REQUEST['filter']['feature'])) {
	    $feature = $_REQUEST['filter']['feature'];
        if ($feature != '') {
            $whereClause = "where r.devicename ".($negate ? "not" : "")." in (select r.devicename from reports r join devicefeatures df on df.reportid = r.id where df.$feature = 1)";
        }    
    }
    // Submitter
    if (isset($_REQUEST['filter']['submitter'])) {
	    $submitter = $_REQUEST['filter']['submitter'];
        if ($submitter != '') {
            $whereClause = "where r.submitter = :filter_submitter";
            $params['filter_submitter'] = $submitter;            
        }
	}
	// Format support
	$linearformatfeature = $_REQUEST['filter']['linearformat'];
	$optimalformatfeature = $_REQUEST['filter']['optimalformat'];
    $bufferformatfeature = $_REQUEST['filter']['bufferformat'];	
    if ($linearformatfeature != '' || $optimalformatfeature != '' || $bufferformatfeature != '') {
        $formatColumn = null;
        if ($linearformatfeature != '') {
            $formatColumn = 'lineartilingfeatures';
            $params['filter_formatfeature'] = $linearformatfeature;
        }	
        if ($optimalformatfeature != '') {
            $formatColumn = 'optimaltilingfeatures';
            $params['filter_formatfeature'] = $optimalformatfeature;
        }	
        if ($bufferformatfeature != '') {
            $formatColumn = 'bufferfeatures';
            $params['filter_formatfeature'] = $bufferformatfeature;
        }    
        if ($formatColumn) {
            $whereClause = "
                where ifnull(r.displayname, r.devicename) ".($negate ? "not" : "")." in
                (
                    select ifnull(r.displayname, r.devicename)
                    from reports r
                    join deviceformats df on df.reportid = r.id
                    join VkFormat vf on vf.value = df.formatid where 
                    vf.name = :filter_formatfeature and df.$formatColumn > 0
                )";
        }
    }
	// Surface format	
	$surfaceformat = $_REQUEST['filter']['surfaceformat'];
	if ($surfaceformat != '') {        
        $whereClause = 
            "where ifnull(r.displayname, r.devicename) ".($negate ? "not" : "")." in
            (
                SELECT ifnull(r.displayname, r.devicename)
                from reports r
                join devicesurfaceformats dsf on dsf.reportid = r.id	
                join VkFormat f on dsf.format = f.value 
                where f.name = :filter_surfaceformat ".($ostype ? " and r.ostype = :ostype" : "")."
            )
            and r.version >= '1.2'";
        $params['filter_surfaceformat'] = $surfaceformat;        
        if ($ostype) {
            $params['ostype'] = $ostype;
        }
	}
	// Surface present mode	
	$surfacepresentmode = $_REQUEST['filter']['surfacepresentmode'];
	if ($surfacepresentmode != '') {
        $whereClause = 
            "where ifnull(r.displayname, r.devicename) ".($negate ? "not" : "")." in
            (
                select ifnull(r.displayname, r.devicename)
                from reports r
                join devicesurfacemodes dsm on dsm.reportid = r.id	
                join VkPresentMode m on dsm.presentmode = m.value 
                where m.name = :filter_surfacepresentmode ".($ostype ? " and r.ostype = :ostype" : "")."
            )
            and r.version >= '1.2'";
        $params['filter_surfacepresentmode'] = $surfacepresentmode;       
        if ($ostype) {
            $params['ostype'] = $ostype;
        }
	}	    
	// Limit
	$limit = $_REQUEST['filter']['devicelimit'];
	if ($limit != '') {
		$selectAddColumns = ",(select dl.`".$limit."` from devicelimits dl where dl.reportid = r.id) as devicelimit";
		// Check if a limit requirement rule has to be applied (see Table 36. of the specs)
		$sql = "select feature from limitrequirements where limitname = :limit";  
		$reqs = DB::$connection->prepare($sql);
		$reqs->execute(array(":limit" => $limit));
		if ($reqs->rowCount() > 0) {
			$req = $reqs->fetch();
		    $whereClause = "where r.id in (select distinct(reportid) from devicefeatures df where df.".$req["feature"]." = 1)";
		}
	}    
    // Devicename
    if (isset($_REQUEST['filter']['devicename'])) {
	    $devicename = $_REQUEST['filter']['devicename'];
        if ($devicename != '') {
            $whereClause = "where r.devicename = :filter_devicename";
            $params['filter_devicename'] = $devicename;            
        }
	}    
    // Displayname (Android devices)
    if (isset($_REQUEST['filter']['displayname'])) {
	    $displayname = $_REQUEST['filter']['displayname'];
        if ($displayname != '') {
            $whereClause = "where r.displayname = :filter_displayname";
            $params['filter_displayname'] = $displayname;            
        }
    }
    
    $orderBy = "order by ".$orderByColumn." ".$orderByDir;

    // TODO: Change to ostype
    if (isset($_REQUEST["platform"])) {
        $platform = $_REQUEST["platform"];
        if ($platform !== "all") {
            if ($whereClause != '') {
                $whereClause .= ' and ';
            } else {
                $whereClause = ' where ';
            }
            switch($platform) {
                case 'windows':
                    $ostype = 0;
                    break;
                case 'linux':
                    $ostype = 1;
                    break;
                case 'android':
                    $ostype = 2;
                    break;
            }
            $whereClause .= "r.ostype = '".$ostype."'";
        }
    }

    if ($minversion) {
        $sql = 
            "SELECT 
                ifnull(r.displayname, dp.devicename) as device, 
                min(dp.apiversionraw) as api,
                min(dp.driverversion) as driverversion,
                min(dp.driverversionraw) as driverversionraw, 
                0 as reportversion,
                0 as reportcount,
                min(submissiondate) as submissiondate,
                VendorId(dp.vendorid) as vendor,
                dp.vendorid as vendorid,
                date(min(submissiondate)) as submissiondate,
                r.osname as osname
                from reports r
                join deviceproperties dp on r.id = dp.reportid
                $whereClause
                group by device
                ".$searchClause."
                ".$orderBy;
    } else {
        $sql = 
            "SELECT 
                r.id,
                ifnull(r.displayname, dp.devicename) as device, 
                max(dp.apiversionraw) as api,
                max(dp.driverversion) as driverversion,
                max(dp.driverversionraw) as driverversionraw, 
                count(distinct r.id) as reportcount,
                max(r.version) as reportversion,
                VendorId(dp.vendorid) as vendor,
                dp.vendorid as vendorid,
                max(r.submissiondate) as submissiondate,
                r.osname as osname
                from deviceproperties dp
                join reports r on r.id = dp.reportid
                $whereClause
                group by device
                ".$searchClause."
                ".$orderBy;          
    }

    $devices = DB::$connection->prepare($sql." ".$paging);
    $devices->execute($params);
    if ($devices->rowCount() > 0) { 
        foreach ($devices as $device) {
            $url = 'listreports.php?devicename='.$device["device"];
            if ($platform !== 'all') {
                $url .= '&platform='.$platform;
            }
            $data[] = array(
                'device' => '<a href="'.$url.'">'.$device["device"].'</a>',
                'api' => versionToString($device["api"]), 
                'driver' =>  getDriverVerson($device["driverversionraw"], "", $device["vendorid"], $device["osname"]), 
				'reportcount' => $device["reportcount"],
                'reportversion' => $device["reportversion"],
                'submissiondate' => $device["submissiondate"],
                'vendor' => $device["vendor"],
                'compare' => '<center><input type="checkbox" name="devices[]" value="'.$device["device"].'&os='.$platform.'"></center>'
            );
        }        
    }

    $filteredCount = 0;
    $stmnt = DB::$connection->prepare($sql);
    $stmnt->execute($params);
    $totalCount = $stmnt->rowCount(); 

    $filteredCount = $totalCount;
    if (($searchClause != '') or ($whereClause != ''))  {
        $stmnt = DB::$connection->prepare($sql);
        $stmnt->execute($params);
        $filteredCount = $stmnt->rowCount();     
    }

    $results = array(
        "draw" => isset($_REQUEST['draw']) ? intval( $_REQUEST['draw'] ) : 0,        
        "recordsTotal" => intval($totalCount),
        "recordsFiltered" => intval($filteredCount),
        "data" => $data);

    DB::disconnect();     

    echo json_encode($results);
?>