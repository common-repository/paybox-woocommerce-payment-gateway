<?php
	/**
	 * Plugin Name: Paybox WooCommerce Payment Gateway
	 * Plugin URI: http://walliecreation.com/
	 * Description: Gateway e-commerce for Paybox. Initial release by SWO (Open Boutique). Partially recoded by V. Pintat to fit whith WooCommerce 2.4.6 and up... 
	 * Version: 1.0.5
	 * Author: Vincent Pintat
     * Author URI: http://walliecreation.com/
	 * Text Domain: paybox_gateway
     * Domain Path: /lang
	 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
	 *
	 * @package WordPress
	 * @author Vincent Pintat
	 * @since 0.1.0
	 */

	if(!defined('ABSPATH'))
		exit;

	function activate_paybox_gateway()
	{
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if( !is_plugin_active('woocommerce/woocommerce.php') )
		{
			_e('WooCommerce must be installed and activated in order to use this plugin !', 'paybox_gateway');
			exit;
		}
		if( !class_exists('WC_Payment_Gateway') )
		{
			_e('An error as occured with WooCommerce: can not find gateway methods...', 'paybox_gateway');
			exit;
		}
	}
	register_activation_hook(__FILE__, 'activate_paybox_gateway');
	add_action('plugins_loaded', 'woocommerce_paybox_init', 0);

	function woocommerce_paybox_init()
	{
		if( class_exists('WC_Payment_Gateway') )
		{
			include_once( plugin_dir_path( __FILE__ ).'woocommerce_paybox_gateway.class.php' );
			include_once( plugin_dir_path( __FILE__ ).'shortcode_woocommerce_paybox_gateway.php' );
		} else
			exit;

		DEFINE('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)));
		DEFINE('VERSION', '1.0.5');
		DEFINE('THANKS_SHORTCODE', 'woocommerce_paybox_gateway_thanks');

		load_plugin_textdomain('paybox_gateway', false, dirname(plugin_basename(__FILE__)).'/lang/');
		add_shortcode( THANKS_SHORTCODE, 'WC_Shortcode_Paybox_Thankyou::get' );
		add_filter('woocommerce_payment_gateways', 'add_paybox_commerce_gateway');
		add_action('init', 'woocommerce_paybox_check_response');
	}

	/*
	 * Ajout de la "gateway" Paybox à woocommerce
	 */
	function add_paybox_commerce_gateway($methods)
	{
		$methods[] = 'WC_Paybox';
		return $methods;
	}

	/**
     * Reponse Paybox (Pour le serveur Paybox)
     *
     * @access public
     * @return void
     */
    function woocommerce_paybox_check_response(){
        if (isset($_GET['order']) && isset($_GET['sign'])){ // On a bien un retour ave une commande et une signature
            $order = new WC_Order((int) $_GET['order']); // On récupère la commande
            $pos_qs = strpos($_SERVER['REQUEST_URI'], '?');
            $pos_sign = strpos($_SERVER['REQUEST_URI'], '&sign=');
            $return_url = substr($_SERVER['REQUEST_URI'], 1, $pos_qs - 1);
            $data = substr($_SERVER['REQUEST_URI'], $pos_qs + 1, $pos_sign - $pos_qs - 1);
            $sign = substr($_SERVER['REQUEST_URI'], $pos_sign + 6);
            //$sign = $_GET['sign'];
            // Est-on en réception d'un retour PayBox
            $my_WC_Paybox = new WC_Paybox();
            $std_msg = __('Paybox Return IP', $my_WC_Paybox->text_domain).' : '.WC_Paybox::getRealIpAddr().'<br/>'.$data.'<br/><div style="word-wrap:break-word;">'.__('PBX Sign', $my_WC_Paybox->text_domain).' : '. $sign . '<div>';
                //@ob_clean();
                // Traitement du retour PayBox
                // PBX_RETOUR=order:R;erreur:E;carte:C;numauto:A;numtrans:S;numabo:B;montantbanque:M;sign:K
                if (isset($_GET['erreur'])){
                    if($_GET['erreur'] == '00000'){
                            // OK Pas de pb
                            // On vérifie la clef
                            // recuperation de la cle publique
                            $fp = $filedata = $key = FALSE;
                            $fsize = filesize(dirname(__FILE__) . '/lib/pubkey.pem');
                            $fp = fopen(dirname(__FILE__) . '/lib/pubkey.pem', 'r');
                            $filedata = fread($fp, $fsize);
                            fclose($fp);
                            $key = openssl_pkey_get_public($filedata);
                            $decoded_sign = base64_decode(urldecode($sign));
                            $verif_sign = openssl_verify($data, $decoded_sign, $key);
                            if ($verif_sign == 1) {   // La commande est bien signé par PayBox
                                // Si montant ok
                                if ((int) (100 * $order->get_total()) == (int) $_GET['montantbanque']) {
                                    $order->add_order_note('<p style="color:green"><b>'.__('Paybox Return OK', $my_WC_Paybox->text_domain).'</b></p><br/>' . $std_msg);
                                    $order->payment_complete();
                                }
                                else{
                                	$order->add_order_note('<p style="color:red"><b>'.__('ERROR', $my_WC_Paybox->text_domain).'</b></p> '.__('Order Amount', $my_WC_Paybox->text_domain).'.<br/>' . $std_msg);
                                	wp_die(__('KO Amount modified', $my_WC_Paybox->text_domain).' : ' . $_GET['montantbanque'] . ' / ' . (100 * $order->get_total()), '', array('response' => 406));
                                }
                                
                            }
                            else{
                            	 $order->add_order_note('<p style="color:red"><b>'.__('ERROR', $my_WC_Paybox->text_domain).'</b></p> '.__('Signature Rejected', $my_WC_Paybox->text_domain).'.<br/>' . $std_msg);
                           		 wp_die(__('KO Signature', $my_WC_Paybox->text_domain), '', array('response' => 406));
                            }
                           
                        } // end error = 00000
                        
                        else{
                        	$order->add_order_note('<p style="color:red"><b>'.__('PBX ERROR', $my_WC_Paybox->text_domain).' ' . $_GET['erreur'] . '</b> ' . WC_Paybox::getErreurMsg($_GET['erreur']) . '</p><br/>' . $std_msg);
                            $order->cancel_order();
                        }
                            
                    }
                
        } //endif GET error & sign
    }

?>