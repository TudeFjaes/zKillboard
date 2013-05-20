<?php
/* zKillboard
 * Copyright (C) 2012-2013 EVE-KILL Team and EVSCO.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class cli_stompSend implements cliCommand
{
	public function getDescription()
	{
		return "Sends out data via STOMP. |w|Beware, this is a persistent script. It's run and forget!.|n| Usage: |g|stompSend";
	}
	
	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters)
	{
		global $stompServer, $stompUser, $stompPassword;
		$stomp = new FuseSource\Stomp\Stomp($stompServer);
		$stomp->connect($stompUser, $stompPassword);
		$stomp->setReadTimeout(1);

		$stompKey = "StompSend::lastFetch";
		$lastFetch = time() - (12 * 3600);

		$lastFetch = Storage::retrieve($stompKey, $lastFetch);
		while (true)
		{
			$result = Db::query("SELECT killID, unix_timestamp(insertTime) AS insertTime, kill_json FROM zz_killmails WHERE insertTime > from_unixtime(:lastFetch) ORDER BY killID", array(":lastFetch" => $lastFetch), 0);
			$lastFetch = time();
			Storage::store($stompKey, $lastFetch);
			foreach($result as $kill)
			{
				$lastFetch = max($lastFetch, $kill["insertTime"]);
				if(!empty($kill["kill_json"]))
				{
					$stomp->begin($kill["killID"]);
					if($kill["killID"] > 0)
						$stomp->send(join(",", self::Destinations($kill["kill_json"])), $kill["kill_json"], array("transaction" => $kill["killID"]));
					
					$data = json_decode($kill["kill_json"], true);
					$json = json_encode(array("solarSystemID" => $data["solarSystemID"], "killID" => $data["killID"], "shipTypeID" => $data["victim"]["shipTypeID"], "killTime" => $data["killTime"]));
					$stomp->send("/topic/starmap.systems.active", $json, array("transaction" => $kill["killID"]));
					$stomp->commit($kill["killID"]);
				}
			}
			if(sizeof($result) > 0)
				Log::log("Stomped " . sizeof($result) . " killmails");
			sleep(5);
		}
	}

	function Destinations($kill)
	{
		$kill = json_decode($kill, true);
		$destinations = array();

		$destinations[] = "/topic/kills";
		$destinations[] = "/queue/kills";
		$destinations[] = "/topic/location.solarsystem.".$kill["solarSystemID"];

		// victim
		if($kill["victim"]["characterID"] > 0)
			$destinations[] = "/topic/involved.character.".$kill["victim"]["characterID"];
		if($kill["victim"]["corporationID"] > 0)
			$destinations[] = "/topic/involved.corporation.".$kill["victim"]["corporationID"];
		if($kill["victim"]["factionID"] > 0)
			$destinations[] = "/topic/involved.faction.".$kill["victim"]["factionID"];
		if($kill["victim"]["allianceID"] > 0)
			$destinations[] = "/topic/involved.alliance.".$kill["victim"]["allianceID"];

		// attackers
		foreach($kill["attackers"] as $attacker)
		{
			if($attacker["characterID"] > 0)
				$destinations[] = "/topic/involved.character." . $attacker["characterID"];
			if($attacker["corporationID"] > 0)
				$destinations[] = "/topic/involved.corporation." . $attacker["corporationID"];
			if($attacker["factionID"] > 0)
				$destinations[] = "/topic/involved.faction." . $attacker["factionID"];
			if($attacker["allianceID"] > 0)
				$destinations[] = "/topic/involved.alliance." . $attacker["allianceID"];
		}

		return $destinations;
	}
}