<?php
/**
 * CleanTalk SMF package
 *
 * @version 1.0.1
 * @package Cleantalk
 * @subpackage SMF
 * @author CleanTalk (welcome@cleantalk.ru)
 * @copyright (C) 2013 Сleantalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 */

if (!defined('SMF'))
	die('Hacking attempt...');

require_once(dirname(__FILE__) . '/cleantalk.class.php');

/**
 * CleanTalk integrate register hook
 * @param array $regOptions
 * @param array $theme_vars
 * @return void
 */
function cleantalk_check_register(&$regOptions, $theme_vars)
{
	global $language, $user_info, $modSettings;

	if ($regOptions['interface'] == 'admin') {
		return;
	}

	$ct = new Cleantalk();
	$ct->server_url = 'http://moderate.cleantalk.ru';

	$ct_request = new CleantalkRequest();
	$ct_request->auth_key = cleantalk_get_api_key();

	$ct_request->response_lang = 'en'; // SMF use any charset and language

	$ct_request->agent = 'php-api';
	$ct_request->sender_email = isset($regOptions['email']) ? $regOptions['email'] : '';
	$ct_request->sender_ip = isset($regOptions['register_vars']['member_ip']) ? $regOptions['register_vars']['member_ip'] : '';
	$ct_request->sender_nickname = isset($regOptions['username']) ? $regOptions['username'] : '';
	if (isset($_SESSION['cleantalk_registration_form_start_time'])) {
		$ct_request->submit_time = time() - $_SESSION['cleantalk_registration_form_start_time'];
	}
	if (isset($_POST['ct_checkjs'])) {
		$ct_request->js_on = $_POST['ct_checkjs'] == cleantalk_get_checkjs_code() ? 1 : 0;
	}

	$ct_request->sender_info = json_encode(array(
		'REFFERRER' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
		'cms_lang' => substr($language, 0, 2),
		'USER_AGENT' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
	));

	// CleanTalk API call
	$ct_result = $ct->isAllowUser($ct_request);

	if ($ct_result->allow == 1) {
		// this not error, only logging
		log_error('CleanTalk: allow regisration for "' . $regOptions['username'] . '"', 'user');
	} else {
		// this is bot
		fatal_error('CleanTalk: ' . strip_tags($ct_result->comment), 'user');
	}

	if ($ct_result->inactive == 1) {
		// need admin approval

		log_error('CleanTalk: need approval for "' . $regOptions['username'] . '"', 'user');

		$regOptions['register_vars']['is_activated'] = 3; // waiting for admin approval
		$regOptions['require'] = 'approval';

		if (!isset($modSettings['notify_new_registration']) || empty($modSettings['notify_new_registration'])) {
			// temporarly turn on notify for new registration
			$modSettings['notify_new_registration'] = 1;
		}

		// add Cleantalk message to email template
		$user_info['cleantalkmessage'] = $ct_result->comment;

		// temporarly turn on registration_method to approval_after
		$modSettings['registration_method'] = 2;
	}

	return;
}

/**
 * Get CleanTalk hidden js code
 * @return string
 */
function cleantalk_get_checkjs_code()
{
	global $webmaster_email;

	return md5(cleantalk_get_api_key() . $webmaster_email);
}

/**
 * Get CleanTalk API KEY from SMF settings
 * @return string
 */
function cleantalk_get_api_key()
{
	global $modSettings;

	return isset($modSettings['cleantalk_api_key']) ? $modSettings['cleantalk_api_key'] : null;
}

/**
 * Add CleanTalk setting into admin panel
 * @param array $config_vars
 */
function cleantalk_general_mod_settings(&$config_vars)
{
	$config_vars += array(
		array('title', 'cleantalk_settings'),
		array('text', 'cleantalk_api_key'),
		array('desc', 'cleantalk_api_key_description'),
	);
}