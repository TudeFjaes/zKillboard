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

class irc_accesslevel implements ircCommand {
	public function getRequiredAccessLevel() {
		return 0;
	}

	public function getDescription() {
		return "Retrieves your accesslevel with this bot. Usage: |g|.z accesslevel|n|";
	}

	public function execute($nick, $uhost, $channel, $command, $parameters, $nickAccessLevel) {
		$pName = trim(implode(" ", $parameters));
		if (strlen($pName)) irc_error("|r|You must be $pName to check access level.  Have them type: |g|.z accesslevel");
		$accessLevel = Db::queryField("select accessLevel from zz_irc_access where name = :name and host = :host", "accessLevel",
			array(":name" => $nick, ":host" => $uhost), 0);
		if ($accessLevel === null) irc_out("|r|You are not registered.|n|");
		else irc_out("|g|Your access level is:|n| $accessLevel");
	}

	public function isHidden() { return false; }
}
