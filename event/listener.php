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

		$seo_rules = array(
			'post'  => array(
				array('pattern' => array('before' => 'post', 'after' => '\.html'), 'replacement' => 'viewtopic.php?p=', 'paginate' => array('before' => '-', 'after' => '\.html')),
				array('pattern' => array('before' => 'message', 'after' => '\.html'), 'replacement' => 'viewtopic.php?p=', 'paginate' => array('before' => '-', 'after' => '\.html')),
			),
			'topic' => array(
				array('pattern' => array('before' => 'topic', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
				array('pattern' => array('before' => 'sujet', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
				array('pattern' => array('before' => '-t', 'after' => '\.html'), 'replacement' => 'viewtopic.php?t=', 'paginate' => array('before' => '-', 'after' => '\.html')),
			),
			'user'  => array(
				array('pattern' => array('before' => '-u', 'after' => '\/'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
				array('pattern' => array('before' => 'member', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
				array('pattern' => array('before' => 'membre', 'after' => '\.html'), 'replacement' => 'memberlist.php?mode=viewprofile&u='),
			),
			'group' => array(
				array('pattern' => array('before' => '-g', 'after' => '\/'), 'replacement' => 'memberlist.php?mode=group&g=', 'paginate' => array('before' => '-', 'after' => '\.html')),
			),
			'forum' => array(
				array('pattern' => array('before' => 'forum', 'after' => ''), 'replacement' => 'viewforum.php?f=', 'paginate' => array('before' => 'page', 'after' => '\.html')),
				array('pattern' => array('before' => '-f', 'after' => ''), 'replacement' => 'viewforum.php?f=', 'paginate' => array('before' => 'page', 'after' => '\.html')),
			),
		);

		// Do not remove the following array
		// Only update 'after' and 'before' in the paginate array
		$seo_rules['noids'] = array(
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

		$base_uri = $original_url_parts['base'];

		// Check the following parameters before rebuilding the URI
		$allow_uri_rebuild_params[] = !strpos($base_uri, '.' . $this->php_ext);
		$allow_uri_rebuild_params[] = $base_uri !== '/';
		$allow_uri_rebuild_params[] = $base_uri !== $this->config['script_path'];
		$allow_uri_rebuild_params[] = $base_uri !== $this->config['script_path'] . '/';

		if ($this->allow_uri_rebuild($allow_uri_rebuild_params))
		{
			$build_url = $this->build_url($uri, $base_uri, $seo_rules);

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
	 * @param array  $seo_params
	 * @param bool   $no_ids
	 *
	 * @return bool|string
	 * @access private
	 */
	private function build_url($uri, $base_uri, $seo_params, $no_ids = false)
	{
		$build_url = '';

		$uri_clean = $this->strip_forum_name($base_uri);

		// Check SEO settings. If if fails once, we initiate settings for no ID
		$fail_check_seo_params = 3;
		while ($fail_check_seo_params > 1)
		{
			$fail_check_seo_params--;
			if ($this->check_seo_params($uri_clean, $seo_params, $no_ids))
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

		// Retriev all IDs available in the URI
		$ids = $this->get_id($uri_clean, 'pattern', !isset($this->seo_params['noids']));

		// Retrieve the ID based on its position
		$id = $this->get_higher_id($ids, 'id');

		// No ID, we try to find a forum ID
		if (empty($id['id']))
		{
			$forum_info = $this->get_forums_info($uri);

			if (isset($forum_info['id']))
			{
				$id['id'] = $forum_info['id'];
				$id = $this->get_higher_id($this->get_id($uri, 'paginate', $fail_check_seo_params == 0), 'num_page');
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
	 * Strip the forum name from the given URI
	 *
	 * @param $uri
	 *
	 * @return string
	 * @access private
	 */
	private function strip_forum_name($uri)
	{
		$forum_info = $this->get_forums_info($uri);

		return sizeof($forum_info) ? substr($uri, strpos($uri, $forum_info['name']) + strlen($forum_info['name'])) : $uri;
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
				return array('id' => $forum_id, 'name' => $forum_name);
			}
		}

		return array();
	}

	/**
	 * Checks mandatory SEO params
	 *
	 * @param string $uri
	 * @param array  $params
	 * @param bool   $no_id
	 *
	 * @return bool
	 * @access private
	 */
	private function check_seo_params($uri, $params, $no_id)
	{
		foreach ($params as $seo_type => $seo_config)
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

		return sizeof($this->seo_params) ? true : false;
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
		$pattern = '';

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
	 * @param array  $ids
	 * @param string $type
	 *
	 * @return array
	 * @access private
	 */
	private function get_higher_id($ids, $type)
	{
		$higher_id = array();
		$position = 0;

		if (sizeof($ids))
		{
			foreach ($ids as $id)
			{
				if ($id['pos'] > $position)
				{
					$position = $id['pos'];
					$higher_id[$type] = $id['id'];
					$higher_id['num_page'] = $id['num_page'];
					$higher_id['replacement'] = $id['replacement'];
				}
			}
		}

		return $higher_id;
	}
}
