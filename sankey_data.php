<?php 
	/*
		* Copyright (C) 2013 - Gareth Llewellyn
		*
		* This file is part of Graphite-Sankey - https://github.com/NetworksAreMadeOfString/Graphite-Sankey
		*
		* This program is free software: you can redistribute it and/or modify it
		* under the terms of the GNU General Public License as published by
		* the Free Software Foundation, either version 3 of the License, or
		* (at your option) any later version.
		*
		* This program is distributed in the hope that it will be useful, but WITHOUT
		* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
		* FOR A PARTICULAR PURPOSE. See the GNU General Public License
		* for more details.
		*
		* You should have received a copy of the GNU General Public License along with
		* this program. If not, see <http://www.gnu.org/licenses/>
	*/

	$graphiteHost = 'https://host.example.net';		//The full path to your graphite host e.g. https://test.domaim.com (no trailing / )
	
	$auth = array('basicAuthUser' => "",			// HTTP Basic Auth username - if empty HTTP Basic auth will not happen
				  'basicAuthPass' => "");			// HTTP Basic Auth password
	
	$nodeID = 0;	 								// Used for dictating node ids derived from the $NodeNames array
	$nodesLine = ""; 								// Used for printing the various nodes as JSON
	$linkLines = ""; 								// Used for printing the various links as JSON
	$nodeIndex = array();							// Used to track the node index for links
	
	
	/*
	 * Defines the nodes
	 * 
	 * Nodes are the points through which data flows e.g. in the diagram
	 * below 'App' 'CPU1', 'CPU2' & 'SYSTEM LOAD' are nodes
	 * 		
	 * 		App         C               
	 *         \_______ P______         S
	 *         /        U       \       Y L
	 *      App         1        \      S O
	 *                            \____ T A
	 *      App         C         /     E D
	 *         \_______ P________/      M
	 *         /        U
	 *      App         2     
	 * 
	 */
	$NodeNames = array('node1',
					'node2',
					'node3',
					);
	
	/*
	 * Defines the link endpoints and the metric that defines the link
	 *		array('source' => 'node1',
	 *			  'target' => 'node2',
	 *			  'metric' => 'graphite.target.line1.element'),
	 *		array('source' => 'node3',
	 *			  'target' => 'node2',
	 *			  'metric' => 'graphite.target.line2.element'),
	 *
	 *	
	 * 		Node1                  
	 *         \
	 *		    \ <= graphite.target.line1.element
	 *			 \
	 *			  Node2
	 *           /
	 *          / <= graphite.target.line2.element
	 *         /        
	 *      Node3       
	 */
	$graphite = array(
			array('source' => 'Node1',
					'target' => 'Node2',
					'metric' => 'graphite.target.line1.element'),
			
			array('source' => 'Node3',
					'target' => 'Node2',
					'metric' => 'graphite.target.line2.element'),
	);
	
	
	
	// Processes the NodeNames array to create the neccessary NodeIDs needed for the linking process
	foreach($NodeNames as $index => $NodeName)
	{
		$nodeIndex[$NodeName] = $index;
		//$nodeID++;
	}
	
	// Generates a hacky JSON Line for the node names
	foreach($NodeNames as $index => $NodeName)
	{
		$nodesLine .= '{"name":"'.$NodeName.'"},';
	}
	
	// Lazy way of fixing the trailing comma
	$nodesLine = substr($nodesLine,0,strlen($nodesLine) - 1);
	
	// Iterates through the various graphite elements to create the links
	// Searches the $NodeNames to ascertain the source & target IDs
	// Creates a hacky JSON string for printing later
	foreach($graphite as $Link)
	{
		$source = $nodeIndex[$Link['source']];
		$target = $nodeIndex[$Link['target']];
		$Value = getGraphite($graphiteHost, $Link['metric'],$auth);
	
		$linkLines .= '{"source":'.$source.',"target":'.$target.',"value":'.$Value.'},';
	}
	
	//Lazy way of fixing the trailing comma
	$linkLines = substr($linkLines,0,strlen($linkLines) - 1);

	//-------------------------------------------------------------------------------------------------------------------------
	
	print('{"nodes":
			[');
	print($nodesLine);
	print('],');
	print('"links":
			[');
	print($linkLines);
	print(']}');
	
	
	/**
	 * Gets the Graphite data
	 * @param string 	$host	The Graphite server (including protocol http / https)
	 * @param string 	$metric	The graphite target (devices.servers.srv0001.cpu.load)
	 * @param array 	$auth	An array containing a HTTP basic auth username and password
	 * 
	 * @returns float	-		A float from graphite or 1 (plugin doesn't like 0)
	 */
	function getGraphite($host,$metric,$auth)
	{
		$url = $host . '/render/?target='.urlencode($metric).'&format=json&from=-2minutes';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array ("Accept: application/json"));
        
        if(!empty($auth['basicAuthUser']))
			curl_setopt($ch, CURLOPT_USERPWD, $auth['basicAuthUser'].":".$auth['basicAuthPass']);
        
        $data = curl_exec($ch);

		$data = json_decode($data,true);

		if(empty($data[0]['datapoints'][0][0]))
		{
			if(empty($data[0]['datapoints'][1][0]))
			{
				return 1;
			}
			else
			{
				return $data[0]['datapoints'][1][0];
			}
		}
		else
		{
			return $data[0]['datapoints'][0][0];
		}
	}
?>