<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<link rel=stylesheet href="/egx.css">
<script type="text/javascript" src="/xcl/xcl.js"></script>
<title>XCC Clans</title>
<table width="100%"><tr><td valign=bottom><p class=page_title>XCC Clans<td align=right valign=bottom><a href="/xwi/">Clans</a> | <a href="http://xccu.sourceforge.net/cgi-bin/forum.cgi">Forum</a> | <a href="http://xwis.net:4005/">Online</a> | <a href="http://strike-team.net/nuke/html/modules.php?op=modload&amp;name=News&amp;file=article&amp;sid=13">Rules</a> | <a href="http://xccu.sourceforge.net/utilities/XGS.zip" title="XCC Game Spy">XGS</a> | <a href="/downloads/XWISB.zip" title="XCC WOL IRC Server Beeper">XWISB</a> | <a href="/downloads/XWISC.exe" title="XCC WOL IRC Server Client">XWISC</a><br><a href="/xcl/?hof=" title="Hall of Fame">HoF</a> | <a href="/xcl/?hos=" title="Hall of Shame">HoS</a> | <a href="/xcl/?">Home</a> | <a href="/xcl/?stats=">Stats</a></table>
<hr>
<a href="?a=create">Create</a> |
<a href="?">Home</a> |
<a href="?a=invite">Invite</a> |
<a href="?a=join">Join</a> |
<a href="?a=kick">Kick</a> |
<a href="?a=leave">Leave</a> |
<a href="?a=reset_pass">Reset pass</a> |
<a href="?a=delete_nick">Delete nick</a> |
<a href="http://strike-team.net/forums/index.php?showforum=88">Hosted Clan Forums</a>
<hr>
<?php
	function apgar_encode($v)
	{
		$a = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./";
		$w = "";
		for ($i = 0; $i < 8; $i++)
		{
			$b = ord($v[$i]);
			$c = ord($v[8 - $i]);
			$w .= $a[($b & 1 ? $b << ($b & 1) & $c : $b ^ $c) & 0x3f];
		}
		return $w;
	}

	function new_security_code()
	{
		$v = "";
		$s = "0123456789ABCDEFGHIJKLMNOPQRSTUVXWXYZabcdefghijklmnopqrstuvxwxyz";
		for ($i = 0; $i < 16; $i++)
			$v .= $s[mt_rand(0, strlen($s) - 1)];
		return $v;
	}

	function get_clan($cid)
	{
		return mysql_fetch_array(db_query(sprintf("select * from xwi_clans where cid = %d", $cid)));
	}

	function get_player($name)
	{
		return mysql_fetch_array(db_query(sprintf("select * from xwi_players where name = '%s'", addslashes($name))));
	}

	function get_player2($name, $pass)
	{
		return mysql_fetch_array(db_query(sprintf("select * from xwi_players where name = '%s' and pass = md5('%s')", addslashes($name), apgar_encode($pass))));
	}

	function valid_clan_abbrev($v)
	{
		if (strlen($v) < 2 || strlen($v) > 6)
			return false;
		for ($i = 0; $i < strlen($v); $i++)
		{
			if (stristr('-0123456789@abcdefghijklmnopqrstuvwxyz', $v[$i]) === false)
				return false;
		}
		return true;
	}

	function valid_clan_name($v)
	{
		if (strlen($v) < 3 || strlen($v) > 32)
			return false;
		for ($i = 0; $i < strlen($v); $i++)
		{
			if (stristr(' -0123456789@abcdefghijklmnopqrstuvwxyz', $v[$i]) === false)
				return false;
		}
		return true;
	}

	require("../xcc_common.php");

	db_connect();

	$name = trim($_POST['name']);
	$pass = trim($_POST['pass']);

	switch ($_REQUEST['a'])
	{
	case "edit":
		{
			$cid = trim($_REQUEST['cid']);
			if (strlen($pass))
			{
				if ($clan = mysql_fetch_array(db_query(sprintf("select * from xwi_clans where cid = %d and pass = md5('%s')", $cid, addslashes($pass)))))
				{
					$icq = trim($_POST['icq']);
					$mail = trim($_POST['mail']);
					$msn = trim($_POST['msn']);
					$site = trim($_POST['site']);
					db_query(sprintf("update xwi_clans set icq = %d, mail = '%s', msn = '%s', site = '%s', mtime = unix_timestamp() where cid = %d", $icq, addslashes($mail), addslashes($msn), addslashes($site), $clan['cid']));
				}
				else
					echo("Wrong clan pass<hr>");
			}
			$clan = get_clan($cid);
			require('templates/clans_edit.php');
		}
		break;
	case "create":
		{
			$cabbrev = trim($_POST['cabbrev']);
			$cname = trim($_POST['cname']);
			$icq = trim($_POST['icq']);
			$mail = trim($_POST['mail']);
			$msn = trim($_POST['msn']);
			$site = trim($_POST['site']);
			if (!$site)
				$site = "http://";
			if ($name || $pass || $cname)
			{
				if (!valid_clan_abbrev($cabbrev))
					echo("Invalid clan abbreviation");
				else if (!valid_clan_name($cname))
					echo("Invalid clan name");
				else if ($player = get_player2($name, $pass))
				{
					if ($clan = mysql_fetch_array(db_query(sprintf("select name, full_name from xwi_clans where name = '%s' or full_name = '%s'", addslashes($cabbrev), addslashes($cname)))))
						printf("Clan %s (%s) already exists", $clan['name'], $clan['full_name']);
					else
					{
						do
						{
							$cpass = new_security_code();
							$results = db_query(sprintf("select count(*) from xwi_clans where pass = md5('%s')", $cpass));
							$result = mysql_fetch_array($results);
						}
						while ($result['0']);
						db_query(sprintf("insert into xwi_clans (name, full_name, pass, icq, mail, msn, site, mtime, ctime) values (lcase('%s'), '%s', md5('%s'), %d, lcase('%s'), lcase('%s'), lcase('%s'), unix_timestamp(), unix_timestamp())", addslashes($cabbrev), addslashes($cname), $cpass, $icq, addslashes($mail), addslashes($msn), addslashes($site)));
						$cid = mysql_insert_id();
						db_query(sprintf("update xwi_players set cid = %d where pid = %d", $cid, $player['pid']));
						$clan = get_clan($cid);
						printf("Player %s created clan %s<br>", $player['name'], $clan['name']);
						printf("The clan admin pass is %s", $cpass);
						if (strlen($mail))
							mail($mail, sprintf("XWI Clan Manager: Clan %s created", $clan['name']), sprintf("Player %s created clan %s with admin pass %s from IP address %s", $player['name'], $clan['name'], $cpass, $_SERVER['REMOTE_ADDR']), "from: XWIS <xwis>");
					}
				}
				else
					echo("Wrong name/pass combo");
				echo("<hr>");
			}
			require('templates/clans_create.php');
		}
		break;
	case "delete_nick":
		{
			if ($name || $pass)
			{
				if ($player = get_player2($name, $pass))
				{
					$results = db_query(sprintf("select count(*) from xcl_players where lid & 1 and name = '%s'", $name));
					$result = mysql_fetch_array($results);
					if ($result['0'])
						printf("Player %s is already in ladder", $player['name']);
					else
					{
						$results = db_query(sprintf("select to_days(now()) - to_days(ctime) from xwi_players where pid = %d", $player['pid']));
						$result = mysql_fetch_array($results);
						if ($result['0'] < 32)
							printf("Only players that were created more than 32 days ago can be deleted. Player %s was created %d days ago", $player['name'], $result['0']);
						else
						{
							db_query(sprintf("update xwi_players set flags = flags | 2 where pid = %d", $player['pid']));
							printf("Player %s has been deleted", $player['name']);
						}
					}
				}
				else
					echo("Wrong name/pass combo");
				echo("<hr>");
			}
			require('templates/players_delete.php');
		}
		break;
	case "invite":
		{
			if ($name || $pass)
			{
				if ($player = get_player($name))
				{
					if ($player['cid'])
					{
						$clan = get_clan($player['cid']);
						printf("Player %s is already in clan %s", $player['name'], $clan['name']);
					}
					else if ($clan = mysql_fetch_array(db_query(sprintf("select * from xwi_clans where pass = md5('%s')", addslashes($pass)))))
					{
						$result = mysql_fetch_array(db_query(sprintf("select count(*) from xwi_clan_invites where cid = %d", $clan['cid'])));
						do
						{
							$cpass = new_security_code();
							$results = db_query(sprintf("select count(*) from xwi_clan_invites where pass = md5('%s')", $cpass));
							$result = mysql_fetch_array($results);
						}
						while ($result['0']);
						if ($result['0'] < 10)
							db_query(sprintf("insert into xwi_clan_invites (pid, cid, pass) values (%d, %d, md5('%s'))", $player['pid'], $clan['cid'], $cpass));
						printf("Player %s may join %s with pass %s", $player['name'], $clan['name'], $cpass);
					}
					else
						echo("Wrong clan pass");
				}
				else
					echo("Unknown player");
				echo("<hr>");
			}
			require('templates/clans_invite.php');
		}
		break;
	case "join":
		{
			$cpass = trim($_POST['cpass']);
			if ($name || $pass || $cpass)
			{
				if ($player = get_player2($name, $pass))
				{
					db_query("delete from xwi_clan_invites where unix_timestamp(now()) - unix_timestamp(ctime) > 3 * 24 * 60 * 60");
					if ($player['cid'])
					{
						$clan = get_clan($player['cid']);
						printf("Player %s is already in clan %s", $player['name'], $clan['name']);
					}
					else if ($clan_invite = mysql_fetch_array(db_query(sprintf("select * from xwi_clan_invites where pass = md5('%s')", addslashes($cpass)))))
					{
						if ($clan_invite['pid'] == $player['pid'])
						{
							db_query(sprintf("delete from xwi_clan_invites where pid = %d and cid = %d", $player['pid'], $clan_invite['cid']));
							db_query(sprintf("update xwi_players set cid = %d where pid = %d", $clan_invite['cid'], $player['pid']));
							$clan = get_clan($clan_invite['cid']);
							printf("Player %s joined clan %s<br>", $player['name'], $clan['name']);
						}
						else
							echo("Wrong invitation");
					}
					else
						echo("Wrong clan pass");
				}
				else
					echo("Wrong name/pass combo");
				echo("<hr>");
			}
			require('templates/clans_join.php');
		}
		break;
	case "kick":
		{
			if ($name || $pass)
			{
				if ($player = get_player($name))
				{
					if ($player['cid'])
					{
						if ($clan = mysql_fetch_array(db_query(sprintf("select * from xwi_clans where pass = md5('%s')", addslashes($pass)))))
						{
							if ($player['cid'] == $clan['cid'])
							{
								db_query(sprintf("update xwi_players set cid = 0 where pid = %d", $player['pid']));
								printf("Player %s left clan %s", $player['name'], $clan['name']);
								$result = mysql_fetch_array(db_query(sprintf("select count(*) from xwi_players where cid = %d", $player['cid'])));
								if (!$result['0'])
									db_query(sprintf("delete from xwi_clans where cid = %d", $player['cid']));
							}
							else
								printf("Player %s is not in clan %s", $player['name'], $clan['name']);
						}
						else
							echo("Wrong clan pass");
					}
					else
						printf("Player %s is not in a clan", $player['name']);
				}
				else
					echo("Unknown player");
				echo("<hr>");
			}
			require('templates/clans_kick.php');
		}
		break;
	case 'reset_pass':
		$cname = trim($_POST['cname']);
		$mail = trim($_POST['mail']);
		$pass = trim($_REQUEST['pass']);
		if ($pass)
		{
			$results = db_query(sprintf("select * from xwi_clan_reset_pass_requests where pass = md5('%s')", addslashes($pass)));
			if ($result = mysql_fetch_assoc($results))
			{
				$cpass = new_security_code();
				db_query(sprintf("update xwi_clans set pass = md5('%s') where cid = %d", $cpass, $result['cid']));
				db_query(sprintf("delete from xwi_clan_reset_pass_requests where id = %d", $result['id']));
				printf("The new clan admin pass is %s", $cpass);

			}
			else
				echo("Wrong reset pass request");
			echo("<hr>");
		}
		else if ($cname && $mail)
		{
			if ($clan = mysql_fetch_array(db_query(sprintf("select cid, name, mail from xwi_clans where name = '%s' and mail = '%s'", addslashes($cname), addslashes($mail)))))
			{
				do
				{
					$cpass = new_security_code();
					$results = db_query(sprintf("select count(*) from xwi_clan_reset_pass_requests where pass = md5('%s')", $cpass));
					$result = mysql_fetch_array($results);
				}
				while ($result['0']);
				db_query(sprintf("insert into xwi_clan_reset_pass_requests (cid, pass, ctime) values (%d, md5('%s'), unix_timestamp())", $clan['cid'], $cpass));
				printf("A link to reset the clan admin pass has been emailed to %s", htmlspecialchars($clan['mail']));
				mail($clan['mail'], sprintf("XWI Clan Manager: Reset pass link for clan %s", $clan['name']), sprintf("To reset the clan admin pass for clan %s, click on http://xwis.net/xwi/?a=reset_pass&pass=%s. The request has been send from IP address %s", $clan['name'], $cpass, $_SERVER['REMOTE_ADDR']), "from: XWIS <xwis>");
			}
			else
			{
				echo("Wrong name/mail combo");
			}
			echo("<hr>");
		}
		require('templates/clans_reset_pass.php');
		break;
	case "leave":
		{
			if ($name || $pass)
			{
				if ($player = get_player2($name, $pass))
				{
					if ($player['cid'])
					{
						db_query(sprintf("update xwi_players set cid = 0 where pid = %d", $player['pid']));
						$clan = get_clan($player['cid']);
						printf("Player %s left clan %s", $player['name'], $clan['name']);
						$result = mysql_fetch_array(db_query(sprintf("select count(*) from xwi_players where cid = %d", $player['cid'])));
						if (!$result['0'])
							db_query(sprintf("delete from xwi_clans where cid = %d", $player['cid']));
					}
					else
						printf("Player %s is not in a clan", $player['name']);
				}
				else
					echo("Wrong name/pass combo");
				echo("<hr>");
			}
			require('templates/clans_leave.php');
		}
		break;
	default:
		$cid = $_REQUEST['cid'];
		if ($cid && $clan = get_clan($cid))
		{
			echo("<table>");
			printf("<tr><th align=right>Abbreviation<td>%s", $clan['name']);
			printf("<tr><th align=right>Name<td>%s", $clan['full_name']);
			if ($clan['icq'])
				printf('<tr><th align=right>ICQ<td><a href="http://wwp.icq.com/%d"><img src="http://wwp.icq.com/scripts/online.dll?icq=%d&img=2"></a>', $clan['icq'], $clan['icq']);
			if ($clan['mail'])
				printf('<tr><th align=right>Mail<td><a href="mailto:%s">%s</a>', htmlspecialchars($clan['mail']), htmlspecialchars($clan['mail']));
			if ($clan['msn'])
				printf('<tr><th align=right>MSN<td><a href="mailto:%s">%s</a>', htmlspecialchars($clan['msn']), htmlspecialchars($clan['msn']));
			if ($clan['site'] && $clan['site'] != 'http://')
			{
				if (!strstr($clan['site'], "://"))
					$clan['site'] = "http://" . $clan['site'];
				printf('<tr><th align=right>Site<td><a href="%s">%s</a>', htmlspecialchars($clan['site']), htmlspecialchars($clan['site']));
			}
			printf("<tr><th align=right>Modified<td>%s", gmdate("H:i d-m-Y", $clan['mtime']));
			printf("<tr><th align=right>Created<td>%s", gmdate("H:i d-m-Y", $clan['ctime']));
			printf('<tr><th><td><a href="?a=edit&cid=%d">Edit</a>', $clan['cid']);
			$results = db_query(sprintf("select name from xwi_players where cid = %d order by name", $cid));
			echo("</table><hr><table>");
			while ($result = mysql_fetch_array($results))
				printf('<tr><td><a href="/xcl/?pname=%s">%s</a>', $result['name'], $result['name']);
			echo("</table>");
		}
		else
		{
			?>
			<table>
				<form action="?" method=get>
					<tr>
						<td><input type=text name=text>
						<td><input type=submit value="Search">
				</form>
			</table>
			<hr>
			<?php
			$text = $_REQUEST['text'];
			if ($text)
				$results = db_query(sprintf("select xwi_clans.*, count(xwi_players.pid) size from xwi_clans left join xwi_players using (cid) where xwi_clans.name like '%s' or xwi_players.name like '%s' group by name order by name", addslashes($text), addslashes($text)));
			else
				$results = db_query("select xwi_clans.*, count(xwi_players.pid) size from xwi_clans left join xwi_players using (cid) group by name having size > 1 order by name");
			echo("<table><tr><th align=left>Abbrev<th align=left>Name<th align=right>Players<th align=left>Modified<th align=left>Created");
			while ($result = mysql_fetch_array($results))
				printf('<tr><td><a href="?cid=%d">%s</a><td>%s<td align=right>%d<td>%s<td>%s', $result['cid'], $result['name'], $result['full_name'], $result['size'], gmdate("d-m-Y", $result['mtime']), gmdate("d-m-Y", $result['ctime']));
			echo("</table>");
		}
	}
?>
<hr>
<a href="?a=create">Create</a> |
<a href="?">Home</a> |
<a href="?a=invite">Invite</a> |
<a href="?a=join">Join</a> |
<a href="?a=kick">Kick</a> |
<a href="?a=leave">Leave</a> |
<a href="?a=reset_pass">Reset pass</a> |
<a href="?a=delete_nick">Delete nick</a> |
<a href="http://strike-team.net/forums/index.php?showforum=88">Hosted Clan Forums</a>
<?php
	echo('<script type="text/javascript">');
	printf("page_bottom(%d);", time());
	echo('</script>');
?>