<?php
/*
  Plugin Name: ShrinkTheWeb Refresh All
  Version: 1.1
  Plugin URI:
  Author: Sabirul Mostofa
  Author URI: https://github.com/sabirmostofa
  Description: Refresh all the STW screenshots  at once
  Tags: homepage,website,thumbnail,thumbnails,thumb,screenshot,snapshot,link,links,images,image
  License: GPLv2
 */
 
 /*
  Copyright 2013 ShrinkTheWeb

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


$stwRefreshAll = new stwRefreshAll();

class stwRefreshAll {

    public $has_plugin = false;
    public $has_portfolio_plugin = false;
    public $has_theme = false;
    public $upload_dir = '';
    //log files
    public $f_links = '';
    public $f_clinks = '';
    public $table = '';
    //links to refresh in a cycle
    public $limit = 15;
    public $logo = '';

    function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'stw_links';
        add_action('plugins_loaded', array($this, 'init_vars'));
        add_action('plugins_loaded', array($this, 'do_refresh_rest'));
        add_action('wpstw_cron', array($this, 'start_refresh'));
        add_action('stw_cron_cache', array($this, 'delete_cache'));
        //add_action('init', array($this, 'refresh_action'));	
        add_action('admin_menu', array($this, 'CreateMenu'), 50);
        add_filter('cron_schedules', array($this, 'cron_scheds'));
        register_activation_hook(__FILE__, array($this, 'create_table'));
    }

    function create_table() {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS $this->table  (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,		
		`url` varchar(200)  NOT NULL,
		 `args` text not null,
		 `last` int(12)	not null default 0,
		 PRIMARY KEY (`id`),	
		 unique key `url_args` (`url`, args(100))		 	
		)";


        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
    }

    function CreateMenu() {
        add_submenu_page('options-general.php', 'STW Refresh All', 'STW Refresh All', 'activate_plugins', 'stwRefreshAll', array($this, 'OptionsPage'));
    }

    //schedule cron events
    function schedule_cron() {
        $opt = get_option('stw_cron_option');
        switch ($opt):
            case 'every_day':
                if (wp_get_schedule('wpstw_cron') != 'daily') {
                    wp_clear_scheduled_hook('wpstw_cron');
                    wp_schedule_event(time(), 'daily', 'wpstw_cron');
                }
                break;
            case 'every_week':
                if (wp_get_schedule('wpstw_cron') != 'every_week') {
                    wp_clear_scheduled_hook('wpstw_cron');
                    wp_schedule_event(time(), 'every_week', 'wpstw_cron');
                }
                break;

            case 'every_month':
                if (wp_get_schedule('wpstw_cron') != 'every_month') {
                    wp_clear_scheduled_hook('wpstw_cron');
                    wp_schedule_event(time(), 'every_month', 'wpstw_cron');
                }
                break;
            case 'every_quarter':
                if (wp_get_schedule('wpstw_cron') != 'every_quarter') {
                    wp_clear_scheduled_hook('wpstw_cron');
                    wp_schedule_event(time(), 'every_quarter', 'wpstw_cron');
                }
                break;


        endswitch;
    }

    function OptionsPage() {
        global $wpdb;
        //var_dump(wp_get_schedule('wpstw_cron'));
        //wp_clear_scheduled_hook('wpstw_cron');		
        //var_dump(wp_get_schedules());
        //var_dump(wp_next_scheduled('stw_cron_cache'));
        // if cron option is changed reschedule cron
        if (isset($_POST["stw_cron_option"])) {
            update_option('stw_cron_option', $_POST["stw_cron_option"]);
            $this->schedule_cron();
        }



        $ro = get_option('stw_cron_option');
        ?>
        <div class="wrap" style="text-align:center">
			<a href="http://www.shrinktheweb.com/">
			<img src="<?php echo plugins_url( 'images/stw_logo.jpg' , __FILE__ ) ?>" />
			</a>
			<br/>
			<br/>
			<div style="width: 500px;margin: 0 auto; color: green; border: 2px solid #C0C0C0">
			<?php
			if($this -> has_plugin)
				echo '<b>STW Plugin detected</span></b><br/>';
			if($this -> has_theme)
				echo '<b>Directorypress detected</b><br/>';
			if($this -> has_portfolio_plugin)
				echo '<b>Portfolio Plugin detected</b>';
			
			?>
			</div>
			<br/>
            <h3>Select an option to refresh all the Thumbails from Directorypress and STW plugin </h3> 
            <form action="" method="post" >
                <select name="stw_cron_option" id="">
                    <option value="every_day"  <?php if ($ro == 'every_day') echo 'selected="selected"'; ?>>Every Day</option>
                    <option value="every_week" <?php if ($ro == 'every_week') echo 'selected="selected"'; ?> >Every Week</option>
                    <option value="every_month" <?php if ($ro == 'every_month') echo 'selected="selected"'; ?> >Every Month</option>
                    <option value="every_quarter" <?php if ($ro == 'every_quarter') echo 'selected="selected"'; ?> >Every Quarter</option>
                    <input class="button-primary" type="submit" value="Save" />
                </select>
            </form>

            <form action="" method ="post">
                <input type="hidden" name="stw_nonce_start" value="<?php echo wp_create_nonce('stw_back_nonce') ?>" />
                <br/>
                <h3> Click the button to refresh all the Thumbnails now. </h3> 
                <input class="button-primary" type ="submit" name="refresh_now" value="Refresh Now"/>

            </form>

            <form action="" method ="post">
                <input type="hidden" name="stw_nonce_start" value="<?php echo wp_create_nonce('stw_back_nonce') ?>" />
                <br/>
                <h3> Click the button to clear the cache</h3> 
                <input class="button-primary" type ="submit" name="clear_cache" value="Clear Cache"/>

            </form>



        <?php
        // start a refresh request
        if (isset($_POST['refresh_now'])) {
			if(!$this->has_plugin && !$this->has_theme){
				echo "<br/><b> Neither STW Plugin nor Directorypress is activated </b> <br/> No thumbnails to refresh!";
				return;
			}
			
			if(!$this->has_stw_credential()){
				echo "<br/><b> STW Access key or Secret isn't set in STW plugin or Directorypress theme </b> <br/> ";
				return;
			}

			
            if (wp_verify_nonce($_POST['stw_nonce_start'], 'stw_back_nonce'))
                $this->start_refresh();
            $recs = $wpdb->get_var("select count(*) from $this->table");

				
				
            echo "<br/><b>Total Thumbnails to be refreshed: $recs</b> <br/> The refresh action will be running in the background. 
            <br/> Cache will be deleted automatically after the completion of the refresh process";
        }
        
        if(isset($_POST['clear_cache'])){
			$this->delete_cache();
			echo "<br/><b>All cached images habe been deleted";
			
			
			}

        echo "</div>";
    }
    
    function has_stw_credential(){
		//var_dump(SECRET_KEY);
		
		if($this->has_plugin_credential())
				return true;
		
		if($this ->has_theme){
			if( (ACCESS_KEY  == '') || (SECRET_KEY == '') ){
				// try to get from plugin
				if($this->has_plugin_credential())
					return true;
				else
					return false;
			}else{
				return true;
				}
		}

	 
			
		
		}
		
		function has_plugin_credential(){
			
			if($this->has_plugin){		
				$allSettings = TidySettings_getSettings(STWWT_SETTINGS_KEY);
				if(($allSettings['stwwt_access_id'] == false ) || ($allSettings['stwwt_secret_id'] == '' ))
					return false;
				else 
					return true;
			}
			
			return false;	 

			
			}

    //cron schedules

    function cron_scheds($cron_schedules) {
        $cron_schedules['every_week'] = array(
            'interval' => 604800,
            'display' => __('every seven days')
        );

        $cron_schedules['every_month'] = array(
            'interval' => 604800 * 4,
            'display' => __('every month')
        );

        $cron_schedules['every_quarter'] = array(
            'interval' => 604800 * 16,
            'display' => __('every quarter')
        );

        return $cron_schedules;
    }

    // initialize public variables
    function init_vars() {

        $this->upload_dir = WP_CONTENT_DIR . '/uploads/';
        $plugin_dir = dirname(__FILE__);
        $this->f_links = $plugin_dir  . '/stw_links.txt';
        $this->f_clinks = $plugin_dir  . '/stw_clinks.txt';
        $this->logo = $plugin_dir . '/images/stw_logo.jpg';


        if (defined('STWWT_VERSION'))
            $this->has_plugin = true;
            
        if(function_exists('WPPortfolio_init'))
			$this->has_portfolio_plugin = true;

        $theme = wp_get_theme();

        if ($theme->Name == 'directorypress') {
            $this->has_theme = true;


            //theme vars
            $IMAGEVALUES = get_option('pptimage'); // 1 ARRAY STORES ALL VALUES
            // STOP ERROR LOGS FOR CHECKBOX VALUES
            if (!isset($IMAGEVALUES['stw_1'])) {
                $IMAGEVALUES['stw_1'] = "";
            }
            if (!isset($IMAGEVALUES['stw_12'])) {
                $IMAGEVALUES['stw_12'] = "";
            }

            defined('ACCESS_KEY') || define('ACCESS_KEY', $IMAGEVALUES['STW_access_key']);
            defined('SECRET_KEY') || define('SECRET_KEY', $IMAGEVALUES['STW_secret_key']);
            defined('THUMBNAIL_URI') || define('THUMBNAIL_URI', get_option("imagestorage_link"));
            defined('THUMBNAIL_DIR') || define('THUMBNAIL_DIR', get_option("imagestorage_path"));
            defined('INSIDE_PAGES') || define('INSIDE_PAGES', $IMAGEVALUES['stw_1']); // set to true if inside capturing should be allowed
            defined('CUSTOM_MSG_URL') || define('CUSTOM_MSG_URL', $IMAGEVALUES['stw_12']); // i.e. 'http://yourdomain.com/path/to/your/custom/msgs'
            defined('CACHE_DAYS') || define('CACHE_DAYS', $IMAGEVALUES['stw_11']);
        }
    }

// end of init var
    //to be called only from the plugin
    function start_refresh() {

        //clear log file
       @$handle = fopen($this->f_links, "w");
        @fwrite($handle, "");
        @fclose($handle);

        global $wpdb;
        //clear cache and db
        //generate links in db 
        update_option('stw_doing_refresh', '0');
        if (get_option('stw_doing_refresh') != 1) {
            $wpdb->show_errors();
            $wpdb->query("Delete from $this->table where 1 ");

            //reset index |wordpress doesn't allow so default queries
            $d = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
            mysql_query("SET @num := 0;", $d);
            mysql_query("UPDATE $this->table SET id = @num := (@num+1)", $d);
            mysql_query("ALTER TABLE $this->table AUTO_INCREMENT = 1", $d);
            //mysql_close($d);
            //dbDelta("truncate table $this->table");
			//delete cache at end
           // $this->delete_cache();
            //$wpdb -> print_error();	
            $this->find_insert_db();

            //send a second request to the server			
            $this->send_refresh_request();
        }
    }

    // function called with init hook
    function do_refresh_rest() {

        //$vn = (int) wp_verify_nonce( $_REQUEST['stw_refresh_nonce'], 'stw_refresh_nonce' )
        if (defined('DOING_CRON') || isset($_GET['doing_wp_cron']) || isset($_REQUEST['stw_refresh_nonce'])) {

            //var_dump($_POST);
            if (get_option('stw_doing_refresh') == 1) {
                //var_dump($_POST);
                ignore_user_abort(true);
                set_time_limit(0);

                $max = ini_get('max_execution_time');
                if ($max != 0)
                    $limit = $this->limit;
                else
                    $limit = false;






                $this->start_cycle_request($limit);
            }
        }
    }

    function start_cycle_request($limit) {

        $count = 0;
        global $wpdb;
        $query = "select * from {$this->table} where last=0 ";

        if ($limit)
            $query .= "limit $limit";
        $recs = $wpdb->get_results($query);

        if (empty($recs)) {
            update_option('stw_doing_refresh', '0');

            
            if (wp_next_scheduled('stw_cron_cache')) 
				wp_clear_scheduled_hook('stw_cron_cache');
				
		// avoiding wordpress limit 10 minutes
		wp_schedule_single_event( time() + 660, 'stw_cron_cache');
            
            exit;
        }
        foreach ($recs as $single) {
            $res = $this->stw_refresh_request($single->url, $single->args);
            if ($res) {
                $wpdb->update(
                        $this->table, array('last' => 1), array('id' => $single->id)
                );
            }
        }
        $this->send_refresh_request();

        //exit if from cron or continue if refreshing now
        if (!isset($_POST['refresh_now']))
            exit();
    }

    function stw_refresh_request($url, $args) {
        $args = json_decode($args, true);
        $args['stwurl'] = $url;
        //var_dump($this -> f_links);

        @$handle = fopen($this->f_links, "a");
        @fwrite($handle, $url);
        @fwrite($handle, "\r\n");
        @fclose($handle);

// finally do request
        $fetchURL = urldecode("http://images.shrinktheweb.com/xino.php?" . http_build_query($args));

        $resp = wp_remote_get($fetchURL, array(
            'timeout' => 2));



        if (!is_wp_error($resp))
            return true;

        return false;
    }

    // send refresh request to self
    function send_refresh_request() {
        update_option('stw_doing_refresh', '1');
        $r = 1;
        $site_url = get_site_url();
        $args = array();
        if (isset($_REQUEST['doing_refresh'])) {
            $r = (int) $_REQUEST['doing_refresh'];
            $r = $r + 1;
        }

        $args['doing_refresh'] = $r;
        if (isset($_REQUEST['stw_refresh_nonce']))
            $args['stw_refresh_nonce'] = $_REQUEST['stw_refresh_nonce'];
        else
            $args['stw_refresh_nonce'] = wp_create_nonce('stw_refresh_nonce');

        $fetchURL = urldecode($site_url . '?' . http_build_query($args));
        //var_dump($fetchURL);
        $resp = wp_remote_get($fetchURL, array(
            'timeout' => 1));

        // var_dump($resp);

        if (!isset($_POST['refresh_now']))
            exit;

        return;
    }

    //collect links and insert in db
    function find_insert_db() {
        global $wpdb;
        $links_theme = array();
        $links_plugin = array();
        $links_portfolio = array();

        // get all directorypress urls
        if($this -> has_theme)
        $links_theme = $wpdb->get_col($wpdb->prepare(
                        "
	SELECT      Distinct(meta_value) as meta_value
	FROM        $wpdb->postmeta
	where       meta_key=%s

	", 'url'
                )
        );

//var_dump($urls); 
//exit;
        // get all plugin urls only if plugin exists
if($this->has_plugin):
        $contents = $wpdb->get_col($wpdb->prepare(
                        "
	SELECT      post_content
	FROM        $wpdb->posts
	where       post_status=%s

	", 'publish'
                )
        );


        

//searching for the thumb shortcode
        foreach ($contents as $con) {
            $attr = false;

            //fastest way to search
            if (stripos($con, '[/thumb]') === false)
                continue;

            preg_match_all('~\[thumb([^\[\]]*)]([^\[\]]+)\[/thumb]~', $con, $r);

            foreach ($r[1] as $key => $val) {
                $link = $r[2][$key];
                $attr = shortcode_parse_atts($val);
                $links_plugin[] = array($link, $attr);
            }
        }//exit;

        $links_plugin = array_map("unserialize", array_unique(array_map("serialize", $links_plugin)));
    endif;
    
    // start saearch for portfolio links
    
    if($this->has_portfolio_plugin):
    $table = $wpdb->prefix . TABLE_WEBSITES;
    
    $links_portfolio = $wpdb->get_col(
                        "
	SELECT      siteurl
	FROM        $table
	"
	   );
 
    endif;

        //var_dump($links_plugin);
        //$this->refresh_request(array($links_theme, $links_plugin));
 
        $this->write_in_db(array($links_theme, $links_plugin, $links_portfolio));
    }

    // write all the urls in file
    function write_in_db($links) {
        global $wpdb;




        $links_theme = $links[0];
        $links_plugin = $links[1];
        $links_portfolio = $links[2];
        //returns without urls
        $args_theme = $this->theme_get_args();




        if (is_array($links_theme))
            foreach ($links_theme as $l) {
                $args_theme['stwurl'] = $l;
                $json_args = json_encode($args_theme);

                $wpdb->insert(
                        $this->table, array(
                    'url' => $l,
                    'args' => $json_args,
                    'last' => 0
                        ), array('%s', '%s', '%d')
                );
            }




        foreach ($links_plugin as $lp) {

            $args_plugin = $this->plugin_get_args($lp[1]);
            $arg = json_encode($args_plugin);

            $wpdb->insert(
                    $this->table, array(
                'url' => $lp[0],
                'args' => $arg,
                'last' => 0
                    ), array('%s', '%s', '%d')
            );
        }
        
       //
       if($this->has_portfolio_plugin):
       $args_portfolio = $this->portfolio_get_args();

       foreach($links_portfolio as $ur){		
		   $arg = $args_portfolio;
		   $arg[] = $ur;
           $arg = json_encode($arg);

            $wpdb->insert(
                    $this->table, array(
                'url' => $ur,
                'args' => $arg,
                'last' => 0
                    ), array('%s', '%s', '%d')
            );
		}
       endif; 
       //exit;
    }

    //delete cache before rereshing
    function delete_cache() {
        //empty cache first
        if ($this->has_theme) {
            $c_dir = get_option("imagestorage_path");
            if ($c_dir) {
                foreach (glob($c_dir . '*') AS $filename) {
                    @unlink($filename);
                }
            }
        }

        if ($this->has_plugin)
            $this->STWWT_cache_emptyCache_plugin(false);
            
            //clear portfolio cache
        if($this -> has_portfolio_plugin){

			$dir_c= WPPortfolio_getThumbPathActualDir();
			if ($dir_c) {
                foreach (glob($dir_c . '*') AS $filename) {
                    @unlink($filename);
                }
			
		}
	}
			
			
    }



    //returns args for plugin links
    function plugin_get_args($la) {
        $url = $la[0];

        if (is_array($la[1])) {
            extract(shortcode_atts(array(
                'size' => '',
                'link' => '',
                'full_length' => ''
                            ), $atts));
        }

        // If link="true" appears in the shortcode, then add the link too.
        $showLink = false;
        if ($link == 'true') {
            $showLink = true;
        }


        // Check for a full length parameter. Completely ignore the parameter
        // if we don't have access to that parameter. 
        $showFullLength = false;
        if ($full_length && STWWT_account_featuredAllowed('embedded_pro_full_length')) {
            $full_length = strtolower($full_length);
            if ($full_length == 'true') {
                $showFullLength = 'full';
            } else {
                $showFullLength = 'normal'; // Allows for situation where user does not want a full length
            }
        }

        $allSettings = TidySettings_getSettings(STWWT_SETTINGS_KEY);
        $accountSettings = TidySettings_getSettings(STWWT_SETTINGS_KEY_ACCOUNT);

        // Common Defaults
        $args = array();
        $args['stwredo'] = 2;
        $args["stwsize"] = $allSettings['stwwt_embedded_default_size'];
        $args["stwaccesskeyid"] = $allSettings['stwwt_access_id'];

        // Check if override for size is given.
        $showCustomSize = $size;
        if ($showCustomSize) {
            // Check for standard STW size parameter (for free or paid accounts)
            $showCustomSize = strtolower($showCustomSize);
            if (in_array($showCustomSize, array('xlg', 'lg', 'sm', 'vsm', 'tny', 'mcr'))) {
                $args["stwsize"] = $showCustomSize;
            }
        }

        // #### Download and Cache Method 
        // Settings for specifically fetching details

        $args["stwu"] = $allSettings['stwwt_secret_id'];

        // Custom Size check - for numeric sizes
        if ($showCustomSize && // Check custom size requested
                preg_match('/^([0-9]+)$/', $showCustomSize, $matches) && // Check for a numeric size 
                STWWT_account_featuredAllowed('embedded_pro_custom_size')) {  // Check custom sizes allowed
            unset($args["stwsize"]);
            $args['stwxmax'] = $matches[1];
        }

        // ### Pro Settings (need to go before URL)
        // Inside page
        if ($allSettings['stwwt_embedded_pro_inside'] == 'enable' && STWWT_account_featuredAllowed('embedded_pro_inside')) {
            $args["stwinside"] = '1';
        }

        // Full Length feature allowed?
        if (STWWT_account_featuredAllowed('embedded_pro_full_length')) {
            if ('full' == $showFullLength || // Check that full override requested
                    (false == $showFullLength && 'enable' == $allSettings['stwwt_embedded_pro_full_length'])) { // Check if default requested
                $args["stwfull"] = '1';

                // Change size to match width of chosen thumbnail size.			
                switch ($args['stwsize']) {
                    case 'mcr': $args['stwxmax'] = '75';
                        break;
                    case 'tny': $args['stwxmax'] = '90';
                        break;
                    case 'vsm': $args['stwxmax'] = '100';
                        break;
                    case 'sm' : $args['stwxmax'] = '120';
                        break;
                    case 'lg' : $args['stwxmax'] = '200';
                        break;
                    case 'xlg': $args['stwxmax'] = '320';
                        break;
                }
                unset($args['stwsize']); // Not needed if custom size
            }
        }

        // Quality
        if ($allSettings['stwwt_embedded_pro_custom_quality'] > 0 && STWWT_account_featuredAllowed('embedded_pro_custom_quality')) {
            $args["stwq"] = $allSettings['stwwt_embedded_pro_custom_quality'];
        }

        // URL (needs to be last)	
        return $args;
    }
    
    //get args for the portfolio plugins
    function portfolio_get_args(){
		$args = array();
		   $args["stwaccesskeyid"] = stripslashes(get_option('WPPortfolio_setting_stw_access_key'));     
		$args["stwu"] 			= stripslashes(get_option('WPPortfolio_setting_stw_secret_key'));
		
		$customSize = WPPortfolio_getCustomSizeOption();
		if($customSize) {
			$args["stwxmax"] = $customSize;
		}
		
		// No custom size, just do the standard size.
    	else {
    		$args["stwsize"] = stripslashes(get_option('WPPortfolio_setting_stw_thumb_size'));
    	} 
    	
    	$args["stwredo"] = 2;   
    	return $args;	
		
		}

    function plugin_thumbnail_refresh($la) {
        $args = plugin_get_args($la);


// finally do request
        $fetchURL = urldecode("http://images.shrinktheweb.com/xino.php?" . http_build_query($args));

        $resp = wp_remote_get($fetchURL, array(
            'timeout' => 0.5));
    }

//end of refresh function
    //get args for theme links without link

    function theme_get_args() {
        $options = $this->_generateOptions(array());
        $args = $this->_generateRequestArgs($options);
//failsafe id
        if (!$args['stwaccesskeyid']) {
            if ($this->has_plugin) {
                $allSettings = TidySettings_getSettings(STWWT_SETTINGS_KEY);
                $args["stwaccesskeyid"] = $allSettings['stwwt_access_id'];
                $args["stwu"] = $allSettings['stwwt_secret_id'];
            }
        }


        return $args;
    }





    function STWWT_cache_emptyCache_plugin($errorOnly) {
        // Remove only error thumbnails, or all files.
        if ($errorOnly) {
            $removeType = 'error_*';
        } else {
            $removeType = '*';
        }

        $cacheDir = STWWT_plugin_getCacheDirectory(false, false);

        foreach (glob($cacheDir . $removeType) AS $filename) {
            @unlink($filename);
        }
    }

    function _generateOptions($aOptions) {
        global $wpdb;
        $IMAGEVALUES = get_option('pptimage');

        if (!isset($IMAGEVALUES['stw_9'])) {
            $IMAGEVALUES['stw_9'] = "";
        }
        if (!isset($IMAGEVALUES['stw_5'])) {
            $IMAGEVALUES['stw_5'] = "";
        }
        if (!isset($IMAGEVALUES['stw_6'])) {
            $IMAGEVALUES['stw_6'] = "";
        }
        if (!isset($IMAGEVALUES['stw_14'])) {
            $IMAGEVALUES['stw_14'] = "";
        }
        if (!isset($IMAGEVALUES['stw_10'])) {
            $IMAGEVALUES['stw_10'] = "";
        }

        // check if there are options set, otherwise set it to default or false
        $aOptions['Size'] = isset($aOptions['Size']) && $aOptions['Size'] != "" ? $aOptions['Size'] : 'lg'; //$IMAGEVALUES['stw_7'];
        //$aOptions['SizeCustom'] = $aOptions['SizeCustom'] ? $aOptions['SizeCustom'] : $IMAGEVALUES['stw_4'];
        $aOptions['FullSizeCapture'] = isset($aOptions['FullSizeCapture']) && $aOptions['FullSizeCapture'] != "" ? $aOptions['FullSizeCapture'] : $IMAGEVALUES['stw_14'];
        //$aOptions['MaxHeight'] = $aOptions['MaxHeight'] ? $aOptions['MaxHeight'] : $IMAGEVALUES['stw_8'];
        $aOptions['NativeResolution'] = isset($aOptions['NativeResolution']) && $aOptions['NativeResolution'] != "" ? $aOptions['NativeResolution'] : $IMAGEVALUES['stw_9'];
        $aOptions['WidescreenY'] = isset($aOptions['WidescreenY']) && $aOptions['WidescreenY'] != "" ? $aOptions['WidescreenY'] : $IMAGEVALUES['stw_10'];
        $aOptions['RefreshOnDemand'] = isset($aOptions['RefreshOnDemand']) && $aOptions['RefreshOnDemand'] != "" ? $aOptions['RefreshOnDemand'] : false;
        $aOptions['Delay'] = isset($aOptions['Delay']) && $aOptions['Delay'] != "" ? $aOptions['Delay'] : $IMAGEVALUES['stw_5'];
        $aOptions['Quality'] = isset($aOptions['Quality']) && $aOptions['Quality'] != "" ? $aOptions['Quality'] : $IMAGEVALUES['stw_6'];

        return $aOptions;
    }

    function _generateRequestArgs($aOptions) {
        $aArgs['stwaccesskeyid'] = ACCESS_KEY;
        $aArgs['stwu'] = SECRET_KEY;
        $aArgs['stwver'] = VER;

        // allowing internal links?
        if (INSIDE_PAGES) {
            $aArgs['stwinside'] = 1;
        }

        // If SizeCustom is specified and widescreen capturing is not activated,
        // then use that size rather than the size stored in the settings
        if (!$aOptions['FullSizeCapture'] && !$aOptions['WidescreenY']) {
            // Do we have a custom size?
            if ($aOptions['SizeCustom']) {
                $aArgs['stwxmax'] = $aOptions['SizeCustom'];
            } else {
                $aArgs['stwsize'] = $aOptions['Size'];
            }
        }

        // Use fullsize capturing?
        if ($aOptions['FullSizeCapture']) {
            $aArgs['stwfull'] = 1;
            if ($aOptions['SizeCustom']) {
                $aArgs['stwxmax'] = $aOptions['SizeCustom'];
            } else {
                $aArgs['stwxmax'] = 120;
            }
            if ($aOptions['MaxHeight']) {
                $aArgs['stwymax'] = $aOptions['MaxHeight'];
            }
        }

        // Change native resolution?
        if ($aOptions['NativeResolution']) {
            $aArgs['stwnrx'] = $aOptions['NativeResolution'];
            if ($aOptions['WidescreenY']) {
                $aArgs['stwnry'] = $aOptions['WidescreenY'];
                if ($aOptions['SizeCustom']) {
                    $aArgs['stwxmax'] = $aOptions['SizeCustom'];
                } else {
                    $aArgs['stwxmax'] = 120;
                }
            }
        }

        // Wait after page load in seconds
        if ($aOptions['Delay']) {
            $aArgs['stwdelay'] = intval($aOptions['Delay']) <= 45 ? intval($aOptions['Delay']) : 45;
        }

        // Use Refresh On-Demand

        $aArgs['stwredo'] = 2;


        // Use another image quality in percent
        if ($aOptions['Quality']) {
            $aArgs['stwq'] = intval($aOptions['Quality']);
        }

        // Use custom messages?
        if (CUSTOM_MSG_URL) {
            $aArgs['stwrpath'] = CUSTOM_MSG_URL;
        }

        return $aArgs;
    }

}

