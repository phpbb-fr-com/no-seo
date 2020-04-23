<?php
/**
 * @copyright (c) EmpreintesDuWeb https://www.empreintesduweb.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace empreintesduweb\seoregression\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected $path_helper;
	protected $request;
	protected $config;

	/** @var array */
	protected $cache_config = array('forum' => array());
	/** @var string */
	protected $php_ext;
	/** @var string */
	protected $phpbb_root_path;
	/** @var array */
	protected $seo_params = array();
	/** @var array */
	protected $forum_info = array();
	/** @var array */
	protected $seo_rules = array();

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config   $config      Config object
	 * @param \phpbb\path_helper     $path_helper Path helper object
	 * @param \phpbb\request\request $request     Request object
	 *
	 * @access public
	 */

	public function __construct(\phpbb\config\config $config, \phpbb\path_helper $path_helper, \phpbb\request\request $request)
	{
		$this->config = $config;
		$this->path_helper = $path_helper;
		$this->php_ext = $this->path_helper->get_php_ext();
		$this->phpbb_root_path = $this->path_helper->get_phpbb_root_path();
		$this->request = $request;

		if (!defined('PHPBB_SEO_CACHE'))
		{
			define('PHPBB_SEO_CACHE', $this->phpbb_root_path . 'ext/empreintesduweb/seoregression/includes/phpbb_cache.' . $this->php_ext);
		}

		if (file_exists(PHPBB_SEO_CACHE))
		{
			include(PHPBB_SEO_CACHE);
		}

		// If you use "Ultimate phpBB SEO Friendly URL" extension, copy as is the content of "config.runtime.php"
		// in the file "<ext_path>/includes/phpbb_cache.php"
		if (!empty($forum_urls))
		{
			$this->cache_config['forum'] = $forum_urls;
		}

		if (!empty($settings))
		{
			$this->cache_config['settings'] = $settings;
		}
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header'                          => 'seo_regression',
			'core.download_file_send_to_browser_before' => 'seo_regression',
		);
	}

	public function seo_regression($event)
	{
		// Array used to determine how undo the URL rewrite
		// Example:
		//      URL - with SEO:
		//          http://domain.tld/phpBB/post12346.html?hilit=keyword
		//      URL - without SEO
		//          http://domain.tld/phpBB/viewtopic.php?p=12346&hilit=keyword
		//      Array - with all options available:
		//          'sample' => array(
		//              array(
		//                  'pattern' => array(                     // Mandatory - Before and After pattern requires to locate the ID.
		//                      'before' => 'post',                 //             The after pattern is not mandatory, but recommended
		//                      'after'  => '.html',                // Warning:    The chars "/" needs to be escaped with "\"
		//                  ),
		//                  'replacement' => 'viewtopic.php?p=',    // Mandatory - First part of the URI to generate the new one.
		//                  'paginate' => array(                    // Optional  - Array used for pagination
		//                      'before' => '-'                     //             If set, must have a start and end value
		//                      'after'  => '.html'                 //             Pagination example: topic1234-60.html | forum-f1234/page60.html
		//                  ),
		//              ),
		//          ),

		$this->seo_rules = array(
			'post'  => array(
				// phpBB2 version
				array('pattern' => array('before' => 'viewpost_', 'after' => '\.html'), 'replacement' => 'viewtopic.php?p='),

				// phpBB3 versions
				array('pattern' => array('before' => 'post', 'after' => '\.html'), 'replacement' => 'viewtopic.php?p='),
				array('pattern' => array('before' => 'message', 'after' => '\.html'), 'replacement' => 'viewtopic.php?p='),
			),
			'topic' => array(
				// phpBB2 version
				/*
				 * http://forums.phpbb-fr.com/viewtopic_32090.html
				 * http://forums.phpbb-fr.com/viewtopic_32090_s30.html (start 30)
				 * http://forums.phpbb-fr.com/viewtopic_32215_pd0_poasc_s30.html (start 30 + ordering parameters, we will just ignore them…)
				 */
				array('pattern' => array('before' => 'viewtopic_', 'after' => '.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '(?:_.+)?_s', 'after' => '.html')),

				// phpBB3 versions
				array('pattern' => array('before' => 'topic', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
				array('pattern' => array('before' => 'sujet', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
				array('pattern' => array('before' => '-t', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
			),
			'user'  => array(
				// phpBB2 version
				array('pattern' => array('before' => 'memberlist_viewprofile_', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),

				// phpBB3 versions
				array('pattern' => array('before' => '-u', 'after' => '\/'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
				array('pattern' => array('before' => 'member', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
				array('pattern' => array('before' => 'membre', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
			),
			'group' => array(
				// phpBB2 version
				array('pattern' => array('before' => 'memberlist_group_', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=group&g='),

				// phpBB3 version
				array('pattern' => array('before' => '-g', 'after' => '\/'), 'replacement' => 'memberlist.php?mode=group&g=', 'paginate' => array('before' => '-', 'after' => '\.html')),
			),
			'forum' => array(
				array('pattern' => array('before' => 'forum', 'after' => ''), 'replacement' => 'viewforum.php?f=', 'paginate' => array('before' => 'page', 'after' => '\.html')),
				array('pattern' => array('before' => '-f', 'after' => ''), 'replacement' => 'viewforum.php?f=', 'paginate' => array('before' => 'page', 'after' => '\.html')),
			),
			'team'  => array(
				array('pattern' => 'team\.html', 'replacement' => 'memberlist.php?mode=team'),
				array('pattern' => 'equipe\.html', 'replacement' => 'memberlist.php?mode=team'),
			),
		);

		// Do not remove the following array
		// Only update 'after' and 'before' in the paginate array
		$this->seo_rules['noids'] = array(
			array('pattern'  => 'DoNotDeleteThisArray', 'replacement' => 'viewforum.php?f=',
				  'paginate' => array(
					  'before' => 'page',
					  'after'  => '\.html',
				  ),
			),
		);

		$allow_uri_rebuild_params = array();

		// Request the URI then removes the query string to prevent false positive
		$uri = $this->request->server('REQUEST_URI');
		$original_url_parts = $this->path_helper->get_url_parts($uri);

		if (strpos($original_url_parts['base'], $this->config['script_path']) === 0)
		{
			$base_uri = substr($original_url_parts['base'], strlen($this->config['script_path']));
			if (substr($base_uri, 0, 1) !== '/')
			{
				$base_uri = '/' . $base_uri;
			}
		}
		else
		{
			// We are outside of the forum, don't rewrite
			return;
		}

		// Check the following parameters before rebuilding the URI
		$allow_uri_rebuild_params[] = !strpos($base_uri, '.' . $this->php_ext);
		$allow_uri_rebuild_params[] = $base_uri !== '/';

		if ($this->allow_uri_rebuild($allow_uri_rebuild_params))
		{
			$build_url = $this->build_url($uri, $base_uri);

			// Redirect to the new formatted URL
			if (!empty($build_url))
			{
				$url_redirect = $this->path_helper->append_url_params($this->phpbb_root_path . $build_url, $original_url_parts['params']);

				send_status_line(301, 'Moved Permanently');
				redirect($url_redirect);
			}
		}
	}

	/**
	 * Return the product of the given array
	 *
	 * @param array $args
	 *
	 * @return bool
	 * @access private
	 */
	private function allow_uri_rebuild($args)
	{
		return (bool) array_product($args);
	}

	/**
	 * Build the new URL
	 *
	 * @param string $uri
	 * @param string $base_uri
	 *
	 * @return bool|string
	 * @access private
	 */
	private function build_url($uri, $base_uri)
	{
		$build_url = '';

		if ($this->check_static_rewrite($base_uri, $build_url))
		{
			return $build_url;
		}

		$uri_clean = $this->strip_forum_name($base_uri);

		// Set $no_ids if the content of $uri_clean and $uri are the same
		$no_ids = ($uri === $uri_clean) ? $this->get_seo_settings('rem_ids') : false;

		// Check SEO settings. If it fails once, we initiate settings for no ID
		$fail_check_seo_params = 3;
		while ($fail_check_seo_params > 1)
		{
			$fail_check_seo_params--;
			if ($this->check_seo_params($uri_clean, $no_ids))
			{
				$fail_check_seo_params = 0;
				continue;
			}
			$no_ids = true;
		}

		// We return false because no SEO rules were found.
		if ($fail_check_seo_params !== 0)
		{
			return false;
		}

		// No forum ID found in the SEO cache file. We check the URI.
		if (!isset($this->seo_params['noids0']))
		{
			// Retrieve all IDs available in the URI
			$ids = $this->get_id($uri_clean, 'pattern', !isset($this->seo_params['noids0']));

			// Retrieve the ID based on its position
			$id = $this->get_higher_id($ids);
		}

		// No ID, we try to find a forum ID
		if (empty($id['id']))
		{
			// Retrieve forum info on the SEO cache file, if not already filled
			if (!count($this->forum_info))
			{
				$this->forum_info = $this->get_forums_info($uri);
			}

			if (isset($this->forum_info['id']))
			{
				// Retrieve pagination information
				$ids = $this->get_id($uri_clean, 'paginate', true);

				// If pagination, assign value to $id. If not, override with minimal value
				if (count($ids))
				{
					$id = $this->get_higher_id($ids);
					$id['id'] = $this->forum_info['id'];
				}
				else
				{
					$id = array(
						'id'          => $this->forum_info['id'],
						'replacement' => $this->seo_params['noids0']['replacement'],
					);
				}
			}
		}

		if (!empty($id['id']))
		{
			$build_url = $id['replacement'] . $id['id'];

			// checks if pagination exist
			if (!empty($id['num_page']) && !empty($build_url))
			{
				$build_url .= '&start=' . $id['num_page'];
			}
		}

		return $build_url;
	}

	/**
	 * Check for static rewrites
	 *
	 * @param string  $base_uri
	 * @param string &$build_url
	 *
	 * @return bool
	 * @access private
	 */
	private function check_static_rewrite($base_uri, &$build_url)
	{
		foreach ($this->seo_rules as $seo_param)
		{
			if (!is_string($seo_param[0]['pattern']))
			{
				continue;
			}

			foreach ($seo_param as $seo_config)
			{
				if (preg_match('#^/(' . $seo_config['pattern'] . ')$#', $base_uri))
				{
					$build_url = $seo_config['replacement'];

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Strip the forum name from the given URI
	 *
	 * @param $uri
	 *
	 * @return bool|string
	 * @access private
	 */
	private function strip_forum_name($uri)
	{
		$uri_suffix = '';

		if (!$this->get_seo_settings('virtual_folder'))
		{
			$uri_suffix = '.html';
		}

		$this->forum_info = $this->get_forums_info($uri);

		if (count($this->forum_info))
		{
			if (strpos($uri, $this->forum_info['name'] . '/' . $this->seo_rules['noids'][0]['paginate']['before']))
			{
				return ($uri);
			}
			else if (strpos($uri, $this->forum_info['name'] . $uri_suffix))
			{
				return (substr($uri, strpos($uri, $this->forum_info['name'] . $uri_suffix) + strlen($this->forum_info['name'] . $uri_suffix)));
			}

			return (substr($uri, strpos($uri, $this->forum_info['name']) + strlen($this->forum_info['name'])));
		}

		return $uri;
	}

	/**
	 * Find forum information based on the URI given
	 *
	 * @param string $uri
	 *
	 * @return array
	 * @access private
	 */
	private function get_forums_info($uri)
	{
		// Remove from the URI the forum name
		foreach ($this->cache_config['forum'] as $forum_id => $forum_name)
		{
			// Returns the first item found.
			if (strpos($uri, $forum_name))
			{
				return array('id' => $forum_id, 'name' => $forum_name, 'pos' => 1);
			}
		}

		return array();
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	private function get_seo_settings($name)
	{
		// Get the setting value from phpbb_cache.php
		if (isset($this->cache_config['settings'][$name]))
		{
			return $this->cache_config['settings'][$name];
		}

		return false;
	}

	/**
	 * Checks mandatory SEO params
	 *
	 * @param string $uri
	 * @param bool   $no_id
	 *
	 * @return bool
	 * @access private
	 */
	private function check_seo_params($uri, $no_id)
	{
		foreach ($this->seo_rules as $seo_type => $seo_config)
		{
			foreach ($seo_config as $id => $seo_vars)
			{
				// Quick check for available ID
				$check_available_id = preg_match_all('#' . $this->get_preg_match_pattern($seo_vars, 'pattern', 'before') . '(\d+)#', $uri);
				if (!empty($seo_vars['pattern']) && !empty($seo_vars['replacement']) && ($no_id || $check_available_id))
				{
					if ($no_id && $seo_type !== 'noids')
					{
						$this->seo_params = array();
						continue;
					}
					else if (!$no_id && $seo_type === 'noids' && $check_available_id)
					{
						continue;
					}

					$this->seo_params[$seo_type . $id] = $seo_vars;
				}
			}
			unset($seo_vars);
		}
		unset($seo_type, $seo_config);

		return count($this->seo_params) ? true : false;
	}

	/**
	 * Returns the pattern for the preg_match function
	 *
	 * @param array  $seo_rules
	 * @param string $type
	 * @param string $position
	 *
	 * @return string
	 * @access private
	 */
	private function get_preg_match_pattern($seo_rules, $type, $position)
	{
		if (!empty($seo_rules[$type][$position]))
		{
			if (!empty($seo_rules['paginate']['before']) && $type == 'pattern' && $position == 'after')
			{
				$pattern = '((' . $seo_rules['pattern']['after'] . ')|(' . $seo_rules['paginate']['before'] . ')(\d+)(' . $seo_rules['pattern']['after'] . '))';
			}
			else
			{
				$pattern = '(' . $seo_rules[$type][$position] . ')';
			}
		}
		else
		{
			$pattern = '(DoNotUsePattern)';
		}

		return $pattern;
	}

	/**
	 * Extract the ID from the string provided
	 *
	 * @param string $uri
	 * @param string $pattern_type The pattern type. Can be 'pattern' or 'paginate"
	 * @param bool   $condition    The condition requires to find the ID
	 *
	 * @return array
	 * @access private
	 */
	private function get_id($uri, $pattern_type, $condition)
	{
		$id = array();
		$i = 0;

		foreach ($this->seo_params as $seo_type => $seo_config)
		{
			if ($condition)
			{
				// We try to find the ID in the URI
				// pattern :
				//	1st capturing group (<string>+): The string provided by the SEO params
				//	2nd capturing group (\d+): all digits following the first capturing group
				//	3rd capturing group (<string>+): The 'after' string provided by the paginate SEO params
				preg_match_all('#' . $this->get_preg_match_pattern($seo_config, $pattern_type, 'before') . '(\d+)' . $this->get_preg_match_pattern($seo_config, $pattern_type, 'after') . '#', $uri, $matches, PREG_SET_ORDER);

				if (empty($matches))
				{
					continue;
				}

				// Get the last result from the preg_match
				$id_match = end($matches);

				if (!isset($id_match[2]))
				{
					continue;
				}

				switch ($pattern_type)
				{
					case 'paginate':
						$id[$i]['id'] = 0;
						$id[$i]['num_page'] = $id_match[2];
						$id[$i]['replacement'] = $seo_config['replacement'];
						$id[$i]['pos'] = strrpos($uri, $id_match[0]);
					break;
					case 'pattern':
						$id[$i]['id'] = $id_match[2];
						$id[$i]['num_page'] = isset($id_match[6]) && is_numeric($id_match[6]) ? $id_match[6] : 0;
						$id[$i]['replacement'] = $seo_config['replacement'];
						$id[$i]['pos'] = strrpos($uri, $id_match[0]);
					break;
				}
			}

			$i++;
		}

		return $id;
	}

	/**
	 * Get the higher ID
	 *
	 * @param array $ids
	 *
	 * @return array
	 * @access private
	 */
	private function get_higher_id($ids)
	{
		$higher_id = array();
		$position = 0;

		foreach ($ids as $id)
		{
			if ($id['pos'] > $position)
			{
				$position = $id['pos'];
				$higher_id['num_page'] = isset($id['num_page']) ? $id['num_page'] : 0;
				$higher_id['id'] = $id['id'];
				$higher_id['replacement'] = $id['replacement'];
			}
		}

		return $higher_id;
	}
}
