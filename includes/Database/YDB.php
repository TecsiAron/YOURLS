<?php

/**
 * Aura SQL wrapper for YOURLS that creates the allmighty YDB object.
 *
 * A fine example of a "class that knows too much" (see https://en.wikipedia.org/wiki/God_object)
 *
 * Note to plugin authors: you most likely SHOULD NOT use directly methods and properties of this class. Use instead
 * function wrappers (eg don't use $ydb->option, or $ydb->set_option(), use yourls_*_options() functions instead).
 *
 * @since 1.7.3
 */

namespace YOURLS\Database;

use \Aura\Sql\ExtendedPdo;
use \YOURLS\Database\Profiler;
use \YOURLS\Database\Logger;
use PDO;

class YDB extends ExtendedPdo {

    /**
     * Debug mode, default false
     */
    protected bool $debug = false;

    /**
     * Page context (ie "infos", "bookmark", "plugins"...)
     */
    protected string $context = '';

    /**
     * Information related to a short URL keyword (eg timestamp, long URL, ...)
     *
     *
     */
    protected array $infos = [];

    /**
     * Is YOURLS installed and ready to run?
     */
    protected bool $installed = false;

    /**
     * Options
     * @var string[]
     */
    protected array $option = [];

    /**
     * Plugin admin pages informations
     * @var array
     */
    protected $plugin_pages = [];

    /**
     * Plugin informations
     * @var string[]
     */
    protected $plugins = [];

    /**
     * Are we emulating prepare statements ?
     */
    protected bool $is_emulate_prepare;

    /**
     * @since 1.7.3
     * @param string $dsn         The data source name
     * @param string $user        The username
     * @param string $pass        The password
     * @param array  $options     Driver-specific options
     * @param array  $attributes  Attributes to set after a connection
     */
    public function __construct(string $dsn, string $user, string $pass, array $options, array $attributes) {
        parent::__construct($dsn, $user, $pass, $options, $attributes);
    }

    /**
     * Init everything needed
     *
     * Everything we need to set up is done here in init(), not in the constructor, so even
     * when the connection fails (eg config error or DB dead), the constructor has worked
     * and we have a $ydb object properly instantiated (and for instance yourls_die() can
     * correctly die, even if using $ydb methods)
     *
     * @since  1.7.3
     * @return void
     */
    public function init():void {
        $this->connect_to_DB();

        $this->set_emulate_state();

        $this->start_profiler();
    }

    /**
     * Check if we emulate prepare statements, and set bool flag accordingly
     *
     * Check if current driver can PDO::getAttribute(PDO::ATTR_EMULATE_PREPARES)
     * Some combinations of PHP/MySQL don't support this function. See
     * https://travis-ci.org/YOURLS/YOURLS/jobs/271423782#L481
     *
     * @since  1.7.3
     * @return void
     */
    public function set_emulate_state():void {
        try {
            $this->is_emulate_prepare = $this->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        } catch (\PDOException $e) {
            $this->is_emulate_prepare = false;
        }
    }

    /**
     * Get emulate status
     *
     * @since  1.7.3
     * @return bool
     */
    public function get_emulate_state():bool {
        return $this->is_emulate_prepare;
    }

    /**
     * Initiate real connection to DB server
     *
     * This is to check that the server is running and/or the config is OK
     *
     * @since  1.7.3
     * @return void
     * @throws \PDOException
     */
    public function connect_to_DB():void {
        try {
            $this->connect();
        } catch ( \Exception $e ) {
            $this->dead_or_error($e);
        }
    }

    /**
     * Die with an error message
     *
     * @since  1.7.3
     *
     * @param \Exception $exception
     *
     * @return void
     */
    public function dead_or_error(\Exception $exception):void {
        // Use any /user/db_error.php file
        $file = YOURLS_USERDIR . '/db_error.php';
        if(file_exists($file)) {
            if(yourls_include_file_sandbox( $file ) === true) {
                die();
            }
        }

        $message  = yourls__( 'Incorrect DB config, or could not connect to DB' );
        $message .= '<br/>' . get_class($exception) .': ' . $exception->getMessage();
        yourls_die( yourls__( $message ), yourls__( 'Fatal error' ), 503 );
        die();

    }

    /**
     * Start a Message Logger
     *
     * @since  1.7.3
     * @see    \Aura\Sql\Profiler\Profiler
     * @see    \Aura\Sql\Profiler\MemoryLogger
     * @return void
     */
    public function start_profiler():void {
        // Instantiate a custom logger and make it the profiler
        $yourls_logger = new Logger();
        $profiler = new Profiler($yourls_logger);
        $this->setProfiler($profiler);

        /* By default, make "query" the log level. This way, each internal logging triggered
         * by Aura SQL will be a "query", and logging triggered by yourls_debug() will be
         * a "message". See includes/functions-debug.php:yourls_debug()
         */
        $profiler->setLoglevel('query');
    }

    /**
     * @param string $context
     * @return void
     */
    public function set_html_context(string $context):void {
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function get_html_context():string {
        return $this->context;
    }

    // Options low level functions, see \YOURLS\Database\Options

    /**
     * @param string $name
     * @param mixed  $value
     * @return void
     */
    public function set_option(string $name, mixed $value) {
        $this->option[$name] = $value;
    }

    /**
     * @param  string $name
     * @return bool
     */
    public function has_option(string $name):bool {
        return array_key_exists($name, $this->option);
    }

    /**
     * @param  string $name
     * @return mixed
     */
    public function get_option(string $name):mixed {
        return $this->option[$name];
    }

    /**
     * @param string $name
     * @return void
     */
    public function delete_option($name):void {
        unset($this->option[$name]);
    }


    // Infos (related to keyword) low level functions

    /**
     * @param string $keyword
     * @param mixed  $infos
     * @return void
     */
    public function set_infos(string $keyword, mixed $infos):void {
        $this->infos[$keyword] = $infos;
    }

    /**
     * @param  string $keyword
     * @return bool
     */
    public function has_infos(string $keyword):bool {
        return array_key_exists($keyword, $this->infos);
    }

    /**
     * @param  string $keyword
     * @return array
     */
    public function get_infos(string $keyword):array {
        return $this->infos[$keyword];
    }

    /**
     * @param string $keyword
     * @return void
     */
    public function delete_infos(string $keyword):void {
        unset($this->infos[$keyword]);
    }

    /**
     * @todo: infos & options are working the same way here. Abstract this.
     */


    // Plugin low level functions, see functions-plugins.php

    /**
     * @return array
     */
    public function get_plugins():array {
        return $this->plugins;
    }

    /**
     * @param array $plugins
     * @return void
     */
    public function set_plugins(array $plugins):void {
        $this->plugins = $plugins;
    }

    /**
     * @param string $plugin  plugin filename
     * @return void
     */
    public function add_plugin(string $plugin):void {
        $this->plugins[] = $plugin;
    }

    /**
     * @param string $plugin  plugin filename
     * @return void
     */
    public function remove_plugin(string $plugin):void {
        unset($this->plugins[$plugin]);
    }


    // Plugin Pages low level functions, see functions-plugins.php

    /**
     * @return array
     */
    public function get_plugin_pages():array {
        return is_array( $this->plugin_pages ) ? $this->plugin_pages : [];
    }

    /**
     * @param array $pages
     * @return void
     */
    public function set_plugin_pages(array $pages):void {
        $this->plugin_pages = $pages;
    }

    /**
     * @param string   $slug
     * @param string   $title
     * @param callable $function
     * @return void
     */
    public function add_plugin_page(string $slug, string $title, callable $function ):void {
        $this->plugin_pages[ $slug ] = [
            'slug'     => $slug,
            'title'    => $title,
            'function' => $function,
        ];
    }

    /**
     * @param string $slug
     * @return void
     */
    public function remove_plugin_page(string $slug ):void {
        unset( $this->plugin_pages[ $slug ] );
    }


    /**
     * Return count of SQL queries performed
     *
     * @since  1.7.3
     * @return int
     */
    public function get_num_queries():int {
        return count( (array) $this->get_queries() );
    }

    /**
     * Return SQL queries performed
     *
     * @since  1.7.3
     * @return array
     */
    public function get_queries():array {
        $queries = $this->getProfiler()->getLogger()->getMessages();

        // Only keep messages that start with "SQL "
        $queries = array_filter($queries, function($query) {return substr( $query, 0, 4 ) === "SQL ";});

        return $queries;
    }

    /**
     * Set YOURLS installed state
     *
     * @since  1.7.3
     * @param  bool $bool
     * @return void
     */
    public function set_installed(bool $bool):void {
        $this->installed = $bool;
    }

    /**
     * Get YOURLS installed state
     *
     * @since  1.7.3
     * @return bool
     */
    public function is_installed():bool {
        return $this->installed;
    }

    /**
     * Return standardized DB version
     *
     * The regex removes everything that's not a number at the start of the string, or remove anything that's not a number and what
     * follows after that.
     *   'omgmysql-5.5-ubuntu-4.20' => '5.5'
     *   'mysql5.5-ubuntu-4.20'     => '5.5'
     *   '5.5-ubuntu-4.20'          => '5.5'
     *   '5.5-beta2'                => '5.5'
     *   '5.5'                      => '5.5'
     *
     * @since  1.7.3
     * @return string
     */
    public function mysql_version():string {
        $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        return $version;
    }

}
