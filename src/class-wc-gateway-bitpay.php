<?php
/*
	Plugin Name: BitPay WooCommerce Payment Gateway
	Plugin URI:  https://bitpay.com
	Description: BitPay WooCommerce Payment Gateway allows you to accept bitcoins on your WooCommerce store.
	Author:      BitPay
	Author URI:  https://bitpay.com

	Version: 	       2.0.0
	License:           Copyright 2011-2014 BitPay Inc., MIT License
	License URI:       https://github.com/bitpay/woocommerce-bitpay/blob/master/LICENSE
	GitHub Plugin URI: https://github.com/bitpay/woocommerce-bitpay
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load up the BitPay library
require_once __DIR__ . '/vendor/autoload.php';

// Ensures WooCommerce is loaded before initializing the BitPay plugin
add_action('plugins_loaded', 'woocommerce_bitpay_init', 0);

function woocommerce_bitpay_init()
{
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

        class WC_Gateway_Bitpay extends WC_Payment_Gateway
        {
            /**
		     * Constructor for the gateway.
		     */
            public function __construct()
            {

                $this->id                 = 'bitpay';
                $this->icon               = plugin_dir_url(__FILE__).'bitpay.png';
                $this->has_fields         = false;
                $this->method_title       = 'BitPay';
                $this->method_description = 'BitPay allows you to accept bitcoin on your WooCommerce store.';

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables
                $this->title              = $this->get_option( 'title' );
                $this->description        = $this->get_option( 'description' );

                // Define BitPay settings
                $this->api_key            = unserialize(get_option( 'woocommerce_bitpay_key' ));
                $this->api_pub            = unserialize(get_option( 'woocommerce_bitpay_pub' ));
                $this->api_sin            = get_option( 'woocommerce_bitpay_sin' );
                $this->api_token          = unserialize(get_option( 'woocommerce_bitpay_token' ));
                $this->api_token_label    = get_option( 'woocommerce_bitpay_label' );
                $this->api_network        = get_option( 'woocommerce_bitpay_network' );

                $this->redirect_url       = WC()->api_request_url( 'WC_Gateway_Bitpay' );

                // Actions
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_order_states' ) );

            }

            public function is_valid_for_use()
            {
                // TODO: Check for valid settings and the ability to create invoices (account not over limit, correct currency, etc)
                return true;
            }

            /**
		     * Initialise Gateway Settings Form Fields
		     */
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'woocommerce' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable Bitcoin via BitPay', 'bitpay' ),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default'     => __( 'Bitcoin - BitPay', 'bitpay' ),
                    ),
                    'message' => array(
                        'title' => __( 'Customer Message', 'woothemes' ),
                        'type' => 'textarea',
                        'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'bitpay' ),
                        'default' => 'You will be redirected to bitpay.com to complete your purchase.'
                    ),
                    'api_token' => array(
                        'type'        => 'api_token'
                    ),
                    'transactionSpeed' => array(
                        'title' => __('Risk/Speed', 'bitpay'),
                        'type' => 'select',
                        'description' => 'Choose a transaction speed.  For details, see the API documentation at bitpay.com',
                        'options' => array(
                            'high' => 'High',
                            'medium' => 'Medium',
                            'low' => 'Low',
                        ),
                        'default' => 'high',
                    ),
                    'fullNotifications' => array(
                        'title' => __('Full Notifications', 'bitpay'),
                        'type' => 'checkbox',
                        'description' => 'Yes: receive an email for each status update on a payment.<br>No: receive an email only when payment is confirmed.',
                        'default' => 'no',
                    ),
                    'order_states' => array(
                        'type'        => 'order_states'
                    )
                );
            }

            /**
    	     * HTML output for form field type `api_token`
    	     */
            public function generate_api_token_html()
            {
                ob_start();

                // TODO: CSS Imports aren't optimal, but neither is this.  Maybe include the css to be css-minimized?
                wp_enqueue_style( 'font-awesome', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css' );
                wp_enqueue_style( 'woocommerce-bitpay', plugins_url( 'woocommerce-bitpay/assets/css/style.css') );
                wp_enqueue_script( 'woocommerce-bitpay', plugins_url( 'woocommerce-bitpay/assets/js/pairing.js'), array('jquery'), null, true);

                $pairing_form = file_get_contents(plugin_dir_url(__FILE__).'templates/pairing.tpl');
                $token_format = file_get_contents(plugin_dir_url(__FILE__).'templates/token.tpl');

                ?>
    		    <tr valign="top">
    	            <th scope="row" class="titledesc">API Token:</th>
    	            <td class="forminp" id="bitpay_api_token">
    	            	<div id="bitpay_api_token_form">
    		            	<?php
                                if (empty($this->api_token)) {
                                    echo sprintf($pairing_form, 'visible');
                                    echo sprintf($token_format, 'hidden', plugins_url( 'woocommerce-bitpay/assets/img/logo.png' ),'','');
                                } else {
                                    echo sprintf($pairing_form, 'hidden');
                                    echo sprintf($token_format, $this->api_network, plugins_url( 'woocommerce-bitpay/assets/img/logo.png' ), $this->api_token_label, $this->api_sin);
                                }

                            ?>
    				    </div>
    			       	<script type="text/javascript">
                            var ajax_loader_url = '<?= plugins_url( 'woocommerce/assets/images/ajax-loader.gif' ); ?>';
    					</script>
    	            </td>
    		    </tr>
    	        <?php

                return ob_get_clean();
            }

            /**
             * HTML output for form field type `order_states`
             */
            public function generate_order_states_html()
            {
                ob_start();

                // TODO: CSS Imports aren't optimal, but neither is this.  Maybe include the css to be css-minimized?
                wp_enqueue_style( 'font-awesome', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css' );
                wp_enqueue_style( 'woocommerce-bitpay', plugins_url( 'woocommerce-bitpay/assets/css/style.css') );
                wp_enqueue_script( 'woocommerce-bitpay', plugins_url( 'woocommerce-bitpay/assets/js/pairing.js'), array('jquery'), null, true);

                $pairing_form = file_get_contents(plugin_dir_url(__FILE__).'templates/pairing.tpl');
                $token_format = file_get_contents(plugin_dir_url(__FILE__).'templates/token.tpl');

                $bp_statuses = ['new'=>'New', 'paid'=>'Paid', 'confirmed'=>'Confirmed', 'complete'=>'Complete', 'expired'=>'Expired', 'invalid'=>'Invalid'];
                $df_statuses = ['new'=>'wc-pending', 'paid'=>'wc-processing', 'confirmed'=>'wc-processing', 'complete'=>'wc-completed', 'expired'=>'wc-failed', 'invalid'=>'wc-failed'];
                $wc_statuses = wc_get_order_statuses();

                ?>
                <tr valign="top">
                    <th scope="row" class="titledesc">Order States:</th>
                    <td class="forminp" id="bitpay_order_states">
                        <table cellspacing="0">
                            <?php

                            foreach ($bp_statuses as $bp_state => $bp_name) {
                                ?>
                                <tr>
                                <th><?= $bp_name; ?></th>
                                <td>
                                    <select name="bitpay_order_state[<?= $bp_state; ?>]">
                                    <?php

                                    foreach ($wc_statuses as $wc_state => $wc_name) {
                                        $current_option = get_option('woocommerce_bitpay_order_state_'.$bp_state);
                                        if (empty($current_option)) {
                                            $current_option = $df_statuses[$bp_state];
                                        }
                                        if ($current_option === $wc_state) {
                                            echo "<option value=\"$wc_state\" selected>$wc_name</option>\n";
                                        } else {
                                            echo "<option value=\"$wc_state\">$wc_name</option>\n";
                                        }
                                    }

                                    ?>
                                    </select>
                                </td>
                                </tr>
                                <?php
                            }

                            ?>
                        </table>
                    </td>
                </tr>
                <?php

                return ob_get_clean();
            }

            /**
             * Save order states
             */
            public function save_order_states()
            {
                $bp_statuses = ['new'=>'New', 'paid'=>'Paid', 'confirmed'=>'Confirmed', 'complete'=>'Complete', 'expired'=>'Expired', 'invalid'=>'Invalid'];
                $wc_statuses = wc_get_order_statuses();

                if ( isset( $_POST['bitpay_order_state'] ) ) {

                    foreach ($bp_statuses as $bp_state => $bp_name) {
                        if ( ! isset( $_POST['bitpay_order_state'][ $bp_state ] ) ) {
                            continue;
                        }

                        $wc_state = $_POST['bitpay_order_state'][ $bp_state ];
                        if (array_key_exists($wc_state, $wc_statuses)) {
                            update_option('woocommerce_bitpay_order_state_'.$bp_state, $wc_state );
                        }

                    }
                }

            }

            /**
    	     * Output for the order received page.
    	     */
            public function thankyou_page($order_id)
            {
            }

            /**
    	     * Process the payment and return the result
    	     *
    	     * @param int $order_id
    	     * @return array
    	     */
            public function process_payment($order_id)
            {
                $order = wc_get_order( $order_id );

                // Mark as on-hold (we're awaiting the payment)
                $order->update_status( 'on-hold', 'Awaiting payment confirmation.' );

                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                WC()->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result'    => 'success',
                    'redirect'    => $this->get_return_url( $order )
                );
            }

        }

    /**
 	* Add BitPay Payment Gateway to WooCommerce
 	**/
    function wc_add_bitpay($methods)
    {
        $methods[] = 'WC_Gateway_Bitpay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_bitpay' );

    /**
	* Add Settings link to the plugin entry in the plugins menu for WC below 2.1
	**/
    if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

        add_filter('plugin_action_links', 'bitpay_plugin_action_links', 10, 2);

        function bitpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=wc_gateway_bitpay">Settings</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }
    }
    /**
	* Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
	**/
    else{
        add_filter('plugin_action_links', 'bitpay_plugin_action_links', 10, 2);

        function bitpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_bitpay">Settings</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }
    }

    // TODO: Try to find a way to make it work within the WC_Gateway_Bitpay class
    add_action( 'wp_ajax_bitpay_pair_code', 'ajax_bitpay_pair_code' );
    add_action( 'wp_ajax_bitpay_revoke_token', 'ajax_bitpay_revoke_token' );
    add_action( 'wp_ajax_bitpay_create_invoice', 'ajax_bitpay_create_invoice' );

    function ajax_bitpay_pair_code()
    {
        // Validate the Pairing Code
        $pairing_code = $_POST['pairing_code'];
        if (!preg_match('/^[a-zA-Z0-9]{7}$/',$pairing_code)) {
            wp_send_json(array("error"=>"Invalid Pairing Code"));
        }

        // Validate the Network
        $network = ($_POST['network'] === 'livenet') ? 'livenet' : 'testnet';

        // Generate Private Key
        $key = new \Bitpay\PrivateKey();
        $key->generate();

        // Generate Public Key
        $pub = new \Bitpay\PublicKey();
        $pub->setPrivateKey($key);
        $pub->generate();

        // Get SIN Format
        $sin = new \Bitpay\SinKey();
        $sin->setPublicKey($pub);
        $sin->generate();

        // Create an API Client
        $client = new \Bitpay\Client\Client();
        if ($network === 'livenet') {
            $client->setNetwork(new \Bitpay\Network\Livenet());
        } else {
            $client->setNetwork(new \Bitpay\Network\Testnet());
        }
        $client->setAdapter(new \Bitpay\Client\Adapter\CurlAdapter());
        $client->setPrivateKey($key);
        $client->setPublicKey($pub);

        try {
            $token = $client->createToken(
                array(
                    'id'          => (string) $sin,
                    'pairingCode' => $pairing_code,
                    'label'       => "WooCommerce - {$_SERVER['SERVER_NAME']}",
                )
            );
        } catch (Exception $e) {
            wp_send_json(array("error"=>$e->getMessage()));
        }

        update_option('woocommerce_bitpay_key', serialize($key));
        update_option('woocommerce_bitpay_pub', serialize($pub));
        update_option('woocommerce_bitpay_sin', (string) $sin);
        update_option('woocommerce_bitpay_token', serialize($token));
        update_option('woocommerce_bitpay_label', "WooCommerce - {$_SERVER['SERVER_NAME']}");
        update_option('woocommerce_bitpay_network', $network);
        wp_send_json(array('sin'=>(string) $sin, 'label'=>"WooCommerce - {$_SERVER['SERVER_NAME']}", 'network'=>$network));
    }

    function ajax_bitpay_revoke_token()
    {
        update_option('woocommerce_bitpay_key', null);
        update_option('woocommerce_bitpay_pub', null);
        update_option('woocommerce_bitpay_sin', null);
        update_option('woocommerce_bitpay_token', null);
        update_option('woocommerce_bitpay_label', null);
        update_option('woocommerce_bitpay_network', null);
        wp_send_json(array('success'=>'Token Revoked!'));
    }

    function ajax_bitpay_create_invoice()
    {
        $key            = unserialize(get_option('woocommerce_bitpay_key'));
        $pub            = unserialize(get_option('woocommerce_bitpay_pub'));
        $sin            = get_option('woocommerce_bitpay_sin');
        $token           = unserialize(get_option('woocommerce_bitpay_token'));

        $client = new \Bitpay\Client\Client();
        $client->setNetwork(new \Bitpay\Network\Livenet());
        $client->setAdapter(new \Bitpay\Client\Adapter\CurlAdapter());
        $client->setPrivateKey($key);
        $client->setPublicKey($pub);
        $client->setToken($token);

        $invoice = new \Bitpay\Invoice();
        $invoice->setOrderId('TEST-01');

        $currency = new \Bitpay\Currency();
        $currency->setCode('USD');
        $invoice->setCurrency($currency);

        $item = new \Bitpay\Item();
        $item->setPrice('19.95');
        $invoice->setItem($item);
        try {
            $invoice = $client->createInvoice($invoice);
        } catch (Exception $e) {
            echo "Sin: $sin\n";
            echo "Key: $key\n";
            echo "Pub: $pub\n";
        }
        //var_dump($invoice);
    }
}
