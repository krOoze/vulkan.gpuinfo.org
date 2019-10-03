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
	
	// Table header
	echo "<thead><tr><td class='caption'></td>";
	foreach ($reportids as $reportid) {
		echo "<td class='caption'>Report $reportid</td>";
	}
	echo "</tr></thead><tbody>";
	reportCompareDeviceColumns($deviceinfo_captions, $deviceinfo_data, sizeof($reportids));

	// Gather all extended properties for reports to compare
	$extended_properties = null;
	try {
		$stmnt = DB::$connection->prepare("SELECT distinct extension, name from deviceproperties2 where reportid in ($repids)");
		$stmnt->execute();
		$extended_properties = $stmnt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
	} catch (Exception $e) {
		die('Could not fetch extended properties!');
		DB::disconnect();
	}

	// Get extended properties for each selected report into an array 
	$extended_properties_reports = null;	
	foreach ($reportids as $reportid) {
		try {
			$stmnt = DB::$connection->prepare("SELECT extension, name, value from deviceproperties2 where reportid = :reportid");
			$stmnt->execute(['reportid' => $reportid]);
			$extended_properties_reports[] = $stmnt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			die("Could not fetch device extended properties for single report!");
		}	
	}
	
	// Generate table
	foreach ($extended_properties as $extension => $properties) {
		echo "<tr class='same'><td class='group' style='border-right:0px'>$extension</td>\n";
		foreach ($reportids as $repid) {
			echo "<td class='group' style='border-right:0px'></td>";
		}  
		echo "</tr>"; 
		// Feature support
		foreach ($properties as $feature) {
			echo "<tr class='$className'><td class='firstrow' style='padding-left:25px'>".$feature['name']."</td>\n";
			$index = 0;			
			foreach ($extended_properties_reports as $extended_properties_report) {
				$ext_present = array_key_exists($extension, $extended_properties_report);
				if ($ext_present) {
					$ext = $extended_properties_report[$extension];
					$value = null;
					foreach ($ext as $ext_f) {
						if ($ext_f['name'] == $feature['name']) {
							$value = $ext_f['value'];
						}
					}
					if (in_array($value, ["true", "false"])) {
						echo "<td><span class=".($value == "true" ? "supported" : "unsupported").">$value</span></td>";
					} else {
						echo "<td>$value</td>";
					}
				} else {
					echo "<td class='na'>n.a.</td>";
				}
				$index++;
			}
			echo "</tr>"; 
		}
	}	  
?>