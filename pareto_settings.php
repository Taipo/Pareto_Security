<?php
if ( class_exists( "pareto_functions" ) ):
    class pareto_settings extends pareto_functions {
        public static $default_settings = array( 'advanced_mode' => 0, 'ban_mode' => 0, 'hard_ban_mode' => 0, 'safe_list' => '', 'email_report' => 1, 'safe_list' => '' );
        var $pagehook, $page_id, $settings_field, $options, $log_list, $logs, $time_zone;
        public $_ban_mode = 0;
        
        function __construct() {
            if ( false === $this->is_wp( false, false ) ) {
                header( 'Status: 403 Forbidden' );
                header( 'HTTP/1.1 403 Forbidden' );
                exit();
            }
            $unix_time       = $this->updated( 1527301652, ( int ) get_option( 'gmt_offset' ) );
            $this->time_zone = date_default_timezone_get() . get_option( 'gmt_offset' );
            
            define( 'PARETO_VERSION', '1.9.5' );
            define( 'PARETO_RELEASE_DATE', date_i18n( 'F j, Y', $unix_time ) );
            define( 'PARETO_DIR', plugin_dir_path( __FILE__ ) );
            define( 'PARETO_URL', plugin_dir_url( __FILE__ ) );
            $this->page_id        = 'pareto_security_settings';
            $this->timestamp      = ( false !== $this->is_wp() ) ? date_i18n( 'd-m-y,G:i', ( $this->updated( time(), ( int ) get_option( 'gmt_offset' ) ) ) ) : date( "d.m.y-G:i" );
            $this->settings_field = 'pareto_security_settings_options';
            $this->log_list       = 'pareto_security_log_list';
            
            $this->options = get_option( $this->settings_field );
            $this->logs    = get_option( $this->log_list );
            
            if ( empty( $this->options ) ) {
                update_option( $this->settings_field, array( // set defaults
                     'advanced_mode' => 0,
                     'hard_ban_mode' => 0,
                     'email_report' => 1,
                     'ban_mode' => 0 
                ) );
                $this->options = get_option( $this->settings_field );
            }
            
            $this->options[ 'ban_mode' ]      = ( false !== $this->check_settings( 'ban_mode' ) ) ? ( int ) $this->options[ 'ban_mode' ] : 0;
            $this->options[ 'email_report' ]  = ( false !== $this->check_settings( 'email_report' ) ) ? ( int ) $this->options[ 'email_report' ] : 0;
            $this->options[ 'advanced_mode' ] = ( false !== $this->check_settings( 'advanced_mode' ) ) ? ( int ) $this->options[ 'advanced_mode' ] : 0;
            $this->options[ 'hard_ban_mode' ] = ( false !== $this->check_settings( 'hard_ban_mode' ) ) ? ( int ) $this->options[ 'hard_ban_mode' ] : 0;
            
            if ( array_key_exists( 'safe_list', $this->options ) ) {
                $this->_domain_list = $this->get_field_value( $this->options, 'safe_list' );
                $this->options[ 'safe_list' ] = $this->_domain_list;
            }
            if ( false !== ( bool ) $this->is_wp( true ) ) {
                if ( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {
                    if ( isset( $_POST[ $this->settings_field ][ "safe_list" ] ) ) {
                        $_POST[ $this->settings_field ][ "safe_list" ] = $this->host_check( $_POST[ $this->settings_field ][ "safe_list" ] );
                    }
                    if ( isset( $_POST[ $this->settings_field ][ "ban_mode" ] ) )
                        $_POST[ $this->settings_field ][ "ban_mode" ] = ( int ) $_POST[ $this->settings_field ][ "ban_mode" ];
                    if ( isset( $_POST[ $this->settings_field ][ "advanced_mode" ] ) ) {
                        $_POST[ $this->settings_field ][ "advanced_mode" ] = ( int ) $_POST[ $this->settings_field ][ "advanced_mode" ];
                        $_POST[ $this->settings_field ][ "safe_list" ] = $this->get_http_host();
                    }
                    if ( isset( $_POST[ $this->settings_field ][ "email_report" ] ) )
                        $_POST[ $this->settings_field ][ "email_report" ] = ( int ) $_POST[ $this->settings_field ][ "email_report" ];
                    if ( isset( $_POST[ $this->settings_field ][ "hard_ban_mode" ] ) )
                        $_POST[ $this->settings_field ][ "hard_ban_mode" ] = ( int ) $_POST[ $this->settings_field ][ "hard_ban_mode" ];
                }
                
            }
            if ( false !== ( bool ) $this->is_wp( true ) ) {
                add_action( 'admin_init', array(
                     $this,
                    'admin_init' 
                ), 20 );
                add_action( 'admin_menu', array(
                     $this,
                    'admin_menu' 
                ), 20 );
            }
            $this->_adv_mode      = $this->get_field_value( $this->options, 'advanced_mode' );
            $this->_ban_mode      = $this->get_field_value( $this->options, 'ban_mode' );
            $this->_email_report  = $this->get_field_value( $this->options, 'email_report' );
            $this->_hard_ban_mode = ( false !== ( bool ) $this->_adv_mode ) ? $this->get_field_value( $this->options, 'hard_ban_mode' ) : 0;
            $this->update_logfile( $this->logs );
            update_option( $this->settings_field, array( // set defaults
                     'advanced_mode' => $this->_adv_mode,
                     'hard_ban_mode' => $this->_hard_ban_mode,
                     'email_report' => $this->_email_report,
                     'ban_mode' => $this->_ban_mode,
                     'safe_list' => $this->_domain_list
            ) );
        }
        function update_logfile( $logfile = array() ) {
            if ( false === ( bool ) $this->_adv_mode ) {
                $tmp_logfile = array();
                for( $x = 0; $x < count( $logfile ); $x++ ) {
                    
                    if ( strpos( strtolower( $logfile[ $x ] ), " low " ) || strpos( strtolower( $logfile[ $x ] ), " safe " ) ) {
                        continue;
                    } else $tmp_logfile[] = $logfile[ $x ];
                }
                update_option( $this->log_list, $tmp_logfile);
            }
            if ( empty( $logfile ) ) {
                $this_log = str_replace( ' ', '%20', PARETO_RELEASE_DATE ) . " Safe " . str_replace( ' ', '%20', $this->get_ip() ) . " GET plugins.php Pareto%20Security%20Installed";
                update_option( $this->log_list, array(
                     0 => $this_log ) );
            }
            $this->logs = get_option( $this->log_list );
            return;
        }
        function check_settings( $val ) {
            if ( !isset( $this->options[ $val ] ) || $this->options[ $val ] > 1 || $this->options[ $val ] < 0 ) {
                return false;
            } else
                return true;
        }
        function admin_init() {
            register_setting( $this->settings_field, $this->settings_field ); //, array( $this, 'sanitize_theme_options' ) );
            add_option( $this->settings_field, pareto_settings::$default_settings );
        }
        function admin_menu() {
            if ( !current_user_can( 'update_plugins' ) )
                return;
            // Add a new submenu to the standard Settings panel
            $this->pagehook = $page = add_options_page( __( 'Pareto Security Settings', 'pareto_security_settings' ), __( 'Pareto Security Dashboard', 'pareto_security_settings' ), 'administrator', $this->page_id, array(
                 $this,
                'render' 
            ) );
            add_action( 'load-' . $this->pagehook, array(
                 $this,
                'metaboxes' 
            ) );
            add_action( "admin_print_scripts-$page", array(
                 $this,
                'js_includes' 
            ) );
            add_action( "admin_head-$page", array(
                 $this,
                'admin_head' 
            ) );
        }
        function admin_head() {
?>
       <style>
        .settings_page_pareto_security_settings label { display:inline-block; width: 400px; }
        </style>
<?php
        }
        function js_includes() {
            // Needed to allow metabox layout and close functionality.
            wp_enqueue_script( 'postbox' );
        }
        /*
        Sanitize our plugin settings array as needed.
        */
        function sanitize_theme_options( $options ) {
            
            if ( is_array( $options ) ) {
                foreach ( $options as $key => $val ) {
                    if ( $key != 'safe_list' && false === $this->integ_prop( $val ) || $val > 1 ) {
                        $options[ $key ] = 0;
                    } elseif ( $val == 'safe_list' ) {
                        $options[ 'safe_list' ] = $this->cleanRequestInput( $val );
                    } else {
                        $options[ $key ] = ( int ) $val;
                    }
                }
                return $options;
            }
        }
        /*
        Settings access functions.
        
        */
        protected function get_field_name( $name ) {
            return sprintf( '%s[%s]', $this->settings_field, $name );
        }
        protected function get_field_id( $id ) {
            return sprintf( '%s[%s]', $this->settings_field, $id );
        }
        protected function get_field_value( $option, $key ) {
            return $option[ $key ];
        }
        function cleanRequestInput( $input ) {
            if ( function_exists( 'filter_var' ) && defined( 'FILTER_SANITIZE_STRING' ) ) {
                if ( false !== ( bool ) filter_var( $input, FILTER_SANITIZE_STRING ) ) {
                    return $input;
                } else
                    return false;
            }
        }
        /*
        Render settings page.
        
        */
        function render() {
            global $wp_meta_boxes;
            $title = __( 'Pareto Security Dashboard', 'pareto_security_settings' );
?>
       <div class="wrap">
            <table style="text-align: left;">
                <tr>
                    <td><img src="data:image/png;base64,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"></td>
                    <td><h1><?php
            echo esc_html( $title );
?></h1></td>
                </tr>
            </table>
            <form method="post" action="options.php" <?php
            if ( false !== ( bool ) $this->_adv_mode && false === ( bool ) $this->_hard_ban_mode ) {
?>onsubmit="return confirm( 'Warning: If you are changing from advanced mode to standard mode log files marked as Low Severity will be removed from the log file. Click Ok to continue' );"<?php
            }
?>>
                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 99%;">
<?php
            // Render metaboxes
            settings_fields( $this->settings_field );
            do_meta_boxes( $this->pagehook, 'main', null );
            if ( isset( $wp_meta_boxes[ $this->pagehook ][ 'column2' ] ) )
                do_meta_boxes( $this->pagehook, 'column2', null );
?>
                   </div>
                </div>
            </form>
        </div>
        <!-- Needed to allow metabox layout and close functionality. -->
        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready( function ($) {
                // close postboxes that should be closed
                $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
                // postboxes setup
                postboxes.add_postbox_toggles('<?php
            echo $this->pagehook;
?>');
            });
            //]]>
        </script>
<?php
        }
        function metaboxes() {
            add_meta_box( 'pareto-security-settings-version', __( 'Information', 'pareto_security_settings' ), array(
                 $this,
                'info_box' 
            ), $this->pagehook, 'main', 'high' );
            add_meta_box( 'pareto-security-settings-notes', __( 'Notes:', 'pareto_security_settings' ), array(
                 $this,
                'notes_box' 
            ), $this->pagehook, 'main' );
            add_meta_box( 'pareto-security-settings-conditions', __( 'Custom Settings', 'pareto_security_settings' ), array(
                 $this,
                'condition_box' 
            ), $this->pagehook, 'main' );
            if ( false !== ( bool ) $this->_adv_mode )
                add_meta_box( 'pareto-security-settings-domainlist', __( 'Domain Name Safe List:', 'pareto_security_settings' ), array(
                     $this,
                    'safelist_box' 
                ), $this->pagehook, 'main' );
            add_meta_box( 'pareto-security-settings-save', __( 'Save All Settings', 'pareto_security_settings' ), array(
                 $this,
                'save_settings' 
            ), $this->pagehook, 'main' );
            add_meta_box( 'pareto-security-settings-logs', __( 'Last 100 Attack Requests', 'pareto_security_settings' ), array(
                 $this,
                'logfile_box' 
            ), $this->pagehook, 'main' );
            add_meta_box( 'pareto-security-settings-donations', __( 'Donations', 'pareto_security_settings' ), array(
                 $this,
                'donations_box' 
            ), $this->pagehook, 'main' );
        }
        
        function safelist_box() {
            if ( false === $this->_adv_mode )
                return;
            $is_https  = ( "on" == @$_SERVER[ "HTTPS" ] || "on" == getenv( "HTTPS" ) ) ? true : false;
            $http      = ( false !== $is_https ) ? 'https://' : 'http://';
            $url       = str_replace( 'www.', '', $this->get_http_host() );
            $hsts_url  = 'https://hstspreload.org/?domain=' . $url;
            $hsts_link = '<a target="_blank" href="' . $hsts_url . '">' . $hsts_url . '</a>';
?>
           <table style="text-align: left;">
                <tr>
                    <td><b>Status:</b> ( <?php
            echo ( false === ( bool ) $this->_adv_mode ) ? 'To enable, set to Advanced Mode above' : 'Enabled';
?> )
                    <ol>
                        <li>List every domain name associated with your website here (including subdomains).</li>
                        <li>One domain name per line: (i.e <?php
            echo $this->get_http_host();
?> - without <code><?php
            echo $http;
?></code> scheme/protocol and double forward slashes)</li>
                           <textarea <?php echo ( false === ( bool ) $this->_adv_mode ) ? 'disabled' : ''; ?> name="<?php echo $this->get_field_name( 'safe_list' ); ?>" id="<?php echo $this->get_field_name( 'safe_list' ); ?>" rows="3" cols="30"><?php echo $this->options[ 'safe_list' ]; ?></textarea>
                        <?php
            if ( false !== $is_https )
                echo '<li>Register your domain with Google Chromes preload list ' . $hsts_link . '</li>';
?>
                   </ol></td>
                </tr>
            </table>
<?php
        }
        function save_settings() {
?>
           <table style="text-align: left;">
                <tr>
                    <td><input type="checkbox" name="<?php
            echo $this->get_field_name( 'email_report' );
?>" id="<?php
            echo $this->get_field_id( 'email_report' );
?>" value="1" <?php
            if ( ( isset( $this->options[ 'email_report' ] ) && false !== ( bool ) $this->options[ 'email_report' ] ) ) {
?>checked<?php
            }
?> /> <label for="<?php
            echo $this->get_field_id( 'email_report' );
?>"><?php
            _e( '<b>Email Notification:</b> Recieve notifications of High Severity attacks', 'pareto_security_settings' );
?></label>
                    <br /><br />
                    </td>
                </tr>
                <tr>
                    <td><input type="submit" class="button button-primary" name="save_options" value="<?php
            esc_attr_e( 'Save Options' );
?>" /><br /><br /></td>
                </tr>
            </table>
<?php
        }
        function info_box() {
?>
           <table style="text-align: left;">
                <tr>
                    <td><strong><?php
            _e( 'Version:', 'pareto_security_settings' );
?></strong> <?php
            echo PARETO_VERSION;
?> <?php
            echo '&middot;';
?> <strong><?php
            _e( 'Released:', 'pareto_security_settings' );
?></strong> <?php
            echo PARETO_RELEASE_DATE;
?> ( <?php
            echo $this->time_zone;
?> )</td>
                    <td><strong>Author:</strong> <a target="_blank" href="https://twitter.com/te_taipo">@te_taipo</a></td>
                    <td><strong>Web:</strong> <a target="_blank" href="https://hokioisecurity.com">https://hokioisecurity.com</a></td>
                    <td><strong>Email:</strong> pareto-security@hokioisecurity.com</td>
                </tr>
                <tr>
                    <td><strong>Rate This Plugin:</strong> <a href="https://wordpress.org/support/plugin/pareto-security/reviews/" target="_blank">Rate this plugin 5 stars on WordPress.org</a>
                    </td>
                </tr>
            </table>
<?php
        }
        
        function donations_box() {
?>
       <p><strong>Bitcoin Address:</strong> 1HnQtSEXZXvL6sfgXRZ8sAhVmtMtwXfSyf</p>
        <p><strong>ZCASH Address:</strong> t1Lnmn4r9jVxhjhTLix8sRfyoqqsJVbShQ1</p>
        <p><strong>Vericoin:</strong> VRsjYZmjpYxXmhRxGzYcECfpNUksvBr25v</p>
        <p><strong>Ethereum:</strong> 0xb9f7a75530ef6b4b21c721a81fe54c548492f9bf</p>
        <p><strong>Paypal Address:</strong> pareto-security@protonmail.com</p>
<?php
        }
        function condition_box() {
?>
       <table style="text-align: left; background-color: #C9C9C9;">
            <tr>
                <td>
                <table style="width: 1050px;">
                    <tr style="background-color:#5F607B">
                      <td style="width:110px"><font color="#FFFFF"><b>&nbsp;<b>Standard Mode:</b></b></font></td>
                      <td style="width:100px"><font color="#FFFFF"><b>&nbsp;<b>Advanced Mode:</b></b></font></td>
                    </tr>
                    <tr style="text-align: left; background-color: #E8E8E8;">
                        <td style="width: 400px; vertical-align:top;">
                            <dl>
                                <dt>&nbsp;&nbsp;- <b>Standard Mode</b> is the recommended setting</dt>
                                <dt>&nbsp;&nbsp;- Hard ban attempts to attack the webserver</dt>
                                <dt>&nbsp;&nbsp;- Hard ban attempts to inject malicious code into the database</dt>
                                <dt>&nbsp;&nbsp;- Hard ban injection attempts via browser user-agents</dt>
                                <dt>&nbsp;&nbsp;- Does not filter User-Agent</dt>
                                <dt>&nbsp;&nbsp;- Advanced POST Filtering is disabled</dt>
                            </dl>
                         </td>
                        <td style="width: 500px; vertical-align:top;">
                            <table>
                                <tr>
                                  <td>
                                      <input type="hidden" name="<?php
            echo $this->get_field_name( 'ban_mode' );
?>" id="<?php
            echo $this->get_field_id( 'ban_mode' );
?>" value="1" />
                                      <input type="checkbox" name="<?php
            echo $this->get_field_name( 'advanced_mode' );
?>" id="<?php
            echo $this->get_field_id( 'advanced_mode' );
?>" value="1" <?php
            if ( ( isset( $this->options[ 'advanced_mode' ] ) && false !== ( bool ) $this->options[ 'advanced_mode' ] ) || false !== ( bool ) $this->_adv_mode || false !== ( bool ) $this->_hard_ban_mode ) {
?>checked<?php
            }
?> /></td>
                                  <td>
                                <label for="<?php
            echo $this->get_field_id( 'advanced_mode' );
?>"><?php
            _e( '<b>Set Advanced Mode</b>', 'pareto_security_settings' );
?></label></td>
                                </tr>
                                
                                <tr>
                                  <td></td>
                                  <td>Note: For detailed descriptions, <a target="_blank" href="https://hokioisecurity.com/?p=343">click here</a></td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Hard ban attempts to attack the webserver</td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Hard ban attempts to inject malicious code into the database</td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Hard ban injection attempts via browser user-agents</td>
                                </tr>
                                <tr>
                                <tr>
                                  <td></td>
                                  <td>- Advanced filtering of HTTP_HOST</td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Soft Ban Bots</td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Advanced POST Filtering</td>
                                </tr>
                                <tr>
                                  <td></td>
                                  <td>- Domain Name Safe List</td>
                                </tr>
                            <tr>
                                  <td></td>
                                  <td>- Filter login attempts (beta)</td>
                                </tr>
                                <tr>
                                  <td><input type="checkbox" name="<?php
            echo $this->get_field_name( 'hard_ban_mode' );
?>" id="<?php
            echo $this->get_field_id( 'hard_ban_mode' );
?>" value="1" <?php
            if ( !isset( $this->options[ 'advanced_mode' ] ) || ( isset( $this->options[ 'advanced_mode' ] ) && false === ( bool ) $this->options[ 'advanced_mode' ] ) || false === ( bool ) $this->_adv_mode ) {
?>disabled="disabled"<?php
            }
?><?php
            if ( isset( $this->options[ 'hard_ban_mode' ] ) && isset( $this->options[ 'advanced_mode' ] ) && false !== ( bool ) $this->_hard_ban_mode ) {
?>checked<?php
            }
?> /></td>
                                  <td>- <label for="<?php
            echo $this->get_field_id( 'hard_ban_mode' );
?>"><?php
            _e( 'Hard ban all bad requests (<b>WARNING: Not Recommended!!!</b>)', 'pareto_security_settings' );
?></label></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                </td>
            </tr>
        </table>
<?php
        }
        function notes_box() {
            $mode     = ( false === ( bool ) $this->_adv_mode ) ? 'Standard' : 'Advanced';
            $ban_type = ( false !== ( bool ) $this->_adv_mode && false !== ( bool ) $this->_hard_ban_mode ) ? 'Low, Medium and High severity requests added to banned IP list' : 'Medium and High severity requests added to banned IP list';
?>   <ul>
        <li>+ Status: <i><?php
            echo $mode;
?> Mode</i></li>
<?php
            if ( file_exists( $this->htapath() ) && $this->get_file_perms( $this->htapath(), true, true ) ) {
?>
       <li>+ Your <code>.htaccess</code> is configured correctly in <code><?php
                echo $this->get_dir();
?></code></li>
        <li>+ <?php
                echo ( $this->_adv_mode ) ? 'Hard Ban' : 'Soft Ban';
?>: <?php
                echo $ban_type;
?></li>
        
<?php
            } else {
?>      <li>- Your <code>.htaccess</code> file cannot be written to in <code><?php
                echo $this->get_dir();
?></code> Pareto Security will still soft ban attack vectors.</li>
        <li>- Hard Ban: <?php
                echo $ban_type;
?></li>
<?php
            }
?>
       <li><?php
            echo ( version_compare( phpversion(), '5.4', '>=' ) ) ? '+' : '-';
?> Your server is running PHP version <?php
            echo substr( phpversion(), 0, 3 );
?><?php
            echo ( version_compare( phpversion(), '5.4', '>=' ) ) ? ' &#x2713&#x2713&#x2713;' : ' <b>WARNING:</b> This version is insecure. Contact your webhost to upgrade to at least PHP 5.4';
?> </li>
    </ul>
<?php
        }
        function logfile_box() {
?>
       <table style="text-align: left; background-color: #C9C9C9;">
            <tr>
                <td>
                <table style="width: 1100px; text-align: left;">
                    <tbody>
                      <tr style="background-color:#5F607B">
                        <td style="width:90px"><font color="#FFFFF"><b>Date-Time:</b></font></td>
                        <td style="width:30px"><font color="#FFFFF"><b>Severity:</b></font></td>
                        <td style="width:150px"><font color="#FFFFF"><b>IP Address:</b></font></td>
                        <td style="width:30px"><font color="#FFFFF"><b>Req:</b></font></td>
                        <td style="width:50px"><font color="#FFFFF"><b>Filename:</b></font></td>
                        <td><font color="#FFFFF"><b>Attack String:</b></font></td>
                      </tr>
                      <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
<?php
        $mylogs     = array();
        $mylogs_fin = array();
        $mylogs     = $this->logs;
        $i          = 0;
        $text_color = "#e68735";
        while ( $i <= 99 ) {
            if ( isset( $mylogs[ $i ] ) ) {
                $row_colour = ( $i % 2 == 0 ) ? "#E8E8E8" : "#D0D0DE";
                $req_var    = explode( " ", $mylogs[ $i ] );
                if ( $req_var[ 1 ] == "Low" ) {
                    if ( false === ( bool ) $this->_adv_mode ) {
                        $i++;
                        continue;
                    }
                    $text_color = "#517ecf";
                } elseif ( $req_var[ 1 ] == "Medium" ) {
                    $text_color = "#e68735";
                } elseif ( empty( $req_var[ 1 ] ) ) {
                    $req_var[ 1 ] = "Medium";
                    $text_color   = "#e68735";
                } else
                    $text_color = "#c72b2c";
                if ( $req_var[ 1 ] == "Safe" ) $text_color = "#517ecf";
                $mylogs_fin[ $i ] = $mylogs[ $i ];
                $ip_addr          = ( false !== ( bool ) $this->check_ip( $req_var[ 2 ] ) ) ? ' <a target="_blank" href="https://mxtoolbox.com/SuperTool.aspx?action=blacklist%3a' . $req_var[ 2 ] . '&run=networktools">[Blacklist]</a> <a target="_blank" href="https://www.whois.com/whois/' . $req_var[ 2 ] . '">' . $req_var[ 2 ] . '</a>' : 'Invalid IP';
                if ( $req_var[ 1 ] == "Safe" ) {
                    $req_var[ 0 ] = str_replace( '%20', ' ', $req_var[ 0 ] );
                    $text_color   = "#517ecf";
                }
                $attack_string = str_replace( '%20', " ", preg_replace( "/[\n]/i", "", stripslashes( $req_var[ 5 ] ) ) );
                
                echo "<tr style=\"background-color:" . $row_colour . "\">" . "    <td style=\"vertical-align:top; width:90px; white-space: nowrap\">" . $req_var[ 0 ] . "</td>" . "    <td style=\"vertical-align:top; text-align: center; width:30px; white-space: nowrap; font-weight: bold; color:" . $text_color . "\">" . $req_var[ 1 ] . "</td>" . "    <td style=\"vertical-align:top; width:150px; white-space: nowrap\">" . $ip_addr . "</td>" . "    <td style=\"vertical-align:top; width:30px; white-space: nowrap\">" . $req_var[ 3 ] . "</td>" . "    <td style=\"vertical-align:top; width:50px; white-space: nowrap\">" . $req_var[ 4 ] . "</td>" . "    <td style=\"vertical-align:top; white-space: nowrap\"><code>" . $attack_string . "</code></td>" . "</tr>";
            } else
                break;
            $i++;
        }
?>
               </table>
                </code>
                </td>
            </tr>
        </table>
<?php
        }
        function do_settings_box() {
            if ( ( false !== ( bool ) defined( 'WP_ADMIN' ) && false !== WP_ADMIN ) && ( false !== ( bool ) is_admin() ) ) {
                do_settings_sections( 'pareto_settings_page' );
            }
        }
    } // end class
else:
    // pareto_settings.php called directly
    require_once( 'pareto_functions.php' );
    $ParetoSecurity = new pareto_functions();
    $ParetoSecurity->send444();
endif;
?>
