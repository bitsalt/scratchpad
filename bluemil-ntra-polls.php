<?php
/**
 * Plugin Name: Blue Million NTRA Polls
 * Plugin URI: 
 * Description: Polling system from Blue Million
 * Version: 0.8
 * Author: Blue Million LLC
 * Author URI: www.bluemillion.com
 * License: 
 */

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);



class BlueMillonPollsLoader {
    
    var $debug = false;
    
    /**
	 * plugin version
	 *
	 * @var string
	 */
	private $version = '1.0';
	
	
	/**
	 * database version
	 *
	 * @var string
	 */
    private $dbVersion = '1.0';
	
		
	/**
	 * admin Panel object
	 *
	 * @var object
	 */
    private $adminPanel;
    
    
    /**
     * Stored options
     * 
     * @var mixed
     */
    private $options;
    
    
    /**
     *
     * @var object
     */
    private $displayPage;


    /**
     * @var bool
     */
    private $activationRun = false;


    /*
     * @var object
     */
    private $db;
    
    
    /**
	 * constructor
	 *
	 * @param none
	 * @return void
	 */
    function __construct() {
		global $wpdb;

		$this->db = $wpdb;

        $this->wpdb->show_errors();
        //$this->wpdb->hide_errors();
		
        $this->defineConstants();
		$this->loadOptions();
		$this->defineTables();
		
   		register_activation_hook(__FILE__, array(&$this, 'activate') );

        
        add_action('init', array(&$this, 'loadController') );
        add_action('init', array(&$this, 'loadFrontEndScripts'));
		add_action('init', array(&$this, 'loadStyles') );
        
        	
		if (function_exists('register_deactivation_hook'))
			register_deactivation_hook(__FILE__, array( $this, 'uninstall'));

        //add_action('plugins_loaded', array(&$this, 'updateCheck'));
        
        // shortcode coverage for front end
        //add_shortcode( 'ntra_poll_results', array(&$this, 'getPollResults') );

        
        
        add_action( 'wp_ajax_bm_send_invitations', array(&$this, 'sendInvitations') );
        add_action( 'wp_ajax_bm_send_reminder', array(&$this, 'sendReminder') );
        add_action( 'wp_ajax_bm_count_votes', array(&$this, 'countVotes') );
        add_action( 'wp_ajax_bm_test_count_votes', array(&$this, 'testCountVotes') );
        add_action( 'wp_ajax_bm_show_votes', array(&$this, 'showVotes') );
        add_action( 'wp_ajax_bm_show_totals', array(&$this, 'showTotals') );
        add_action( 'wp_ajax_bm_show_snapshot', array(&$this, 'showSnapshot') );
        add_action( 'wp_ajax_nopriv_bm_save_votes', array(&$this, 'saveVotes') );
        //add_action( 'wp_ajax_show_slack_performance_form', array(&$this, 'show_slack_performance_form') );
        
	}
    
    
    
    
    /**
	 * Activate plugin
	 *
	 * @param none
	 */
    function activate()
	{
        update_option( 'bmpolls-version', $this->version );
        update_option( 'bmpolls-db-version', $this->dbVersion );
        
        // Set Capabilities
		$adminUser = get_role('administrator');
		$adminUser->add_cap('manage_polls');
		$adminUser->add_cap('manage_invitations');
        $adminUser->add_cap('bm-polls');
	
		$editorUser = get_role('editor');
		$editorUser->add_cap('manage_polls');
		$editorUser->add_cap('manage_invitations');
        $editorUser->add_cap('bm-polls');
    
		//$this->install();
        
        $this->activationRun = true;
	}
		
		
    
    
    

    /**
     * Install the database tables 
     */
    public function install() {
        $charset_collate = '';
        if ($this->wpdb->has_cap('collation')) {
            if (!empty($this->wpdb->charset))
                $charset_collate = "DEFAULT CHARACTER SET $this->wpdb->charset";
            if (!empty($this->wpdb->collate))
                $charset_collate .= " COLLATE $this->wpdb->collate";
        }
        
        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS `REDACTED_polls` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `start_date` datetime NOT NULL,
                    `end_date` datetime NOT NULL,
                    `extended_end_date` datetime NOT NULL,
                    `type` varchar(20) NOT NULL,
                    `year` int(11) NOT NULL,
                    PRIMARY KEY (`id`)
                  ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `REDACTED_poll_horses` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `position` int(11) DEFAULT NULL,
                    `age` int(11) DEFAULT NULL,
                    `gender` varchar(2) DEFAULT NULL,
                    `record_1` int(11) DEFAULT NULL,
                    `record_2` int(11) DEFAULT NULL,
                    `record_3` int(11) DEFAULT NULL,
                    `record_4` int(11) DEFAULT NULL,
                    `points` int(11) DEFAULT NULL,
                    `last_position` int(11) DEFAULT NULL,
                    `is_active` tinyint(1) NOT NULL DEFAULT '0',
                    `is_trash` int(11) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                )$charset_collate;";  

        $sql[] = "CREATE TABLE IF NOT EXISTS `REDACTED_poll_invitations` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `poll_id` int(11) NOT NULL,
                    `voter_id` int(11) NOT NULL,
                    `code` varchar(64) NOT NULL,
                    `is_active` tinyint(1) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`),
                    KEY `poll_id` (`poll_id`),
                    KEY `voter_id` (`voter_id`)
                  ) $charset_collate;";  

        $sql[] = "CREATE TABLE IF NOT EXISTS `REDACTED_poll_votes` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `poll_id` int(11) NOT NULL,
                    `voter_id` int(11) NOT NULL,
                    `horse_id` int(11) NOT NULL,
                    `position` int(11) NOT NULL,
                    `horse_name` varchar(100) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `poll_id` (`poll_id`),
                    KEY `voter_id` (`voter_id`),
                    KEY `horse_id` (`horse_id`)
                  ) $charset_collate;";  

        $sql[] = "CREATE TABLE IF NOT EXISTS `REDACTED_poll_voters` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `email` varchar(100) NOT NULL,
                    `first_name` varchar(30) NOT NULL,
                    `last_name` varchar(30) NOT NULL,
                    `is_active` tinyint(1) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                  ) $charset_collate;";  

    }
    
    
    /**
	 * load options
	 *
	 * @param none
	 * @return void
	 */
    function loadOptions() {
        require(BMPOLLS_PATH.'/models/BMModel.php');
        require_once(BMPOLLS_PATH . '/models/MailGunForPolls.php');
		//$this->options = get_option('bmlivestats');
	}
    
    
    
    /**
	 * define constants
	 *
	 * @param none
	 * @return void
	 */
    function defineConstants() {
		if ( !defined( 'WP_CONTENT_URL' ) )
			define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
		if ( !defined( 'WP_PLUGIN_URL' ) )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
		if ( !defined( 'WP_CONTENT_DIR' ) )
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		if ( !defined( 'WP_PLUGIN_DIR' ) )
			define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
			
		//define( 'BMLIVESTATS_VERSION', $this->version );
		//define( 'BMLIVESTATS_DBVERSION', $this->dbVersion );
		define( 'BMPOLLS_URL', WP_PLUGIN_URL.'/bluemil-ntra-polls' );
		define( 'BMPOLLS_PATH', WP_PLUGIN_DIR.'/bluemil-ntra-polls' );
        define( 'BMPOLLS_ADMIN_URL', get_option( 'siteurl' ) . '/wp-admin' );
        
        define( 'BMPOLLS_EMAIL_DIST', 'REDACTED@bluemillion.com, REDACTED@ntra.com, REDACTED@bitsalt.com');
        define( 'BMPOLLS_EMAIL_NTRA', 'REDACTED@bluemillion.com, REDACTED@ntra.com, REDACTED@bitsalt.com');
    }
    
    
    
    
    /**
	 * define database tables
	 *
	 * @param none
	 * @return void
	 */
	function defineTables() {
		$this->wpdb->table_poll = $this->wpdb->prefix . 'poll';
		$this->wpdb->table_voters = $this->wpdb->prefix . 'poll_voters';
		$this->wpdb->table_invitation = $this->wpdb->prefix . 'poll_invitation';
        $this->wpdb->table_votes = $this->wpdb->prefix . 'poll_votes';
        $this->wpdb->table_horses = $this->wpdb->prefix . 'poll_horses';
        
	}
    
    
    
    
    /**
	 * load libraries
	 *
	 * @param none
	 * @return void
	 */
    function loadController() {
        
        // admin or front end use?
        if ( is_admin() ) {
            require_once (dirname (__FILE__) . '/controllers/PollController.php');
        }
        else {
            // any non-admin specific libraries to add?
            
            require_once (dirname (__FILE__) . '/controllers/PollFrontController.php');
            
            add_shortcode('ntra_polls', array(&$this, 'handlePollsShortcode'));
        }
       
	}
	
	
    /**
    * Uninstall Plugin
    *
    * @param none
    */
   function uninstall() {
       /** NTRA staff user uninstalled unintentionally. Let's not do this again...
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_poll}" );
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_voters}" );
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_invitation}" );
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_votes}" );
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_horses}" );
       $this->wpdb->query( "DROP TABLE {$this->wpdb->table_results}" );

       delete_option( 'bmpolls' );
        * 
        */
   }
	
   
   
   public function handlePollsShortcode() { //$atts) {
       //extract(shortcode_atts(array('cStr' => 0), $atts));
       $front = new bmPollFrontController();
       echo $front->router();
       
   }
   
   
    
   public function loadFrontEndScripts() {
       wp_enqueue_script( 'bmpoll-autocomplete-js', BMPOLLS_URL.'/inc/jquery-autocomplete-ui.min.js', array('jquery') );
       wp_enqueue_script('jquery-migrate', 'http://code.jquery.com/jquery-migrate-1.4.1.js', array('bmpoll-reveal-js'));
       wp_enqueue_script( 'bmpoll-modal-js', BMPOLLS_URL.'/inc/jquery.modal.min.js', array('jquery') );
       wp_enqueue_script( 'bmpoll-bmpolls-js', BMPOLLS_URL.'/inc/bm-polls.js', array('jquery', 'jquery-ui-core', 'dtpicker-js'), '1.0.3' );
       wp_enqueue_script('dtpicker-js', BMPOLLS_URL.'/inc/jquery.timepickerAdd.js', array('jquery', 'jquery-ui-core'),time(),true);
   }
   
   public function loadStyles() {
       wp_enqueue_style('bmpoll-autocomplete-css', BMPOLLS_URL . "/inc/jquery-autocomplete-ui.css", false, '1.0', 'screen');
       wp_enqueue_style('bmpoll-polls-css', BMPOLLS_URL . "/inc/bm-polls.css", false, '1.0', 'screen');
       wp_enqueue_style('bmpoll-modal-css', BMPOLLS_URL . "/inc/jquery.modal.css", false, '0.9.2', 'screen');
       wp_enqueue_style('dtpicker-css', BMPOLLS_URL . "/inc/jquery.timepickerAdd.css", false, '1.0', 'screen');
   }
   
   
   
   
   /*****  Ajax handlers ********/
   
    public function sendInvitations() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['group_id'])) {
            $ajax = new AjaxController();
            $output = $ajax->sendInvitations($_POST['group_id']);
        
            echo 'Invitations sent';
        }
        exit;
    }
    
    
    
    public function sendReminder() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['group_id'])) {
            $ajax = new AjaxController();
            $output = $ajax->sendReminder($_POST['group_id']);
        
            echo $output.' reminders sent';
        }
        exit;
    }
    
    
    public function countVotes() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['poll_id'])) {
            $ajax = new AjaxController();
            $result = $ajax->countVotes($_POST['poll_id']);
        
            if($result) {
                echo '<input type="button" name="view_votes" value="View Results" onClick="bmViewResults('.$_POST['poll_id'].')" />';
            }
            else {
                echo 'An error ocurred during vote counting.';
            }
        }
        exit;
    }
    
    
    public function testCountVotes() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['poll_id'])) {
            $ajax = new AjaxController();
            $result = $ajax->countVotes($_POST['poll_id'], true);
        
            if($result) {
                echo $result;
            }
            else {
                echo 'An error ocurred during vote counting.';
            }
        }
        
        exit;
    }
    
    
    public function getTopPollResult($type) {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        $result = '';
        
        if(isset($type)) {
            $ajax = new AjaxController();
            $result = $ajax->getTopPollResult($type);
            
            if(!$result) {
                return NULL;
            }
        }
        return $result;
    }
    
    public function showVotes() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['poll_id'])) {
            $ajax = new AjaxController();
            $result = $ajax->showVotes($_POST['poll_id']);
            
            if(!$result) {
                $result = 'An error ocurred during vote counting.';
            }
        }
        
        echo $result; exit;
    }
    
    public function showSnapshot() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['poll_id'])) {
            $ajax = new AjaxController();
            $result = $ajax->showSnapshot($_POST['poll_id']);
            
            if(!$result) {
                $result = 'An error ocurred during vote counting.';
            }
        }
        
        echo $result; exit;
    }
    
    
    public function showTotals() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        if(isset($_POST['poll_id'])) {
            $ajax = new AjaxController();
            $result = $ajax->showTotals($_POST['poll_id']);
            
            if(!$result) {
                $result = 'An error ocurred during vote counting.';
            }
        }
        
        echo $result; exit;
    }
    
    
    public function saveVotes() {
        require_once(BMPOLLS_PATH . '/controllers/AjaxController.php');
        
        $data =$_REQUEST['data'][0];
        
        $ajax = new AjaxController($this->debug);
        $result = $ajax->processPoll($data);

        if(!$result) {
            $result = 'An error ocurred during vote counting.';
        }
        
        echo $result; exit;
    }
    
}


// Run the Plugin
$bmPoll = new BlueMillonPollsLoader();

function getTopPollResult($atts) {
    global $bmPoll;
    return $bmPoll->getTopPollResult($atts['poll']);
}
add_shortcode( 'ntra_poll_results', 'getTopPollResult' );