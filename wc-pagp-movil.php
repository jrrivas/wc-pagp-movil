<?php
/*
Plugin Name: WooCommerce Pago Móvil
Description: Añade el método de pago Pago Móvil a WooCommerce
Version: 1.0.0
Author: Junnior Rivas
Text Domain: Woocommerce-pago-movil
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'init_pago_movil_gateway');

function init_pago_movil_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Pago_Movil_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'pago_movil';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Pago Móvil', 'woocommerce-pago-movil');
            $this->method_description = __('Permite pagos mediante transferencia móvil con comprobante', 'woocommerce-pago-movil');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->phone_number = $this->get_option('phone_number');
            $this->bank_name = $this->get_option('bank_name');
            $this->max_file_size = $this->get_option('max_file_size', 5);

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_pago_movil_data_in_admin'), 10, 1);
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Habilitar/Deshabilitar', 'woocommerce-pago-movil'),
                    'type'    => 'checkbox',
                    'label'   => __('Habilitar Pago Móvil', 'woocommerce-pago-movil'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __('Título', 'woocommerce-pago-movil'),
                    'type'        => 'text',
                    'description' => __('Título que el usuario verá durante el checkout.', 'woocommerce-pago-movil'),
                    'default'     => __('Pago Móvil', 'woocommerce-pago-movil'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Descripción', 'woocommerce-pago-movil'),
                    'type'        => 'textarea',
                    'description' => __('Descripción del método de pago que el cliente verá en tu tienda.', 'woocommerce-pago-movil'),
                    'default'     => __('Realiza el pago mediante transferencia móvil y sube el comprobante.', 'woocommerce-pago-movil'),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __('Instrucciones', 'woocommerce-pago-movil'),
                    'type'        => 'textarea',
                    'description' => __('Instrucciones que se añadirán a la página de agradecimiento y emails.', 'woocommerce-pago-movil'),
                    'default'     => __('Por favor realiza el pago móvil a nuestro número de teléfono y sube el comprobante con el número de referencia. Tu pedido se procesará una vez verifiquemos el pago.', 'woocommerce-pago-movil'),
                    'desc_tip'    => true,
                ),
                'phone_number' => array(
                    'title'       => __('Número de teléfono para pagos', 'woocommerce-pago-movil'),
                    'type'        => 'text',
                    'description' => __('Número de teléfono al que los clientes deben enviar el pago móvil.', 'woocommerce-pago-movil'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'bank_name' => array(
                    'title'       => __('Nombre del banco', 'woocommerce-pago-movil'),
                    'type'        => 'text',
                    'description' => __('Nombre del banco asociado al pago móvil.', 'woocommerce-pago-movil'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'max_file_size' => array(
                    'title'       => __('Tamaño máximo de archivo (MB)', 'woocommerce-pago-movil'),
                    'type'        => 'number',
                    'description' => __('Tamaño máximo permitido para el comprobante en MB.', 'woocommerce-pago-movil'),
                    'default'     => 5,
                    'desc_tip'    => true,
                )
            );
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            if ($this->phone_number) {
                echo '<p>' . sprintf(__('Realiza el pago móvil al número: <strong>%s</strong>', 'woocommerce-pago-movil'), esc_html($this->phone_number)) . '</p>';
            }
            
            if ($this->bank_name) {
                echo '<p>' . sprintf(__('Banco: <strong>%s</strong>', 'woocommerce-pago-movil'), esc_html($this->bank_name)) . '</p>';
            }

            // Campos del formulario
            ?>
            <div class="pago-movil-container">
                <div class="pago-movil-grid">
                    <div class="pago-movil-field">
                        <label class="pago-movil-label" for="pago-movil-telefono"><?php esc_html_e('Número de teléfono', 'woocommerce-pago-movil'); ?></label>
                        <input type="tel" id="pago-movil-telefono" name="pago_movil_telefono" class="pago-movil-input" 
                               placeholder="<?php esc_attr_e('Ej: 04141234567', 'woocommerce-pago-movil'); ?>" required pattern="[0-9]{11}">
                        <div class="pago-movil-error" id="telefono-error">
                            <?php esc_html_e('Por favor ingresa un número de teléfono válido (11 dígitos)', 'woocommerce-pago-movil'); ?>
                        </div>
                    </div>
                    
                    <div class="pago-movil-field">
                        <label class="pago-movil-label" for="pago-movil-referencia"><?php esc_html_e('Número de referencia', 'woocommerce-pago-movil'); ?></label>
                        <input type="text" id="pago-movil-referencia" name="pago_movil_referencia" class="pago-movil-input" 
                               placeholder="<?php esc_attr_e('Número de referencia del pago', 'woocommerce-pago-movil'); ?>" required>
                        <div class="pago-movil-error" id="referencia-error">
                            <?php esc_html_e('Por favor ingresa el número de referencia', 'woocommerce-pago-movil'); ?>
                        </div>
                    </div>
                    
                    <div class="pago-movil-field">
                        <label class="pago-movil-label" for="pago-movil-fecha"><?php esc_html_e('Fecha del pago', 'woocommerce-pago-movil'); ?></label>
                        <input type="date" id="pago-movil-fecha" name="pago_movil_fecha" class="pago-movil-date" required>
                        <div class="pago-movil-error" id="fecha-error">
                            <?php esc_html_e('Por favor selecciona la fecha del pago', 'woocommerce-pago-movil'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="pago-movil-file-container">
                    <label class="pago-movil-label"><?php esc_html_e('Captura de pantalla del comprobante', 'woocommerce-pago-movil'); ?></label>
                    <label for="pago-movil-imagen" class="pago-movil-file-label">
                        <div class="pago-movil-file-text">
                            <span id="pago-movil-file-name"><?php esc_html_e('Haz clic para subir el comprobante', 'woocommerce-pago-movil'); ?></span>
                        </div>
                        <div class="pago-movil-file-info">
                            <?php 
                            printf(
                                esc_html__('Formatos aceptados: JPG, PNG, PDF (Max. %dMB)', 'woocommerce-pago-movil'), 
                                absint($this->max_file_size)
                            ); 
                            ?>
                        </div>
                        <input type="file" id="pago-movil-imagen" name="pago_movil_comprobante" accept="image/*,.pdf" style="display: none;" required>
                    </label>
                    <div class="pago-movil-error" id="imagen-error">
                        <?php esc_html_e('Por favor sube el comprobante del pago', 'woocommerce-pago-movil'); ?>
                    </div>
                    <img id="pago-movil-preview" class="pago-movil-preview" alt="<?php esc_attr_e('Vista previa del comprobante', 'woocommerce-pago-movil'); ?>">
                </div>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    // Manejo de la vista previa de la imagen
                    $('#pago-movil-imagen').on('change', function(e) {
                        var file = e.target.files[0];
                        if (file) {
                            $('#pago-movil-file-name').text(file.name);
                            
                            if (file.type.startsWith('image/')) {
                                var reader = new FileReader();
                                reader.onload = function(e) {
                                    $('#pago-movil-preview').attr('src', e.target.result).show();
                                };
                                reader.readAsDataURL(file);
                            } else {
                                $('#pago-movil-preview').hide();
                            }
                            
                            $('#imagen-error').hide();
                        } else {
                            $('#pago-movil-file-name').text('<?php esc_html_e("Haz clic para subir el comprobante", "woocommerce-pago-movil"); ?>');
                            $('#pago-movil-preview').hide();
                        }
                    });
                    
                    // Validación del formulario
                    $('form.checkout').on('checkout_place_order', function() {
                        if ($('#payment_method_pago_movil:checked').length === 0) {
                            return true;
                        }
                        
                        var isValid = true;
                        var maxSize = <?php echo absint($this->max_file_size) * 1024 * 1024; ?>;
                        
                        // Validar teléfono
                        var telefono = $('#pago-movil-telefono').val();
                        if (!/^[0-9]{11}$/.test(telefono)) {
                            $('#telefono-error').show();
                            $('#pago-movil-telefono').addClass('invalid');
                            isValid = false;
                        } else {
                            $('#telefono-error').hide();
                            $('#pago-movil-telefono').removeClass('invalid');
                        }
                        
                        // Validar referencia
                        var referencia = $('#pago-movil-referencia').val().trim();
                        if (referencia === '') {
                            $('#referencia-error').show();
                            $('#pago-movil-referencia').addClass('invalid');
                            isValid = false;
                        } else {
                            $('#referencia-error').hide();
                            $('#pago-movil-referencia').removeClass('invalid');
                        }
                        
                        // Validar fecha
                        var fecha = $('#pago-movil-fecha').val();
                        if (fecha === '') {
                            $('#fecha-error').show();
                            $('#pago-movil-fecha').addClass('invalid');
                            isValid = false;
                        } else {
                            $('#fecha-error').hide();
                            $('#pago-movil-fecha').removeClass('invalid');
                        }
                        
                        // Validar imagen
                        var fileInput = $('#pago-movil-imagen')[0];
                        if (fileInput.files.length === 0) {
                            $('#imagen-error').show();
                            isValid = false;
                        } else {
                            var file = fileInput.files[0];
                            if (file.size > maxSize) {
                                $('#imagen-error').text('<?php esc_html_e("El archivo es demasiado grande", "woocommerce-pago-movil"); ?>').show();
                                isValid = false;
                            } else {
                                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                                if (allowedTypes.indexOf(file.type) === -1) {
                                    $('#imagen-error').text('<?php esc_html_e("Formato no válido (solo JPG, PNG, PDF)", "woocommerce-pago-movil"); ?>').show();
                                    isValid = false;
                                } else {
                                    $('#imagen-error').hide();
                                }
                            }
                        }
                        
                        if (!isValid) {
                            $('html, body').animate({
                                scrollTop: $('.pago-movil-error:visible').first().offset().top - 100
                            }, 500);
                        }
                        
                        return isValid;
                    });
                });
            </script>
            <?php
        }

        public function validate_fields() {
            if ($_POST['payment_method'] !== 'pago_movil') {
                return true;
            }

            $telefono = isset($_POST['pago_movil_telefono']) ? sanitize_text_field($_POST['pago_movil_telefono']) : '';
            $referencia = isset($_POST['pago_movil_referencia']) ? sanitize_text_field($_POST['pago_movil_referencia']) : '';
            $fecha = isset($_POST['pago_movil_fecha']) ? sanitize_text_field($_POST['pago_movil_fecha']) : '';

            if (empty($telefono) || !preg_match('/^[0-9]{11}$/', $telefono)) {
                wc_add_notice(__('Por favor ingresa un número de teléfono válido (11 dígitos)', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            if (empty($referencia)) {
                wc_add_notice(__('Por favor ingresa el número de referencia', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            if (empty($fecha)) {
                wc_add_notice(__('Por favor selecciona la fecha del pago', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            if (empty($_FILES['pago_movil_comprobante']['name'])) {
                wc_add_notice(__('Por favor sube el comprobante del pago', 'woocommerce-pago-movil'), 'error');
                return false;
            }

            return true;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $uploadedfile = $_FILES['pago_movil_comprobante'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $order->update_meta_data('pago_movil_comprobante', $movefile['url']);
                $order->update_meta_data('pago_movil_telefono', sanitize_text_field($_POST['pago_movil_telefono']));
                $order->update_meta_data('pago_movil_referencia', sanitize_text_field($_POST['pago_movil_referencia']));
                $order->update_meta_data('pago_movil_fecha', sanitize_text_field($_POST['pago_movil_fecha']));
            } else {
                wc_add_notice(__('Error al subir el comprobante: ', 'woocommerce-pago-movil') . $movefile['error'], 'error');
                return;
            }

            $order->update_status('on-hold', __('Esperando verificación de pago móvil.', 'woocommerce-pago-movil'));
            $order->reduce_order_stock();
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if ($this->id === $order->get_payment_method()) {
                echo '<h2>' . __('Detalles del Pago Móvil', 'woocommerce-pago-movil') . '</h2>';
                echo '<ul>';
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
                echo '<h4>' . __('Detalles de Pago Móvil', 'woocommerce-pago-movil') . '</h4>';
                echo '<p><strong>' . __('Teléfono:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($telefono) . '</p>';
                echo '<p><strong>' . __('Referencia:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($referencia) . '</p>';
                echo '<p><strong>' . __('Fecha del Pago:', 'woocommerce-pago-movil') . '</strong> ' . esc_html($fecha) . '</p>';
                echo '<p><strong>' . __('Comprobante:', 'woocommerce-pago-movil') . '</strong> <a href="' . esc_url($comprobante) . '" target="_blank">' . __('Ver Comprobante', 'woocommerce-pago-movil') . '</a></p>';
            }
        }
    }

    function add_pago_movil_gateway($methods) {
        $methods[] = 'WC_Pago_Movil_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pago_movil_gateway');
}

add_action('wp_enqueue_scripts', 'enqueue_pago_movil_scripts');

function enqueue_pago_movil_scripts() {
    if (is_checkout() && !is_wc_endpoint_url()) {
        wp_enqueue_script('jquery');
    }
}
