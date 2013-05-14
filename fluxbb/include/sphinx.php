<?php

/**
 * Copyright (C) 2011 Guillaume Kulakowski
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class fluxSphinx
{
	/**
	 * Singleton instance
	 * @var fluxSphinx $instance
	 */
	static private $instance = null;
	/**
	 * Is Sphinx used ?
	 * @var boolean $use
	 */
	static public $use = false;
	/**
	 * SphinxClient
	 * @var SphinxClient $client
	 */
	private $client = null;
	/**
	 * SphinxClient result
	 * @var array $result
	 */
	public $result = null;
	/**
	 * Search keywords
	 * @var array $result
	 */
	public $keywords = '';
	/**
	 * Search sort by
	 * @var string $sort_by
	 */
	public $sortBy = '';
	/**
	 * Search sort by dir
	 * @var string $sort_by
	 */
	public $sortDir = 'DESC';





	/**
	 * Private constructor to prevent non-singleton use
	 */
	private function __construct ()
	{
		global $sphinx_config;

		// Load Sphinx from PECL
		if ( $sphinx_config['use_pecl'] )
		{
			define( 'SPHINX_API_LOADED', true );
		}
		else
		{
			if ( !defined( 'SPHINX_API_LOADED' ) OR !SPHINX_API_LOADED )
			{
				$sphinxLoaded = @include PUN_ROOT.'include/sphinxapi.php';
				if ( !$sphinxLoaded )
				{
					$sphinxLoaded = include 'sphinxapi.php';
				}
				define( 'SPHINX_API_LOADED', $sphinxLoaded );
			}

			if ( !defined( 'SPHINX_API_LOADED' ) OR !SPHINX_API_LOADED )
				error('Unable to load SphinxAPI', __FILE__, __LINE__ );
		}

		// Setup SphinxClient
		$this->client = new SphinxClient;
		$this->client->setServer( $sphinx_config['host'], $sphinx_config['port'] );
		$this->client->setMatchMode( SPH_MATCH_EXTENDED2 );
		$this->client->setMaxQueryTime( $sphinx_config['max_query_time'] );
		$this->client->SetFieldWeights( $sphinx_config['weights'] );
	}



	/**
	 * Don't allow clone
	 *
	 * @throws Exception because Gauffr don't allow clone.
	 */
	private function __clone ()
	{
		throw new Exception ('Clone is not allowed');
	}



	/**
	 * Returns an instance of the class SphinxBB.
	 *
	 * @return SphinxBB Instance of SphinxBB
	 */
	public static function getInstance()
	{
		if ( is_null( self::$instance ) )
		{
			self::$instance = new fluxSphinx();
		}
		return self::$instance;
	}





/* ********************************************************* Set SphinxClient */

	/**
	 * Set SphinxClient limit value
	 *
	 * @param string $show_as
	 * @param array $pun_user
	 * @param int $max_matches
	 */
	public function setLimit( $show_as, $pun_user, $max_matches=1000 )
	{
		// Set limit
		$per_page = ($show_as == 'posts') ? $pun_user['disp_posts'] : $pun_user['disp_topics'];

		$p = (!isset($_GET['p']) || $_GET['p'] <= 1) ? 1 : intval($_GET['p']);
		$start_from = $per_page * ($p - 1);

		$this->client->setLimits( (int)$start_from, (int)$per_page, (int)$max_matches );
	}



	/**
	 * Set SphinxClient filter by forums ids
	 *
	 * @param array $forums
	 */
	public function setForumsfilter( array $forums )
	{
		if ( !empty( $forums ) )
			$this->client->SetFilter( 'forum_id', $forums );
	}



	/**
	 * Set SphinxClient filter for user permission
	 *
	 * @param array $&forums
	 *
	 */
	public function setPermsfilter( array &$forums )
	{
		global $db, $pun_user;

		// Get forums disallowed
		$result = $db->query('SELECT forum_id FROM '.$db->prefix.'forum_perms WHERE group_id='.$pun_user['g_id'].' AND read_forum = 0');
		$disallowed = array();
		while ($temp = $db->fetch_assoc($result))
			$disallowed[] = $temp['forum_id'];

		// Remove from $forums
		if ( !empty( $forums ) )
		{
			$forums_allowed = array();
			foreach ( $forums as $forum )
			{
				if ( !array_key_exists( $forum, $disallowed ) )
					$forums_allowed[] = $forum;
			}
			$forums = $forums_allowed;
		}

		// Exlude from Sphinx
		if ( !empty( $disallowed ) )
			$this->client->SetFilter( 'forum_id', $disallowed, true );
	}



	/**
	 * Set SphinxClient filter by authors ids
	 *
	 * @param array $forums
	 */
	public function setAuthorsfilter( array $user_ids )
	{
		if ( !empty( $user_ids ) )
			$this->client->SetFilter( 'poster_id', $user_ids );
	}



	/**
	 * Set SphinxClient SortBy
	 *
	 * @TODO Sort only the displayed elements
	 *
	 * @param int $sort_by
	 * @param string $sort_dir
	 * @param string $show_as
	 */
	public function setSortBy( $sort_by = -1, $sort_dir = 'DESC', $show_as = '' )
	{
		$this->sortDir = $sort_dir;

		switch ( $sort_by )
		{
			// Date
			case 0:
				$this->sortBy = 'posted';
				break;
			// Author
			case 1:
				$this->sortBy = 'poster_id';
				break;
			// Subject
			case 2:
				$this->sortBy = 'tid';
				break;
			// Forums
			case 3:
				$this->sortBy = 'forum_id';
				break;
			// Last post
			case 4:
				$this->sortBy = 'last_post';
				break;
			// Relevance
			default:
				$this->sortBy = '@relevance';
				break;
		}

		$this->client->SetSortMode( SPH_SORT_EXTENDED, $this->sortBy.' '.$this->sortDir );

		// GroupBy with sort
		if ( $show_as == 'topics' )
			$this->client->setGroupBy( 'tid', SPH_GROUPBY_ATTR, $this->sortBy.' '.$this->sortDir );
	}



	/**
	 * Do SphinxClient query
	 *
	 * @param string $keywords
	 * @param int $search_in
	 */
	public function query( $keywords, $search_in = 0 )
	{
		global $sphinx_config;

		// Search in message only
		if ( $search_in == 1 )
			$this->keywords = '@message '.$keywords;
		// Search in subject only
		else if ( $search_in == -1 )
			$this->keywords = '@subject '.$keywords;
		// Search in all (use weight)
		else
			$this->keywords = $keywords;

		// Do search
		$this->result = $this->client->query( $this->keywords, $sphinx_config['idx_main'].', '.$sphinx_config['idx_delta'] );
		//var_dump( $this->result );
	}





/* ********************************************************************* Misc */

	/**
	 * Convert a SphinxResult to search_ids array used by FluxBB
	 */
	public function toSearchIds ()
	{
		$search_ids = array();
		if ( is_array( $this->result['matches'] ) )
		{
			foreach( $this->result['matches'] as $matches )
			{
				$search_ids[$matches['attrs']['search_id']] = $matches['attrs']['tid'];
			}
		}

		return $search_ids;
	}



	/**
	 * Return Sphinx header informations
	 */
	public function resultHeader()
	{
		global $lang_sphinx, $pun_user;

		if ( !is_array( $this->result["words"] ) )
			return;

		if( !isset($lang_sphinx) AND isset($pun_user) )
			require PUN_ROOT.'lang/'.$pun_user['language'].'/sphinx.php';

		$return = '<div id="sphinx_header">' .
			'<p>' . sprintf ( $lang_sphinx['Search info'], $this->keywords, $this->result['total'], $this->result['total_found'], $this->result['time'] ) . '</p>' . "\n" .
			'<ul>';
		foreach ( $this->result["words"] as $word => $info )
			$return .= '<li>' . sprintf( $lang_sphinx['Search word info'], $word, $info[hits], $info[docs] ) .'</li>' . "\n";
		$return .= '</ul>' . "\n" . '</div>';

		return $return;
	}



	/**
	 * Return Sphinx footer informations
	 */
	public function resultFooter()
	{
		global $lang_sphinx, $pun_user;

		if( !isset($lang_sphinx) AND isset($pun_user) )
			require PUN_ROOT.'lang/'.$pun_user['language'].'/sphinx.php';

		return '<p id="sphinx_footer">' . sprintf( $lang_sphinx['Search powered by Sphinx'], $this->result['time'] ) . '</p>';
	}



	/**
	 * Text highlighter
	 *
	 * @param string $text
	 */
	public function highlight( &$text )
	{
		foreach ( $this->result['words'] as $word => $info )
		{
			$text = str_ireplace( $word, '<span class="highlight">'.$word.'</span>', $text);
		}
	}



	/**
	 * Write the anti-floood cookie
	 *
	 * @param string $hash
	 * @param int $expires
	 * @param string $key
	 */
	public static function setFloodCookie( $hash, $expires, $key = 'last_search')
	{
		forum_setcookie( $key, $hash, $expires );
	}
}

?>
