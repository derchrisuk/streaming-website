<?php

if(!ini_get('short_open_tag'))
	die('`short_open_tag = On` is required');

require_once('lib/helper.php');

require_once('lib/PhpTemplate.php');
require_once('lib/Exceptions.php');
require_once('lib/less.php/Less.php');

require_once('model/ModelBase.php');
require_once('model/Conferences.php');
require_once('model/Conference.php');
require_once('model/Feedback.php');
require_once('model/Schedule.php');
require_once('model/Overview.php');
require_once('model/Room.php');
require_once('model/RoomTab.php');
require_once('model/RoomSelection.php');
require_once('model/Stream.php');
require_once('model/Relive.php');
require_once('model/Upcoming.php');


$route = @$_GET['route'];
$route = rtrim($route, '/');

@list($mandator, $route) = explode('/', $route, 2);
if(!$mandator)
{
	// root requested

	if(Conferences::getActiveConferencesCount() == 0)
	{
		// no clients
		//   error

		var_dump('no clients');
		exit;
	}
	else if(Conferences::getActiveConferencesCount() == 1)
	{
		// one client
		//   redirect

		$clients = Conferences::getActiveConferences();
		header('Location: '.forceslash( baseurl() . $clients[0] ));
		exit;
	}
	else
	{
		// multiple clients
		//   show overview

		// TODO Template
		$clients = Conferences::getActiveConferences();
		var_dump('multiple clients');
		var_dump($clients);
		exit;
	}
}
else if(!Conferences::exists($mandator))
{
	// old url OR wrong client OR
	// -> error
	die('unknown conference '.$mandator);
}

Conferences::load($mandator);


// PER-CONFERENCE CODE
$conference = new Conference();

$tpl = new PhpTemplate('template/page.phtml');
$tpl->set(array(
	'baseurl' => forceslash(baseurl()),
	'route' => $route,
	'canonicalurl' => forceslash(baseurl()).forceslash($route),
	'assemblies' => './template/assemblies/',

	'conference' => $conference,
	'feedback' => new Feedback(),
	'schedule' => new Schedule(),
));

if(startswith('//', @$GLOBALS['CONFIG']['BASEURL']))
{
	$tpl->set(array(
		'httpsurl' => forceslash('https:'.$GLOBALS['CONFIG']['BASEURL']).forceslash($route),
		'httpurl' =>  forceslash('http:'. $GLOBALS['CONFIG']['BASEURL']).forceslash($route),
	));
}

ob_start();
try {

	// ALWAYS AVAILABLE ROUTES
	if($route == 'feedback/read')
	{
		require('view/feedback-read.php');
	}

	else if($route == 'schedule.json')
	{
		require('view/schedule-json.php');
	}

	else if($route == 'streams/v1.json')
	{
		require('view/streams-json-v1.php');
	}

	else if($route == 'gen/main.css')
	{
		if(Conferences::hasCustomStyles($mandator))
		{
			handle_lesscss_request(
				Conferences::getCustomStyles($mandator),
				'../../'.Conferences::getCustomStylesDir($mandator)
			);
		}
		else {
			handle_lesscss_request('assets/css/main.less', '../../assets/css/');
		}
	}

	// HAS-NOT-BEGUN VIEW
	else if(!$conference->hasBegun())
	{
		require('view/not-started.php');
	}

	// ROUTES AVAILABLE AFTER BUT NOT BEFORE THE CONFERENCE
	else if(preg_match('@^relive/([0-9]+)$@', $route, $m))
	{
		$_GET = array(
			'id' => $m[1],
		);
		require('view/relive-player.php');
	}

	else if($route == 'relive')
	{
		require('view/relive.php');
	}


	// HAS-ENDED VIEW
	else if($conference->hasEnded())
	{
		require('view/closed.php');
	}

	// ROUTES AVAILABLE ONLY DURING THE CONFERENCE
	else if($route == '')
	{
		require('view/overview.php');
	}

	else if($route == 'about')
	{
		require('view/about.php');
	}

	else if($route == 'multiview')
	{
		require('view/multiview.php');
	}

	else if($route == 'feedback')
	{
		require('view/feedback.php');
	}

	else if(preg_match('@^([^/]+)$@', $route, $m))
	{
		$_GET = array(
			'room' => $m[1],
			'selection' => '',
			'language' => 'native',
		);
		require('view/room.php');
	}

	else if(preg_match('@^([^/]+)/translated$@', $route, $m))
	{
		$_GET = array(
			'room' => $m[1],
			'selection' => '',
			'language' => 'translated',
		);
		require('view/room.php');
	}

	else if(preg_match('@^([^/]+)/(sd|audio|slides)$@', $route, $m))
	{
		$_GET = array(
			'room' => $m[1],
			'selection' => $m[2],
			'language' => 'native',
		);
		require('view/room.php');
	}

	else if(preg_match('@^([^/]+)/(sd|audio|slides)/translated$@', $route, $m))
	{
		$_GET = array(
			'room' => $m[1],
			'selection' => $m[2],
			'language' => 'translated',
		);
		require('view/room.php');
	}

	else if(preg_match('@^embed/([^/]+)/(hd|sd|audio|slides)/(native|translated|stereo)(/no-autoplay)?$@', $route, $m))
	{
		$_GET = array(
			'room' => $m[1],
			'selection' => $m[2],
			'language' => $m[3],
			'autoplay' => !isset($m[4]),
		);
		require('view/embed.php');
	}

	// UNKNOWN ROUTE
	else
	{
		throw new NotFoundException();
	}

}
catch(NotFoundException $e)
{
	ob_clean();
	require('view/404.php');
}
catch(Exception $e)
{
	ob_clean();
	require('view/500.php');
}
