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
	 * phinx is used ?
	 * @var boolean $use
	 */
	static public $use = false;
	/**
	 * SphinxClient
	 * @var SphinxClient $client
	 */
	private $client = null;
	/**
	 * Result
	 * @var array $result
	 */
	public $result = null;
	/**
	 * Keyword
	 * @var array $result
	 */
	public $keywords = '';



	/**
	 * Private constructor to prevent non-singleton use
	 */
	private function __construct ()
	{
		global $sphinx_config;

		// Load Sphinx
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

		// Setup SpĥinxClient
		$this->client = new SphinxClient;
		$this->client->setServer( $sphinx_config['host'], $sphinx_config['port'] );
		$this->client->setMatchMode( SPH_MATCH_EXTENDED );
		$this->client->setMaxQueryTime( $sphinx_config['max_query_time'] );
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
	 */
	public function setLimit( $show_as, $pun_user )
	{
		// Set limit
		$per_page = ($show_as == 'posts') ? $pun_user['disp_posts'] : $pun_user['disp_topics'];

		$p = (!isset($_GET['p']) || $_GET['p'] <= 1) ? 1 : intval($_GET['p']);
		$start_from = $per_page * ($p - 1);


		$this->client->setLimits( (int)$start_from, (int)$per_page );
	}



	/**
	 * Set SphinxClient filters
	 *
	 * @param array $forums
	 */
	public function setfilter( array $forums )
	{
		if ( !empty( $forums ) )
			$this->client->SetFilter( 'forum_id', $forums );
	}



	/**
	 * Set SphinxClient GroupBy value
	 *
	 * @param string $show_as
	 */
	public function setGroupBy( $show_as )
	{
		if ( $show_as == 'topics' )
			$this->client->setGroupBy( 'tid', SPH_GROUPBY_ATTR );
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

		// Search in field
		if ( $search_in == 1 )
			$this->keywords = '@message '.$keywords;
		else if ( $search_in == -1 )
			$this->keywords = '@subject '.$keywords;
		else
			$this->keywords = $keywords;

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
		foreach( $this->result['matches'] as $matches )
		{
			$search_ids[$matches['attrs']['search_id']] = $matches['attrs']['tid'];
		}

		return $search_ids;
	}



	/**
	 * Return Sphinx informations
	 */
	public function getSphinxresultInfo()
	{
		return 'Search "<strong>'.$this->keywords.'</strong>" in <a href="http://www.sphinxsearch.com/" title="Sphinx Search Engine">Sphinx</a> index in <em>'.$this->result['time'].'s</em>.';
	}

}

?>