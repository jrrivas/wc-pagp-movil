<?php
/**
 * Plugin Name: WooCommerce Pago Móvil
 * Description: Añade el método de pago Pago Móvil a WooCommerce
 * Version: 1.0.1
 * Author: Junnior Rivas
 * Text Domain: woocommerce-pago-movil
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'jr_init_pago_movil_gateway');

function jr_init_pago_movil_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Pago_Movil_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'pago_movil';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Pago Móvil', 'woocommerce-pago-movil');
            $this->method_description = __('Permite pagos mediante transferencia móvil con comprobante.', 'woocommerce-pago-movil');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->phone_number = $this->get_option('phone_number');
            $this->bank_name = $this->get_option('bank_name');
            $this->max_file_size = absint($this->get_option('max_file_size', 5)) * 1024 * 1024;

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
            add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
            add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_pago_movil_data_in_admin']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Habilitar/Deshabilitar', 'woocommerce-pago-movil'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar Pago Móvil', 'woocommerce-pago-movil'),
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => __('Título', 'woocommerce-pago-movil'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => __('Pago Móvil', 'woocommerce-pago-movil'),
                ],
                'description' => [
                    'title' => __('Descripción', 'woocommerce-pago-movil'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'default' => __('Realiza el pago mediante transferencia móvil y sube el comprobante.', 'woocommerce-pago-movil'),
                ],
                'instructions' => [
                    'title' => __('Instrucciones', 'woocommerce-pago-movil'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'default' => __('Por favor realiza el pago móvil a nuestro número de teléfono y sube el comprobante con el número de referencia. Tu pedido se procesará una vez verifiquemos el pago.', 'woocommerce-pago-movil'),
                ],
                'phone_number' => [
                    'title' => __('Número de teléfono para pagos', 'woocommerce-pago-movil'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => '',
                ],
                'bank_name' => [
                    'title' => __('Nombre del banco', 'woocommerce-pago-movil'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => '',
                ],
                'max_file_size' => [
                    'title' => __('Tamaño máximo del archivo (MB)', 'woocommerce-pago-movil'),
                    'type' => 'number',
                    'desc_tip' => true,
                    'default' => 5,
                ],
            ];
        }

        public function payment_fields() {
            echo wpautop(wptexturize($this->description));
            ?>
            <div class="form-row form-row-wide">
                <label for"pago_movil_telefono"><?php _e('Teléfono:', 'woocommerce-pago-movil'); ?></label>
                <input type="tel" name="pago_movil_telefono" required pattern="[0-9]{11}" id="pago_movil_telefono"/>
                <span class="men-error-pago-movil" id="e_pago_movil_telefono"></span>
            </div>
            <div class="form-row form-row-wide">
                <label for="pago_movil_referencia"><?php _e('Referencia:', 'woocommerce-pago-movil'); ?></label>
                <input type="text" name="pago_movil_referencia" id="pago_movil_referencia" required />
                <span class="men-error-pago-movil" id="e_pago_movil_referencia"></span>
            </div>
            <div class="form-row form-row-wide">
                <label for="pago_movil_fecha"><?php _e('Fecha del Pago:', 'woocommerce-pago-movil'); ?></label>
                <input type="date" name="pago_movil_fecha" id="pago_movil_fecha" required />
                <span class="men-error-pago-movil" id="e_pago_movil_fecha"></span>
            </div>
            <div class="form-row form-row-wide">
                <label form="pago_movil_comprobante"><?php _e('Comprobante:', 'woocommerce-pago-movil'); ?></label>
                <input type="file" name="pago_movil_comprobante" accept="image/jpeg,image/png,application/pdf" />
                <small><?php printf(__('Formatos aceptados: JPG, PNG, PDF. Máx: %dMB', 'woocommerce-pago-movil'), ($this->max_file_size / 1024 / 1024)); ?></small>
               <span class="men-error-pago-movil" id="e_pago_movil_comprobante"></span>
            </div>
            <?php
        }

        public function validate_fields() {
            if ($_POST['payment_method'] !== 'pago_movil') return true;

            $telefono = sanitize_text_field($_POST['pago_movil_telefono']);
            $referencia = sanitize_text_field($_POST['pago_movil_referencia']);
            $fecha = sanitize_text_field($_POST['pago_movil_fecha']);
            $file = $_FILES['pago_movil_comprobante'];

            if (!preg_match('/^[0-9]{11}$/', $telefono)) {
                wc_add_notice(__('Número de teléfono inválido.', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            if (empty($referencia)) {
                wc_add_notice(__('Número de referencia requerido.', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            if (empty($fecha)) {
                wc_add_notice(__('Fecha de pago requerida.', 'woocommerce-pago-movil'), 'error');
                return false;
            }
        
            if (empty($file['name']) || $file['size'] > $this->max_file_size) {
                wc_add_notice(__('Archivo inválido o demasiado grande.', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($file['type'], $allowed_types)) {
                wc_add_notice(__('Formato de archivo no permitido.', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            return true;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            $file = $_FILES['pago_movil_comprobante'];
            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $upload = wp_handle_upload($file, ['test_form' => false]);

            if ($upload && !isset($upload['error'])) {
                $order->update_meta_data('pago_movil_telefono', sanitize_text_field($_POST['pago_movil_telefono']));
                $order->update_meta_data('pago_movil_referencia', sanitize_text_field($_POST['pago_movil_referencia']));
                $order->update_meta_data('pago_movil_fecha', sanitize_text_field($_POST['pago_movil_fecha']));
                $order->update_meta_data('pago_movil_comprobante', esc_url($upload['url']));
            } else {
                wc_add_notice(__('Error al subir el comprobante: ', 'woocommerce-pago-movil') . $upload['error'], 'error');
                return;
            }

            $order->update_status('on-hold', __('Esperando verificación de pago móvil.', 'woocommerce-pago-movil'));
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            if ($this->id === $order->get_payment_method()) {
                echo '<h2>' . __('Detalles del Pago Móvil', 'woocommerce-pago-movil') . '</h2><ul>';
                echo '<li><strong>' . __('Teléfono:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($order->get_meta('pago_movil_telefono')) . '</li>';
                echo '<li><strong>' . __('Referencia:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($order->get_meta('pago_movil_referencia')) . '</li>';
                echo '<li><strong>' . __('Fecha del Pago:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($order->get_meta('pago_movil_fecha')) . '</li>';
                echo '<li><strong>' . __('Comprobante:', 'woocommerce-pago-movil') . '</strong> <a href="' . esc_url($order->get_meta('pago_movil_comprobante')) . '" target="_blank">' . __('Ver Comprobante', 'woocommerce-pago-movil') . '</a></li>';
                echo '</ul>';
            }
        }

        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if ($this->id === $order->get_payment_method()) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        public function display_pago_movil_data_in_admin($order) {
            $telefono = $order->get_meta('pago_movil_telefono');
            $referencia = $order->get_meta('pago_movil_referencia');
            $fecha = $order->get_meta('pago_movil_fecha');
            $comprobante = $order->get_meta('pago_movil_comprobante');

            if ($telefono || $referencia || $fecha || $comprobante) {
                echo '<h3>' . __('Detalles del Pago Móvil', 'woocommerce-pago-movil') . '</h3><ul>';
                echo '<li><strong>' . __('Teléfono:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($telefono) . '</li>';
                echo '<li><strong>' . __('Referencia:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($referencia) . '</li>';
                echo '<li><strong>' . __('Fecha del Pago:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($fecha) . '</li>';
                echo '<li><strong>' . __('Comprobante:', 'woocommerce-pago-movil') . '</strong> <a href="' . esc_url($comprobante) . '" target="_blank">' . __('Ver Comprobante', 'woocommerce-pago-movil') . '</a></li>';
                echo '</ul>';
            }
        }

        public function enqueue_scripts() {
            if (is_checkout() && !is_wc_endpoint_url()) {
                wp_enqueue_script('jquery');
                wp_enqueue_script('pago-movil-checkout', plugin_dir_url(__FILE__) . 'assets/js/pago-movil.js', ['jquery'], null, true);
                wp_localize_script('pago-movil-checkout', 'PagoMovilVars', [
                    'maxSize' => $this->max_file_size,
                    'allowedTypes' => ['image/jpeg', 'image/png', 'application/pdf'],
                    'errorSize' => __('El archivo es demasiado grande.', 'woocommerce-pago-movil'),
                    'errorFormat' => __('Formato no válido. Solo JPG, PNG, PDF.', 'woocommerce-pago-movil'),
                     // ✨ Mensajes para alert()
                    'msgTelefono'  => __('Por favor ingresa un número de teléfono válido (11 dígitos).', 'woocommerce-pago-movil'),
                    'msgReferencia'=> __('Por favor ingresa el número de referencia.', 'woocommerce-pago-movil'),
                    'msgFecha'     => __('Por favor selecciona la fecha del pago.', 'woocommerce-pago-movil'),
                    'msgImagen'    => __('Por favor sube el comprobante del pago.', 'woocommerce-pago-movil'),
                    'errorSize'    => __('El archivo es demasiado grande.', 'woocommerce-pago-movil'),
                    'errorFormat'  => __('Formato no válido. Solo JPG, PNG, PDF.', 'woocommerce-pago-movil'),
                ]);
            }
        }
    }

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Pago_Movil_Gateway';
        return $gateways;
    });
}
