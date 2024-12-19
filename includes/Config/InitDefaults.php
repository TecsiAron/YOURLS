<?php

/**
 * YOURLS defaut actions upon instantiating
 *
 * This class defines all the default actions to be performed when instantiating YOURLS. The idea
 * is that this is easily tuneable depending on the scenario, namely when running YOURLS for
 * unit tests.
 *
 * @see \YOURLS\Config\Init
 */

namespace YOURLS\Config;

class InitDefaults {

    /**
     * Whether to include core function files
     */
    public bool $include_core_funcs = true;

    /**
     * Whether to set default time zone
     */
    public bool $default_timezone = true;

    /**
     * Whether to load default text domain
     */
    public bool $load_default_textdomain = true;

    /**
     * Whether to check for maintenance mode and maybe die here
     */
    public bool $check_maintenance_mode = true;

    /**
     * Whether to fix $_REQUEST for IIS
     */
    public bool $fix_request_uri = true;

    /**
     * Whether to redirect to SSL if needed
     */
    public bool $redirect_ssl = true;

    /**
     * Whether to include DB engine
     */
    public bool $include_db = true;

    /**
     * Whether to include cache layer
     */
    public bool $include_cache = true;

    /**
     * Whether to end instantiating early if YOURLS_FAST_INIT is defined and true
     */
    public bool $return_if_fast_init = true;

    /**
     * Whether to read all options at once during starting
     */
    public bool $get_all_options = true;

    /**
     * Whether to register shutdown action
     */
    public bool $register_shutdown = true;

    /**
     * Whether to trigger action 'init' after core is loaded
     */
    public bool $core_loaded = true;

    /**
     * Whether to redirect to install procedure if needed
     */
    public bool $redirect_to_install = true;

    /**
     * Whether to redirect to upgrade procedure if needed
     */
    public bool $check_if_upgrade_needed = true;

    /**
     * Whether to load all plugins
     */
    public bool $load_plugins = true;

    /**
     * Whether to trigger the "plugins_loaded" action
     */
    public bool $plugins_loaded_action = true;

    /**
     * Whether to check if a new version if available
     */
    public bool $check_new_version = true;

    /**
     * Whether to trigger 'admin_init' if applicable
     */
    public bool $init_admin = true;

}
