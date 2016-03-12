<?php

/***************************************************************
*  Copyright notice
*
*  (c) 2005-2007 Mauro Lorenzutti (Webformat srl) (mauro.lorenzutti@webformat.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * DB connection class
 *
 * @author	Mauro Lorenzutti <mauro.lorenzutti@webformat.com>
 */


class tx_wfqbe_connect {
	var $extKey = 'wfqbe'; // The extension key.

	function connect($where) {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wfqbe_query', $where . 'tx_wfqbe_query.hidden!=1 AND tx_wfqbe_query.deleted!=1');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)>0)	{
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			if (!isset($GLOBALS['WFQBE'][$row['credentials']]))	{
				$h = $this->connectNow($row['credentials'], $row['dbname']);
				$h->SetFetchMode(ADODB_FETCH_BOTH);
				$GLOBALS['WFQBE'][$row['credentials']] = $h;
			}	else	{
				$h = $GLOBALS['WFQBE'][$row['credentials']];
			}
			if ($h!==false)
				return array("conn" => $h, "row" => $row);
			else
				return false;
		}
		return false;
	}
	
	
	
	
	function connectNow($credentials = 0, $dbname='') {
		
		if ($credentials == 0) {
			// Local TYPO3 DB
			$h = NewADOConnection("mysql"); // TODO: correct this for DBAL compatibility
			$resultConnection = $h->Connect(TYPO3_db_host, TYPO3_db_username, TYPO3_db_password, TYPO3_db);
			if ($resultConnection) {
				global $TYPO3_CONF_VARS;
				if ($TYPO3_CONF_VARS['SYS']['setDBinit']!='')	{
					//$h->query($TYPO3_CONF_VARS['SYS']['setDBinit']);
					$statements = explode(';', $TYPO3_CONF_VARS['SYS']['setDBinit']);
					foreach ($statements as $statement) {
						$h->query($statement);
					}
				}
				if (intval(str_replace('.','',TYPO3_branch))>=47) {
					$h->query("SET NAMES utf8;");
				}
				return $h;
			} else {
				$content = $h->ErrorMsg()."<br /><br />";
				echo($content);
				return false;
			}
		} else {
			$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('host,dbms,username,passw,conn_type,setdbinit,dbname,type,connection_uri,connection_localconf', 'tx_wfqbe_credentials', 'tx_wfqbe_credentials.uid=' . intval($credentials), '', '', '');
			while ($row2 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
				if ($row2['type']=='localconf')	{
					global $TYPO3_CONF_VARS;
					// Overwrites standard configuration array with values from localconf. Useful to avoid rewriting connection calls
					if ($row2['connection_localconf']!='' && is_array($TYPO3_CONF_VARS['EXTCONF']['wfqbe']['__CONNECTIONS']) && is_array($TYPO3_CONF_VARS['EXTCONF']['wfqbe']['__CONNECTIONS'][$row2['connection_localconf']]))	{
						foreach ($TYPO3_CONF_VARS['EXTCONF']['wfqbe']['__CONNECTIONS'][$row2['connection_localconf']] as $key => $value)
							$row2[$key] = $value;
					}
				}
				
				if ($row2['type']=='uri')	{
					// Alternative uri connection type
					$h = NewADOConnection($row2['connection_uri']);
					$resultConnection = true;
					
				}	else	{
					// Standard connection type
					if ($row2['dbname']!='')
						$dbname = $row2['dbname'];
					
					$h = NewADOConnection($row2['dbms']); //starts a new connection 
					if ($row2['dbms'] == 'access') {
						$dsn = "Driver={Microsoft Access Driver (*.mdb)};Dbq=" . $row2['host'] . ";Uid=" . $row2['username'] . ";Pwd=" . $row2['passw'] . ";";
						if ($row2['conn_type']=="PConnect")
							$resultConnection = $h->PConnect($dsn);
						elseif ($row2['conn_type']=="NConnect")
							$resultConnection = $h->NConnect($dsn);
						else
							$resultConnection = $h->Connect($dsn);
					} else {
						if ($row2['conn_type']=="PConnect")
							$resultConnection = $h->PConnect($row2['host'], $row2['username'], $row2['passw'], $dbname);
						elseif ($row2['conn_type']=="NConnect")
							$resultConnection = $h->NConnect($row2['host'], $row2['username'], $row2['passw'], $dbname);
						else	{
							$resultConnection = $h->Connect($row2['host'], $row2['username'], $row2['passw'], ''.$dbname);
						}
					}
				}
				
				if ($resultConnection) {
					if ($row2['setdbinit']!='')	{
						//$h->query($row2['setdbinit']);
						$statements = explode(';', $row2['setdbinit']);
						foreach ($statements as $statement) {
							$h->query($statement);
						}
					}
					return $h;
				} else {
					$content = $h->ErrorMsg()."<br /><br />";
					echo($content);
					return false;
				}
			}
		}
		
		return false;
	}
}
