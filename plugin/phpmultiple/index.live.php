<?php
include("/usr/local/cpanel/php/cpanel.php");

define('PHPMULTIPLE_ETC', '/etc/phpmultiple/');
define('PHPMULTIPLE_ETC_HOME', $_SERVER['HOME'] . PHPMULTIPLE_ETC);
define('PHPMULTIPLE_CGI_SYS', '/usr/local/cpanel/cgi-sys');

$cpanel = &new CPANEL();

$cpanel->api1( 'setvar', '', array("dprefix=../") );

function phpmultiple_htaccess_find($path) {
	
}

function phpmultiple_htaccess_add($path, $version) {
	$domain_htaccess = $path . '/.htaccess';
	phpmultiple_htaccess_remove($path);
	$domain_htaccess_string = '# FCGID configuration for PHP5
<IfModule mod_fcgid.c>
Action application/x-httpd-php5 /fcgi-sys/php[version].fcgi
</IfModule>
';
	$domain_htaccess_data = '';
	if (file_exists($domain_htaccess)) {
		$domain_htaccess_data = file_get_contents($domain_htaccess);
	}
	
	$domain_htaccess_data = str_replace('[version]', $version, $domain_htaccess_string) . $domain_htaccess_data;
	file_put_contents($domain_htaccess, $domain_htaccess_data);
}

function phpmultiple_htaccess_remove($path) {
	$domain_htaccess = $path . '/.htaccess';
	if (file_exists($domain_htaccess)) {
		$domain_htaccess_data = file_get_contents($domain_htaccess);
		$domain_htaccess_data = preg_replace('|# FCGID configuration for PHP5\n<IfModule mod_fcgid.c>[^a-z]Action application/x-httpd-php5[^\<]*<\/IfModule>|', '', $domain_htaccess_data);
		file_put_contents($domain_htaccess, $domain_htaccess_data);
	}
}

function phpmultiple_get_domains() {
	global $cpanel;
	$domains = array();
	$domain_roots = $cpanel->api2('DomainLookup','getdocroots', array());

	if(is_array($domain_roots) && is_array($domain_roots['cpanelresult']['data'])) {
		$domains = $domain_roots['cpanelresult']['data'];
	}
	return $domains;
}

function phpmultiple_get_version($path) {
	$ver = '';
	$user_home = $_SERVER['HOME'];
	$user_home_etc = PHPMULTIPLE_ETC_HOME;
	if (is_dir($user_home_etc) && is_dir($path)) {
		$user_home_etc_file = $user_home_etc . md5($path);
		if (file_exists($user_home_etc_file)) $ver = @file_get_contents($user_home_etc_file);
	}
	$ver = trim($ver);
	return ($ver == '') ? 'default' : $ver;
}

function phpmultiple_get_versions() {
	global $cpanel;

	$ver_arr = array('default' => 'default');
	$dh = opendir(PHPMULTIPLE_ETC);
	if ($dh) {
		while($php_ver = readdir($dh)) {
			if ($php_ver == '.' || $php_ver == '..') continue;
			$php_ver_dir = PHPMULTIPLE_ETC . $php_ver;
			$php_ver_fcgi = $php_ver_dir . '/bin/php-cgi-fcgi';
			if (is_dir($php_ver_dir) && @file_exists($php_ver_fcgi))
				$ver_arr[$php_ver] = $php_ver;
			
		}
		closedir($dh);
	}
	return $ver_arr;
}

function phpmultiple_set_version($domain, $version) {
	$rv = false;
	$versions = phpmultiple_get_versions();
	$domains = phpmultiple_get_domains();
	$version = trim($version);

	if (isset($versions[$version])) {
		foreach($domains as $domain_info) {
			if ($domain_info['domain'] == $domain) {
				$domain_home = $domain_info['docroot'];
				//echo $domain_home;
				$domain_phpversion = PHPMULTIPLE_ETC_HOME . md5($domain_home);
				if ($version == 'default') {
					phpmultiple_htaccess_remove($domain_home);
					@unlink($domain_phpversion);
				}
				else {
					phpmultiple_htaccess_add($domain_home, $version);
					file_put_contents($domain_phpversion, $version);
					//@chown($domain_phpversion, $_SERVER['USER']);
					//@chgrp($domain_phpversion, $_SERVER['USER']);
				}
				$rv = true;
				break;
			}
		}
	}
	return $rv;
}

if (isset($_POST['domain']) && isset($_POST['version'])) {
	if (!is_dir(PHPMULTIPLE_ETC_HOME)) {
		@mkdir(PHPMULTIPLE_ETC_HOME);
		//@chown(PHPMULTIPLE_ETC_HOME, $_SERVER['USER']);
		//@chgrp(PHPMULTIPLE_ETC_HOME, $_SERVER['USER']);
	}
	phpmultiple_set_version($_POST['domain'], $_POST['version']);
	header('Location: index.live.php');
	exit();
}

//print_r($_SERVER);

$res = $cpanel->api1('Branding', 'include', array('stdheader.html') );
print $res['cpanelresult']['data']['result'];
?>
<div class="body-content">
<h2>PHP Multiple Version Select (Beta)</h2>
<?php 

$domain_roots = $cpanel->api2('DomainLookup','getdocroots', array());

if(is_array($domain_roots) && is_array($domain_roots['cpanelresult']['data'])) {
	$domains = $domain_roots['cpanelresult']['data'];
	$php_versions = phpmultiple_get_versions();
	?>
	<div class="highlight">
	<form id="phpmultiple" method="post" action="index.live.php">
	<table>
	<tr>
		<td>Domain: <select name="domain">
		<?php
		foreach($domains as $domain) echo '<option value="'.$domain['domain'].'">'.$domain['domain'].'</option>';
		?>
		</select></td>
		<td>PHP Version: <select name="version">
		<?php
		foreach($php_versions as $php_version => $php_version_name) echo '<option value="'.$php_version.'">'.$php_version_name.'</option>';
		?>
		</select></td>
		<td><input type="submit" value="Change" /></td>
	</tr>
	</table>
	</form>
	</div>

	<table id="maintbl" class="nonsortable" cellspacing="0" cellpadding="0" border="0">
	<thead>
		<th>Domain</th>
		<th>Path</th>
		<th>Version</th>
	</thead>
	<?php
	
	$domains_home = array();
	foreach($domains as $domain) {
		$domains_home[$domain['docroot']][] = $domain['domain'];
	}

	foreach($domains_home as $domain_root => $domain_list) {
		?>
		<tr>
			<td><?php echo implode('<br />', $domain_list); ?></td>
			<td><?php echo $domain_root; ?></td>
			<td><?php echo phpmultiple_get_version($domain_root); ?></td>
		</tr>
		<!-- <div><?php echo $domain['domain'] . ' : ' . $domain['docroot'];?></div> -->
		<?php
	}
	?>
	</table>
	<?php
} else {
	echo '<div style="text-align: center; padding: 50px;">This feature not enabled yet!</div>';
}

?>
</div>
<?php
$res = $cpanel->api1('Branding', 'include', array('stdfooter.html') );
print $res['cpanelresult']['data']['result'];
$cpanel->end();
