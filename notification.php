<?php

/**
 * Plugin Name: Cart Contact Notification
 * Description: Shows a cart reminder and sends cart contact requests by email.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
  exit;
}

class Ismat_Cart_Contact_Notification
{
  private static $instance = null;

  private $settings = array(
    'enabled_option' => 'cart_contact_notification_enabled',
    'enabled_default' => '1',
    'interval_hours' => 2,
    'email_to' => 'alisa3d2007@gmail.com, info@ismat.ru',
    'telegram_redirect_url' => 'https://t.me/IsmatDecor_official?text=',
    'vk_bot_lead_endpoint' => 'https://vk-bot.ismatdecor.ru/lead/cart',
    'vk_bot_lead_secret' => 'ismat_cart_leads_2026',
    'vk_redirect_url' => 'https://vk.com/ismatdecor',
    'notification_text' => 'Нашли дешевле ?',
    'notification_action' => 'Напишите НАМ!',
    'modal_title' => 'Куда вам написать?',
    'modal_phone_label' => 'Оставьте свой номер телефона',
    'modal_contact_label' => 'Предпочитаемый способ связи',
    'modal_submit_text' => 'OK',
    'modal_cancel_text' => 'Отмена',
    'success_text' => 'Спасибо! Мы скоро свяжемся с вами.',
    'error_text' => 'Не удалось отправить заявку. Попробуйте еще раз.',
    'contact_methods' => array(
      'max' => 'MAX',
      'tg' => 'Telegram',
      'vk' => 'VK',
    ),
  );

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  private function __construct()
  {
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_ajax_cart_contact_notification_send', array($this, 'send_notification'));
    add_action('wp_ajax_nopriv_cart_contact_notification_send', array($this, 'send_notification'));
  }

  public function enqueue_assets()
  {
    if (!class_exists('WooCommerce') || !function_exists('WC') || !WC()->cart) {
      return;
    }

    wp_enqueue_style(
      'cart-contact-notification-css',
      plugin_dir_url(__FILE__) . 'assets/css/notification.css',
      array(),
      '1.0.0'
    );

    wp_enqueue_style(
      'toastify-css',
      get_stylesheet_directory_uri() . '/assets/css/toastify/toastify.css',
      array(),
      '1.12.0'
    );

    wp_enqueue_script(
      'toastify-js',
      get_stylesheet_directory_uri() . '/assets/js/toastify/toastify.js',
      array(),
      '1.12.0',
      true
    );

    wp_enqueue_script(
      'cart-contact-utils-js',
      plugin_dir_url(__FILE__) . 'assets/js/cart-utils.js',
      array(),
      '1.0.0',
      true
    );

    wp_enqueue_script(
      'cart-contact-notification-js',
      plugin_dir_url(__FILE__) . 'assets/js/notification.js',
      array('jquery', 'cart-contact-utils-js', 'toastify-js'),
      '1.0.0',
      true
    );

    $data = array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'cartUrl' => wc_get_cart_url(),
      'nonce' => wp_create_nonce('cart_contact_notification_send'),
      'enabled' => get_option($this->settings['enabled_option'], $this->settings['enabled_default']),
      'intervalHours' => $this->settings['interval_hours'],
      'texts' => array(
        'notification' => $this->settings['notification_text'],
        'notificationAction' => $this->settings['notification_action'],
        'modalTitle' => $this->settings['modal_title'],
        'phoneLabel' => $this->settings['modal_phone_label'],
        'contactLabel' => $this->settings['modal_contact_label'],
        'submit' => $this->settings['modal_submit_text'],
        'cancel' => $this->settings['modal_cancel_text'],
        'success' => $this->settings['success_text'],
        'error' => $this->settings['error_text'],
      ),
      'contactMethods' => $this->settings['contact_methods'],
      'integrations' => array(
        'telegram' => array(
          'redirectUrl' => $this->settings['telegram_redirect_url'],
        ),
        'vk' => array(
          'redirectUrl' => $this->settings['vk_redirect_url'],
        ),
      ),
    );

    wp_localize_script('cart-contact-notification-js', 'cartContactNotificationData', $data);
  }

  public function send_notification()
  {
    check_ajax_referer('cart_contact_notification_send', 'nonce');

    if (!class_exists('WooCommerce') || !function_exists('WC')) {
      wp_send_json_error(array('message' => 'WooCommerce is unavailable.'), 400);
    }

    $this->ensure_cart_loaded();

    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $contact_method = isset($_POST['contact_method']) ? sanitize_key(wp_unslash($_POST['contact_method'])) : '';

    if ($phone === '' || !isset($this->settings['contact_methods'][$contact_method])) {
      wp_send_json_error(array('message' => 'Invalid request data.'), 400);
    }

    if (!WC()->cart || WC()->cart->is_empty()) {
      wp_send_json_error(array('message' => 'Cart is empty.'), 400);
    }

    $email_sent = $this->send_to_email($phone, $contact_method);

    if (!$email_sent) {
      wp_send_json_error(array('message' => 'Email was not sent.'), 500);
    }

    if ($contact_method === 'vk') {
      $sent = $this->send_to_vk($phone, $this->build_plain_message($phone, $contact_method));

      if (is_wp_error($sent)) {
        wp_send_json_error(array('message' => $sent->get_error_message()), 500);
      }

      wp_send_json_success(array(
        'message' => $this->settings['success_text'],
        'redirectUrl' => !empty($sent['redirect_url']) ? $sent['redirect_url'] : $this->settings['vk_redirect_url'],
      ));
    }

    wp_send_json_success(array('message' => $this->settings['success_text']));
  }

  private function ensure_cart_loaded()
  {
    if (!WC()->session && method_exists(WC(), 'initialize_session')) {
      WC()->initialize_session();
    }

    if (!WC()->cart && function_exists('wc_load_cart')) {
      wc_load_cart();
    }
  }

  private function get_email_to()
  {
    $emails = array_filter(array_map('trim', explode(',', $this->settings['email_to'])));
    $emails = array_filter(array_map('sanitize_email', $emails));

    return $emails ? array_values($emails) : get_option('admin_email');
  }

  private function send_to_email($phone, $contact_method)
  {
    return wp_mail(
      $this->get_email_to(),
      'Заявка по корзине с сайта ' . wp_parse_url(home_url(), PHP_URL_HOST),
      $this->build_email_message($phone, $contact_method),
      array('Content-Type: text/html; charset=UTF-8')
    );
  }

  private function send_to_vk($phone, $message)
  {
    $endpoint = trim($this->settings['vk_bot_lead_endpoint']);
    $secret = trim($this->settings['vk_bot_lead_secret']);

    if ($endpoint === '' || $secret === '') {
      return new WP_Error('vk_not_configured', 'VK bot lead endpoint is not configured.');
    }

    $response = wp_remote_post($endpoint, array(
      'timeout' => 12,
      'headers' => array(
        'Content-Type' => 'application/json',
        'X-VK-BOT-SECRET' => $secret,
      ),
      'body' => wp_json_encode(array(
        'phone' => $phone,
        'contact_method' => 'VK',
        'cart_url' => wc_get_cart_url(),
        'items' => $this->get_cart_rows_for_api(),
        'message' => $message,
      )),
    ));

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code < 200 || $status_code >= 300) {
      error_log('VK bot lead endpoint error: HTTP ' . $status_code . ' ' . $body);

      return new WP_Error(
        'vk_bot_error',
        'VK bot endpoint returned HTTP ' . $status_code . ': ' . $body
      );
    }

    $data = json_decode($body, true);

    return is_array($data) ? $data : array('ok' => true);
  }

  private function get_cart_rows_for_api()
  {
    return array_map(
      function ($row) {
        return array(
          'name' => $row['name'],
          'url' => $row['url'],
          'quantity' => $row['quantity'],
        );
      },
      $this->get_cart_rows()
    );
  }

  private function build_plain_message($phone, $contact_method)
  {
    $method_label = $this->settings['contact_methods'][$contact_method];
    $lines = array(
      'Новая заявка по корзине',
      'Телефон: ' . $phone,
      'Предпочитаемый способ связи: ' . $method_label,
    );

    $lines[] = 'Корзина:';

    foreach ($this->get_cart_rows() as $row) {
      $lines[] = '- ' . $row['name'] . ' x ' . $row['quantity'];

      if ($row['url']) {
        $lines[] = $row['url'];
      }
    }

    $lines[] = 'Страница корзины: ' . wc_get_cart_url();

    return implode("\n", $lines);
  }

  private function build_email_message($phone, $contact_method)
  {
    $method_label = $this->settings['contact_methods'][$contact_method];
    $cart_rows = $this->get_cart_rows();

    ob_start();
?>
<h2>Новая заявка по корзине</h2>
<p><strong>Телефон:</strong> <?php echo esc_html($phone); ?></p>
<p><strong>Предпочитаемый способ связи:</strong> <?php echo esc_html($method_label); ?></p>
<p><strong>Страница:</strong> <a href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo esc_html(wc_get_cart_url()); ?></a></p>

<h3>Корзина</h3>
<table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
  <thead>
    <tr>
      <th align="left">Товар</th>
      <th align="left">Количество</th>
      <th align="left">Цена</th>
      <th align="left">Сумма</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cart_rows as $row): ?>
    <tr>
      <td>
        <a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['name']); ?></a>
        <?php if ($row['sku']): ?>
        <br><small>Артикул: <?php echo esc_html($row['sku']); ?></small>
        <?php endif; ?>
      </td>
      <td><?php echo esc_html($row['quantity']); ?></td>
      <td><?php echo wp_kses_post($row['price']); ?></td>
      <td><?php echo wp_kses_post($row['subtotal']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p><strong>Итого:</strong> <?php echo wp_kses_post(WC()->cart->get_total()); ?></p>
<?php

    return ob_get_clean();
  }

  private function get_cart_rows()
  {
    $rows = array();

    foreach (WC()->cart->get_cart() as $cart_item) {
      if (empty($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
        continue;
      }

      $product = $cart_item['data'];
      $quantity = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 0;

      $rows[] = array(
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'url' => $product->is_visible() ? $product->get_permalink() : '',
        'quantity' => $quantity,
        'price' => wc_price((float) $product->get_price()),
        'subtotal' => WC()->cart->get_product_subtotal($product, $quantity),
      );
    }

    return $rows;
  }
}

Ismat_Cart_Contact_Notification::get_instance();
