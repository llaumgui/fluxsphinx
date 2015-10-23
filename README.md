# FluxSphinx
FluxSphinx is an implementation of the [Sphinx](http://sphinxsearch.com/) Search Engine for the [FluxBB](http://fluxbb.org/) forum.

FluxSphinx aims to provide an alternernative for the native FluxBB's search engine and provides a fulltext search for high-volume forums.

## Requirements
* PHP 5.3.3 or newer
* Sphinx 2.0 or newer
* FluxBB 1.4.x

## Installation

### Step 1: Install Sphinx
[Install Sphinx](http://sphinxsearch.com/docs/current.html#installation) on your server (check if a package is avalaible for your distro).

### Step 2: Prepare your FluxBB database
Add the FluxSphinx tables in your FluxBB databases, __be careful, check the table prefix__:

```sql
CREATE TABLE IF NOT EXISTS `fluxbb_search_sphinx` (
  `action` varchar(64) NOT NULL DEFAULT '',
  `param` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `search_sphinx_action` (`action`),
  KEY `search_sphinx_created` (`created`)
);
INSERT INTO `fluxbb_search_sphinx` (`action`, `param`) VALUES ('counter_posts', 0);
INSERT INTO `fluxbb_search_sphinx` (`action`, `param`) VALUES ('tmp_counter_posts', 0);
```

### Step 3: Setup Sphinx
* Apply configuration (sphinx/sphinx.conf).
* Creating the index:

```bash
/usr/bin/indexer --config /etc/sphinx/sphinx.conf --all --rotate
```

* Starting the search daemon:

```bash
/usr/bin/searchd --config /etc/sphinx/sphinx.conf
```
OR
```bash
/etc/init.d/searchd start
```

### Step 4: Configure Cronjobs
```bash
3,18,33,48 * * * * /usr/bin/indexer --quiet --config /etc/sphinx/sphinx.conf fluxbb_search_posts_delta --rotate >/dev/null 2>&1
30 4 * * * /usr/bin/indexer --quiet --config /etc/sphinx/sphinx.conf fluxbb_search_posts_main --rotate >/dev/null 2>&1; /usr/bin/indexer --quiet --config /etc/sphinx/sphinx.conf fluxbb_search_posts_delta --rotate >/dev/null 2>&1
```

### Step 5: Setup FluxBB
* Install SphinxClient from [Sphinx API PHP](https://github.com/sphinxsearch/sphinx/blob/master/api/sphinxapi.php) (put on include folder) or [from PECL](http://pecl.php.net/package/sphinx).
* Add Sphinx configuration to your FluxBB's config.php:

```php
// Sphinx
$sphinx_config = array(
    'host' => 'localhost', // Your sphinx server host
    'port' => 3312, // Your sphinx server port
    'idx_main' => 'fluxbb_search_posts_main', // Main search index
    'idx_delta' => 'fluxbb_search_posts_delta', // Delta search index
    'max_query_time' => 5000, // Timeout search queries after 5 seconds.
    'weights' => array( 'message' => 10, 'subject' => 40 ), // Sets the weighting of certain columns
    'use_pecl' => true // Use C client from PECL
);
```

* Add fluxbb/include/sphinx.php to __include__ folder.
* Add fluxbb/lang to __lang__ folder.
* Replace __search.php__ by fluxbb/search.php.