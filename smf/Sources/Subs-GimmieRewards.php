<?php
if (!defined('SMF')) {
  die('You are not allowed to access this file directly');
}

/**
 * Gimmie Hook
 */
function gimmie_menu_buttons_hook(&$menu_buttons) {
  global $txt;
  
  $menu_buttons = array_merge(
    array_slice($menu_buttons, 0, 1, true),
    array(
      'gimmie' => array(
        'title' => $txt['gmss_button'],
        'href' => 'javascript:GimmieWidget._showPopup(\'catalog\')',
        'show' => true,
        'sub_buttons' => array()
      )
    ),
    array_slice($menu_buttons, 1)
  );
}

function gimmie_admin_area_hook(&$admin_area) {
  global $txt;
  
  $admin_area = array_merge(
    $admin_area,
    array(
      'gmss' => array(
        'title' => $txt['gmss_title'],
        'permission' => array('admin_forum'),
        'areas' => array(
          'gmss' => array(
            'label' => $txt['gmss_gimmie_rewards'],
            'file' => 'Subs-GimmieRewards.php',
            'function' => 'gimmie_reward_config',
            'custom_url' => $scopeurl.'?action=admin;area=gmss;sa=settings;secs='.$sc
          )
        )
      )
    )
  );
}

function gimmie_actions_hook(&$actions) {
  $actions = array_merge(
    $actions,
    array(
      'gmpx' => array('Subs-GimmieRewards.php', 'gimmie_proxy'), # Proxy
      'gmss' => array('Subs-GimmieRewards.php', 'gimmie_reward_config'), # Configuration Action
    )
  );
}

function gimmie_load_theme_hook() {
  global $context, $settings, $modSettings, $scripturl, $user_info;
  
  $themeurl = $settings['theme_url'];
	$user = $context['user'];
	
	$endpoint = $scripturl."?action=gmpx;sa=";
	$key = $modSettings['gm_key'];
	$notification_timeout = isset($modSettings['gm_notification_timeout']) ? $modSettings['gm_notification_timeout'] : 10;
	
	$country = '';
	
	if (!isset($modSettings['gm_country']) || $modSettings['gm_country'] == 'auto') {
	  $ip = isset($user_info['ip2']) ? $user_info['ip2'] : $user_info['ip'];
    $country = trim(file_get_contents('http://api.wipmania.com/'.$ip));
	}
	else {
	  $country = $modSettings['gm_country'];
	}
	
  $headers = $context['html_headers'];
	$headers = $headers.<<<EOH
	
	
	<link href="//cdnjs.cloudflare.com/ajax/libs/select2/3.4.5/select2.css" rel="stylesheet" />
	<link href="$themeurl/css/GimmieRewards.css" rel="stylesheet" />
  
	<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/select2/3.4.5/select2.js"></script>
	<script type="text/javascript">
	  $(document).ready(function () {
	    $(".gimmie .gm-select").select2();
	  });
	</script>
  
EOH;

  if ($modSettings['gm_enable']) {
  	$headers = $headers.<<<EOS
	<script type="text/javascript">
	  _gimmie = {
	    "endpoint"                    : "$endpoint",
	    "key"                         : "$key",
	    "country"                     : "$country",  
EOS;
    
    if (!$user['is_guest']) {
      $email = $user['email'];
      $username = $user['username'];
      $name = $user['name'];
      
      $headers = $headers.<<<EOU
      
	    "user"                        : {
	        "external_uid"              : "$email",
	        // Display name
	        "name"                      : "$username",
	        // Gateway name
	        "realname"                  : "$name",
	        "email"                     : "$email",
	        "avatar"                    : ""
	    },
EOU;
    }
    
    $headers = $headers.<<<EOS

	    "options"                     : {
	      "animate"                   : true,
	      "auto_show_notification"    : true,
	      "notification_timeout"      : $notification_timeout,
	      "responsive"                : true,
	      "show_anonymous_rewards"    : true,
	      "shuffle_reward"            : true
	    },
	    "templates"                   : {}
	  };
    
	  $(document).ready(function () {
	    var root = document.createElement('div');
	    root.id = "gimmie-root";
	    document.body.appendChild(root);
	    
	    (function(d){
	      var js, id = "gimmie-widget", ref = d.getElementsByTagName("script")[0];
	      if (d.getElementById(id)) {return;}
	      js = d.createElement("script"); js.id = id; js.async = true;
	      js.src = "http://api.llun.in/assets/gimmie-widget2.all.js";
	      ref.parentNode.insertBefore(js, ref);
	    }(document));
	  });
	</script>
EOS;

	}
	
  $context['html_headers'] = $headers;
}

/**
 * Action Hooks
 */
function gimmie_redirect(&$setLocation, &$refresh) {
  global $context, $user_info, $smcFunc, $topic;
  $email = $user_info['email'];

  switch ($context['current_action']) {
    case 'vote':
      if (_check_event_enable('gm_trigger_vote_poll')) {
        _trigger_event_for_user('did_smf_vote_poll', $email);
      }
      break;
    case 'post2':
      $request = $smcFunc['db_query']('', '
  			SELECT
  				t.id_member_started, t.id_poll, t.num_replies, t.id_last_msg, ml.body,
  				CASE WHEN ml.poster_time > ml.modified_time THEN ml.poster_time ELSE ml.modified_time END AS last_post_time
  			FROM {db_prefix}topics AS t
  				LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_topic = t.id_topic AND ln.id_member = {int:current_member})
  				LEFT JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
  			WHERE t.id_topic = {int:current_topic}
  			LIMIT 1',
  			array(
  				'current_member' => $user_info['id'],
  				'current_topic' => $topic,
  			)
  		);
  		list ($owner, $poll_id, $total_replies, $last_message_id, $last_message) = $smcFunc['db_fetch_row']($request);
  		$smcFunc['db_free_result']($request);
  
      $user_id = $user_info['id'];
      if ($total_replies > 0) {
    		if ($user_id == $owner && _check_event_enable('gm_trigger_reply_own_thread')) {
      		_trigger_event_for_user('did_smf_reply_own_thread', $email);
    		}
    		else if (_check_event_enable('gm_trigger_reply_thread')) {
    		  _trigger_event_for_user('did_smf_reply_thread', $email);
    		}
  		}
  		else if ($poll_id && _check_event_enable('gm_trigger_create_poll')) {
    		_trigger_event_for_user('did_smf_new_poll', $email);
  		}
  		else {
    		// This is new post
    		if (_check_event_enable('gm_trigger_new_thread')) {
      		_trigger_event_for_user('did_smf_new_thread', $email);
    		}
  		}

      break;
  }  

  
}
  
function gimmie_login_hook($username) {
  global $sourcedir, $context, $modSettings, $user_profile;
  require_once($sourcedir.'/Gimmie.sdk.php');
  
  if (_check_event_enable('gm_trigger_login')) {
    $members = loadMemberData(array($username), true);
    $user = $user_profile[array_pop($members)];
    
    $email = $user['email_address'];
    _trigger_event_for_user('did_smf_user_login_time', $email);
  }
  
  // SMF Requires this hook returns something.
  return "";
}

function _check_event_enable($name) {
  global $modSettings;
  return (isset($modSettings['gm_enable']) && $modSettings['gm_enable'] && $modSettings[$name]);
}

function _trigger_event_for_user($event, $user) {
  global $modSettings, $sourcedir;
  require_once($sourcedir.'/Gimmie.sdk.php');
  
  $gimmie = Gimmie::getInstance($modSettings['gm_key'], $modSettings['gm_secret']);
  $gimmie->login($user);
  $gimmie->trigger_with_name($event);
}


/**
 * Proxy Area
 */
function gimmie_proxy() {
  global $sourcedir, $context, $modSettings;
  require_once($sourcedir.'/Gimmie.sdk.php');

  $sa = !empty($_REQUEST['sa']) ? $_REQUEST['sa'] : '';
  
  $id = $context['user']['email'];

  $key = $modSettings['gm_key'];
  $secret = $modSettings['gm_secret'];

  $endpoint = 'https://api.gimmieworld.com'.$sa;

  $access_token = $context['user']['email'];
  $access_token_secret = $secret;
  $params = array();
  $sig_method = new OAuthSignatureMethod_HMAC_SHA1();
  $consumer = new OAuthConsumer($key, $secret, NULL);
  $token = new OAuthConsumer($access_token, $access_token_secret);
  
  $json = '{"response":{"success":false}, "error":{}}';
  if (!is_null($endpoint)) {
    $acc_req = OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $endpoint, $params);
    $acc_req->sign_request($sig_method, $consumer, $token);
    
    $json = file_get_contents($acc_req);
  }
  
  header('Access-Control-Allow-Origin: *');
  header('Content-type: application/json');
  print($json);
  obExit(false);
}

/**
 * Administration Area
 */
function gimmie_reward_config() {
  $sa = !empty($_REQUEST['sa']) ? $_REQUEST['sa'] : '';
  switch ($sa) {
  case 'save':
    gimmie_reward_config_save();
    break;
  default:
    gimmie_reward_config_show();
    break;
  }
}

function gimmie_reward_config_show() {
  global $txt, $context, $modSettings, $settings;

  isAllowedTo('admin_forum');
  loadLanguage('GimmieRewards');
  loadtemplate('GimmieRewards.admin', array('GimmieRewards.admin'));

  $context['sub_template'] = 'gimmie_rewards_config';

  $context['page_title'] = $txt['gmss_title'];
}

function gimmie_reward_config_save() {
  isAllowedTo('admin_forum');

  checkSession('post');
  $gm_settings = $_REQUEST['gm_settings'];
  
  gimmie_log($gm_settings);
  
  $gm_settings['gm_enable'] = (!empty($gm_settings['gm_enable']) ? true : false);
  
  $gm_settings['gm_key'] = (!empty($gm_settings['gm_key']) ? trim($gm_settings['gm_key']) : "");
  $gm_settings['gm_secret'] = (!empty($gm_settings['gm_secret']) ? trim($gm_settings['gm_secret']) : "");
  $gm_settings['gm_country'] = (!empty($gm_settings['gm_country']) ? trim($gm_settings['gm_country']) : 'auto');
  
  $gm_settings['gm_notification_timeout'] = (!empty($gm_settings['gm_notification_timeout']) ? intval(trim($gm_settings['gm_notification_timeout'])) : 10);
  
  $gm_settings['gm_views_catalog'] = (!empty($gm_settings['gm_views_catalog']) ? true : false);
  $gm_settings['gm_views_profile'] = (!empty($gm_settings['gm_views_profile']) ? true : false);
  $gm_settings['gm_views_leaderboard'] = (!empty($gm_settings['gm_views_leaderboard']) ? true : false);
  
  $gm_settings['gm_trigger_login'] = (!empty($gm_settings['gm_trigger_login']) ? true : false);
  $gm_settings['gm_trigger_new_thread'] = (!empty($gm_settings['gm_trigger_new_thread']) ? true : false);
  $gm_settings['gm_trigger_reply_thread'] = (!empty($gm_settings['gm_trigger_reply_thread']) ? true : false);
  $gm_settings['gm_trigger_reply_own_thread'] = (!empty($gm_settings['gm_trigger_reply_own_thread']) ? true : false);
  $gm_settings['gm_trigger_create_poll'] = (!empty($gm_settings['gm_trigger_create_poll']) ? true : false);
  $gm_settings['gm_trigger_vote_poll'] = (!empty($gm_settings['gm_trigger_vote_poll']) ? true : false);

  updateSettings($gm_settings);
  redirectexit('action=admin;area=gmss;sa=settings;gmss_action=saved');

}

/**
 * Utilities functions
 */
function gimmie_log($mixed) {
  error_log(print_r($mixed, 1)."\n", 3, '/var/log/debug.php.log');
}


?>
