<?php
/**
 * WordPress DB Class
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */

/**
 * WordPress Database Access Abstraction Object
 *
 * It is possible to replace this class with your own
 * by setting the $wpdb global variable in wp-content/db.php
 * file with your class. You can name it wpdb also, since
 * this file will not be included, if the other file is
 * available.
 *
 * @link http://codex.wordpress.org/Function_Reference/wpdb_Class
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */

require_once dirname(dirname(__FILE__)) . 
    DIRECTORY_SEPARATOR . 'translations' . 
    DIRECTORY_SEPARATOR . $pdo_type . 
    DIRECTORY_SEPARATOR . 'translations.php';

class pdo_wpdb extends SQL_Translations {

    /**
     * Saved result of the last translated query made
     *
     * @since 1.2.0
     * @access private
     * @var array
     */
    var $previous_query;
    
    /**
    * Database type
    *
    * @access public
    * @var string
    */
    var $db_type;
    
    /**
     * PDO Type
     *
     * @since 2.9.2
     * @access private
     * @var string
     */
    var $pdo_type;

    /**
     * Connects to the database server and selects a database
     *
     * PHP4 compatibility layer for calling the PHP5 constructor.
     *
     * @uses wpdb::__construct() Passes parameters and returns result
     * @since 0.71
     *
     * @param string $dbuser MySQL database user
     * @param string $dbpassword MySQL database password
     * @param string $dbname MySQL database name
     * @param string $dbhost MySQL database host
     */
    function wpdb( $dbuser, $dbpassword, $dbname, $dbhost, $pdo_type) {
        return $this->__construct( $dbuser, $dbpassword, $dbname, $dbhost, $pdo_type );
    }

    /**
     * Connects to the database server and selects a database
     *
     * PHP5 style constructor for compatibility with PHP5. Does
     * the actual setting up of the class properties and connection
     * to the database.
     *
     * @link http://core.trac.wordpress.org/ticket/3354
     * @since 2.0.8
     *
     * @param string $dbuser MySQL database user
     * @param string $dbpassword MySQL database password
     * @param string $dbname MySQL database name
     * @param string $dbhost MySQL database host
     */
    function __construct( $dbuser, $dbpassword, $dbname, $dbhost, $pdo_type ) {
        $this->pdo_type = $pdo_type;

        if(!extension_loaded('pdo')) {
            $this->bail('
<h1>Extension Not Loaded</h1>
<p>The pdo PHP extension is not loaded properly or available for PHP to use.</p>
<ul>
<li>Check your phpinfo</li>
<li>Make sure it is loaded in your php ini file</li>
<li>Turn on display_errors and display_startup_errors so you can detect issues with loading the module.</li>
</ul>');
            return;
        }

        if(!in_array($this->pdo_type, pdo_drivers())) {
            $this->bail('
<h1>PDO Driver Not Loaded</h1>
<p>The pdo PHP driver extension ' . $this->pdo_type . ' is not loaded properly or available for PHP to use.</p>
<ul>
<li>Check your phpinfo</li>
<li>Make sure it is loaded in your php ini file</li>
<li>Turn on display_errors and display_startup_errors so you can detect issues with loading the module.</li>
</ul>');
            return;
        }

        parent::__construct( $dbuser, $dbpassword, $dbname, $dbhost);
    }

    /**
     * Sets the connection's character set.
     *
     * @since 3.1.0
     *
     * @param resource $dbh     The resource given by mysql_connect
     * @param string   $charset The character set (optional)
     * @param string   $collate The collation (optional)
     */
    function set_charset($dbh, $charset = null, $collate = null) {
        /* NOTE: this SHOULD be handled by the pdo connection with both a charset=
          in the dsn and a set names init call, this is only here for completeness */
        if ($this->pdo_type == 'mysql') {
            if ( !isset($charset) )
                $charset = $this->charset;
            if ( !isset($collate) )
                $collate = $this->collate;
            if ( $this->has_cap( 'collation', $dbh ) && !empty( $charset ) ) {
                $query = $this->prepare( 'SET NAMES %s', $charset );
                if ( ! empty( $collate ) ) {
                    $query .= $this->prepare( ' COLLATE %s', $collate );
                    $dbh->query( $query );
                }
            }
        }
        // If we are not mysql, do nothing
    }

    /**
     * Set $this->charset and $this->collate
     *
     * @since 3.1.0
     */
    function init_charset() {
            if ( function_exists('is_multisite') && is_multisite() ) {
                    $this->charset = 'utf8';
                    if ( defined( 'DB_COLLATE' ) && DB_COLLATE )
                            $this->collate = DB_COLLATE;
            } elseif ( defined( 'DB_COLLATE' ) ) {
                    $this->collate = DB_COLLATE;
            }

            if ( defined( 'DB_CHARSET' ) )
                    $this->charset = DB_CHARSET;
    }

    /**
     * Selects a database using the current database connection.
     *
     * The database name will be changed based on the current database
     * connection. On failure, the execution will bail and display an DB error.
     *
     * @since 0.71
     *
     * @param string $db MySQL database name
     * @return null Always null.
     */
    function select( $db, $dbh = null ) {
        if ( is_null($dbh) )
            $dbh = $this->dbh;

        if ($dbh->dbname !== $db) {
            $this->ready = false;
            $this->bail( sprintf( /*WP_I18N_DB_SELECT_DB*/'
<h1>Can&#8217;t select database</h1>
<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
<ul>
<li>Are you sure it exists?</li>
<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
<li>On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?</li>
</ul>
<p>If you don\'t know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="http://wordpress.org/support/">WordPress Support Forums</a>.</p>'/*/WP_I18N_DB_SELECT_DB*/, $db, $this->dbuser ), 'db_select_fail' );
            return;
        }
    }

    /**
     * Weak escape, using addslashes()
     *
     * @see addslashes()
     * @since 2.8.0
     * @access private
     *
     * @param string $string
     * @return string
     */
    function _weak_escape( $string ) {
        if ($this->pdo_type == 'mysql') {
            return addslashes($string);
        } elseif ($this->pdo_type == 'sqlsrv') {
            return str_replace("'", "''", $string);
        } else {
            return $string;
        }
    }

    /**
     * Real escape, using mysql_real_escape_string() or addslashes()
     *
     * @see mysql_real_escape_string()
     * @see addslashes()
     * @since 2.8
     * @access private
     *
     * @param  string $string to escape
     * @return string escaped
     */
    function _real_escape( $string ) {
        if ($this->pdo_type == 'mysql') {
            if ( $this->dbh && $this->real_escape ) {
                return mysql_real_escape_string( $string, $this->dbh );
            } else {
                return addslashes( $string );
            }
        } elseif ($this->pdo_type == 'sqlsrv') {
            return str_replace("'", "''", $string);
        } else {
            return $string;
        }
    }

    /**
     * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
     *
     * The following directives can be used in the query format string:
     *   %d (decimal number)
     *   %s (string)
     *   %% (literal percentage sign - no argument needed)
     *
     * Both %d and %s are to be left unquoted in the query string and they need an argument passed for them.
     * Literals (%) as parts of the query must be properly written as %%.
     *
     * This function only supports a small subset of the sprintf syntax; it only supports %d (decimal number), %s (string).
     * Does not support sign, padding, alignment, width or precision specifiers.
     * Does not support argument numbering/swapping.
     *
     * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
     *
     * Both %d and %s should be left unquoted in the query string.
     *
     * <code>
     * wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
     * wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
     * </code>
     *
     * @link http://php.net/sprintf Description of syntax.
     * @since 2.3.0
     *
     * @param string $query Query statement with sprintf()-like placeholders
     * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
     *  {@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
     *  being called like {@link http://php.net/sprintf sprintf()}.
     * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
     *  {@link http://php.net/sprintf sprintf()}.
     * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
     *  if there was something to prepare
     */
    function prepare( $query = null ) { // ( $query, *$args )

        if ( is_null( $query ) ) {
            return;
        }
        $this->prepare_args = func_get_args();
        array_shift($this->prepare_args);
        // If args were passed as an array (as in vsprintf), move them up
        if ( isset($this->prepare_args[0]) && is_array($this->prepare_args[0]) ) {
            $this->prepare_args = $this->prepare_args[0];
        }
        $flag = '--PREPARE';
        foreach($this->prepare_args as $key => $arg){
            if (is_serialized($arg)) {
                $flag = '--SERIALIZED';
            }
        }
        if ($this->pdo_type == 'mysql') {
            $flag = '';
        }
        if ($this->pdo_type == 'sqlsrv') {
            $prefix = ' N';
        } else {
            $prefix = '';
        }
        $query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
        $query = str_replace('"%s"', '%s', $query); // doublequote unquoting
        $query = preg_replace( '|(?<!%)%s|', "$prefix'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
        array_walk($this->prepare_args, array(&$this, 'escape_by_ref'));
        return @vsprintf($query, $this->prepare_args).$flag;
    }

    /**
     * Print SQL/DB error.
     *
     * @since 0.71
     * @global array $EZSQL_ERROR Stores error information of query and error string
     *
     * @param string $str The error to display
     * @return bool False if the showing of errors is disabled.
     */
    function print_error( $str = '' ) {
        global $EZSQL_ERROR;

        if ( !$str ) {
            $error = $this->dbh->errorInfo();
            $str = $error[2];
        }
        $EZSQL_ERROR[] = array( 'query' => $this->previous_query, 'error_str' => $str );

        if ( $this->suppress_errors )
            return false;

        if ( $caller = $this->get_caller() )
            $error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR_FULL*/'WordPress database error %1$s for query %2$s made by %3$s'/*/WP_I18N_DB_QUERY_ERROR_FULL*/, $str, $this->previous_query, $caller );
        else
            $error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR*/'WordPress database error %1$s for query %2$s'/*/WP_I18N_DB_QUERY_ERROR*/, $str, $this->previous_query );

        if ( function_exists( 'error_log' )
            && ( $log_file = @ini_get( 'error_log' ) )
            && ( 'syslog' == $log_file || @is_writable( $log_file ) )
            )
            @error_log( $error_str );

        // Are we showing errors?
        if ( ! $this->show_errors )
            return false;

        // If there is an error then take note of it
        if ( is_multisite() ) {
            $msg = "WordPress database error: [$str]\n{$this->previous_query}\n";
            if ( defined( 'ERRORLOGFILE' ) )
                error_log( $msg, 3, ERRORLOGFILE );
            if ( defined( 'DIEONDBERROR' ) )
                wp_die( $msg );
        } else {
            $str   = htmlspecialchars( $str, ENT_QUOTES );
            $query = htmlspecialchars( $this->previous_query, ENT_QUOTES );

            print "<div id='error'>
            <p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
            <code>$query</code></p>
            </div>";
        }
    }

    /**
     * Connect to and select database
     *
     * @since 3.0.0
     */
    function db_connect() {

        if (get_magic_quotes_gpc()) {
            $this->dbhost = trim(str_replace("\\\\", "\\", $this->dbhost));
        }

        $dsn = array();

        /* Specify the server and connection string attributes. */
        $pdo_attributes = array();

        if ($this->pdo_type == 'mysql') {
            if (stristr($this->dbhost, ':')) {
                $dbhost_parts = explode(':', $this->dbhost);
                $dsn[] = 'host=' . $dbhost_parts[0] . ';port=' . $dbhost_parts[1];
            } else {
                $dsn[] = 'host=' . $this->dbhost;
            }
            $dsn[] = 'dbname=' . $this->dbname;
            $dsn[] = 'charset=' . $this->charset;
            $dsn = $this->pdo_type . ':' . implode(';', $dsn);
        } else {
            $dsn = $this->pdo_type . ':Server=' . $this->dbhost . ';Database=' . $this->dbname;
        }

        // Is this SQL Azure?
        if (stristr($this->dbhost, 'database.windows.net') !== false) {
            // Need to turn off MultipleActiveResultSets, this requires
            // Sql Server Driver for PHP 1.1+ (1.0 doesn't support this property)
            $dsn .= ';MultipleActiveResultSets=false'; 
            $this->azure = true;
        }

        try {
            $this->dbh = new PDO($dsn, $this->dbuser, $this->dbpassword, $pdo_attributes);
        } catch (Exception $e) {
            if (WP_DEBUG) {
                throw $e;
            }
        }

        if (!isset($this->dbh) || !is_object($this->dbh)) {
            $this->bail( sprintf( /*WP_I18N_DB_CONN_ERROR*/"
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
    <li>Are you sure you have the correct username and password?</li>
    <li>Are you sure that you have typed the correct hostname?</li>
    <li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
"/*/WP_I18N_DB_CONN_ERROR*/, $this->dbhost ), 'db_connect_fail' );
            return;
        }

        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->dbh->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);
        // We keep error mode on silent or else we get a lot of barking during installation
        // because wordpress tries to select from tables that don't exist to see whether
        // installation is necessary or not.
        //$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $this->ready = true;
    }

    /**
     * Perform a MySQL database query, using current database connection.
     *
     * More information can be found on the codex page.
     *
     * @since 0.71
     *
     * @param string $query Database query
     * @return int|false Number of rows affected/selected or false on error
     */
    function query( $query, $translate = true ) {

        if ( ! $this->ready )
            return false;

        // some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
        if ( function_exists( 'apply_filters' ) )
            $query = apply_filters( 'query', $query );

        $return_val = 0;
        $this->flush();

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";

        // Keep track of the last query for debug..
        $this->last_query = $query;

        $dbh = $this->dbh;

        // Make Necessary Translations
        if ($translate === true && $this->pdo_type != 'mysql') {
            $query = $this->translate($query);
            if ($query == '') {
                return false;
            }

            $this->previous_query = $query;

            if ($this->preceeding_query !== false) {
                if (is_array($this->preceeding_query)) {
                    foreach ($this->preceeding_query as $p_query) {
                        $dbh->exec($p_query);
                    }
                } else {
                    $dbh->exec($this->preceeding_query);
                }
                $this->preceeding_query = false;
            }
            
            // Check if array of queries (this happens for INSERTS with multiple VALUES blocks)
            if (is_array($query)) {
                foreach ($query as $sub_query) {
                    $this->_pre_query();
                    $this->result = $dbh->query($sub_query);
                    $return_val = $this->_post_query($dbh, $sub_query);
                }
            } else {
                $this->_pre_query();
                $this->result = $dbh->query($query);
                $return_val = $this->_post_query($dbh, $query);
            }

            if ($this->following_query !== false) {
                if (is_array($this->following_query)) {
                    foreach ($this->following_query as $f_query) {
                        $dbh->exec($f_query);
                    }
                } else {
                    $dbh->exec($this->following_query);
                }
                $this->following_query = false;
            }
        } else {
            $this->_pre_query();
            $this->result = $dbh->query($query);
            $return_val = $this->_post_query($dbh, $query);
        }

        return $return_val;
    }

    function _pre_query() {
        if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
            $this->timer_start();
        }
    }

    function _post_query($dbh, $query) {
        ++$this->num_queries;

        if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
            $this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
        }
        
        // If there is an error then take note of it..
        if ( $this->result == FALSE ) {
            $arr = $dbh->errorInfo();
            $this->log_query($arr);
            $this->last_error = $arr[0] . ' : ' . $arr[2];
            if ($this->last_error != '') {
                //echo "<pre>";
                //var_dump($arr,$query);
                //var_dump($this->translation_changes);
                //echo "</pre>";
                $this->print_error($this->last_error);
            }
            return false;
        }
        
        if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {
            $this->rows_affected = $this->result->rowCount();

            // Take note of the insert_id
            if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
                $this->insert_id = $dbh->lastInsertId();
            }

            $return_val = $this->rows_affected;
        } elseif ( preg_match("/^\\s*(set|create|alter) /i",$query) ) {
            $this->rows_affected = 0;
            $return_val = $this->rows_affected;
        } else {
            if ($dbh->errorCode() == '42S02') {
                $this->rows_affected = 0;
                $return_val = $this->rows_affected;
                break;
            }
            $i = 0;
            while ($i < $this->result->columnCount()) {
                $field = $this->result->getColumnMeta($i);
                if ($field['pdo_type'] == PDO::PARAM_LOB) {
                    $type = 'text';
                } elseif ($field['pdo_type'] == PDO::PARAM_STR) {
                    $type = 'char';
                } elseif ($field['pdo_type'] == PDO::PARAM_INT) {
                    $type = 'numeric';
                } else {
                    $type = $field['pdo_type'];
                }
                $new_field = new stdClass();
                $new_field->name = $field['name'];
                $new_field->table = $field['name'];
                $new_field->def = null;
                $new_field->max_length = $field['len'];
                $new_field->not_null = true;
                $new_field->primary_key = null;
                $new_field->unique_key = null;
                $new_field->multiple_key = null;
                $new_field->numeric = $field['precision'];
                $new_field->blob = null;
                $new_field->type = $type;
                $new_field->unsigned = null;
                $new_field->zerofill = null;
                $this->col_info[$i] = $new_field;
                $i++;
            }

            $this->last_result = $this->result->fetchAll(PDO::FETCH_OBJ);
            $num_rows = count($this->last_result);

            if ($this->pdo_type != 'mysql') {
                $this->last_result = $this->fix_results($this->last_result);
                // perform limit
                if (!empty($this->limit)) {
                    $this->last_result = array_slice($this->last_result, $this->limit['from'], $this->limit['to']);
                    $num_rows = count($this->last_result);
                }
            }

            $this->result = null;

            // Log number of rows the query returned
            $this->num_rows = $num_rows;

            // Return number of rows selected
            $return_val = $this->num_rows;
        }

        $this->log_query();
        return $return_val;
    }

    function log_query($error = null)
    {
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return; //bail
        }

        if (!defined('QUERY_LOG')) {
            $log = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'queries.log';
        } else {
            $log = QUERY_LOG;
        }

        if (!empty($this->queries)) {
            $last_query = end($this->queries);
            if (preg_match( "/^\\s*(insert|delete|update|replace|alter) /i", $last_query[0])) {
                $result = serialize($this->rows_affected);
            } else {
                $result = serialize($this->last_result);
            }
            if (is_array($error)) {
                $error = serialize($error);
            }
            $q = str_replace("\n", ' ', $last_query[0]);
            file_put_contents($log, $q . '|~|' . $result . '|~|' . $error . "\n", FILE_APPEND);
        }
    }

    /**
     * The database version number.
     *
     * @return false|string false on failure, version number on success
     */
    function db_version() {
        if ($this->pdo_type == 'mysql') {
            try {
                return $this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Exception $e) {
                return '5.1'; // what WP expects
            }
        } else {
            return '5.1';
        }
    }
}
