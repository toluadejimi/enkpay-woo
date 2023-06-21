<?php

class WC_GSama extends WC_Payment_Gateway
{
    protected string $api_key;
    public string $email_address;
     public string $ref_with_prefix;

    public string $before_payment_description;

    protected string $success_message;

    protected string $failed_message;

    public function __construct()
    {
        // Gateway Info
        $this->id = "WC_GSama";
       $this->method_title = "ENKPAY Webpay";
        $this->method_description = "Fast and Secured Web payment";
        $this->has_fields = false;
        $this->icon = apply_filters(
            "WC_GSama_logo",
            plugins_url("/assets/images/logo.png", __FILE__)
        );

        // Get setting values.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        $this->api_key = $this->get_option("api_key");
        $this->email_address = $this->get_option("email_address");

        $this->before_payment_description = $this->get_option(
            "before_payment_description"
        );
        $this->success_message = $this->get_option("success_message");
        $this->failed_message = $this->get_option("failed_message");

        if (version_compare(WOOCOMMERCE_VERSION, "2.0.0", ">=")) {
            add_action(
                "woocommerce_update_options_payment_gateways_" . $this->id,
                [$this, "process_admin_options"]
            );
        } else {
            add_action("woocommerce_update_options_payment_gateways", [
                $this,
                "process_admin_options",
            ]);
        }

        add_action("woocommerce_receipt_" . $this->id, [
            $this,
            "checkout_receipt_page",
        ]);
        add_action("woocommerce_api_" . strtolower(get_class($this)), [
            $this,
            "sama_checkout_return_handler",
        ]);


    }






    public function init_form_fields()
    {
        $this->form_fields = apply_filters("WC_GSama_Config", [
           "enabled" => [
            "title" => "Enabled / Disabled",
            "type" => "checkbox",
            "label" => "Enable or disable the payment gateway.",
            "default" => "yes",
           ],
           
            "title" => [
            "title" => "Gateway Title",
            "type" => "text",
            "description" => "Enter the title of the gateway.",
            "default" => "Enkpay",
            ],
           "description" => [
            "title" => "Gateway Description",
            "type" => "textarea",
            "description" => "Enter the description of the gateway.",
            "default" => "Fast and Secure web payment",
            ],
           "api_key" => [
            "title" => "Web Service Key",
            "type" => "text",
            "description" => "Enter the web service key.",
            ],
             "email_address" => [
            "title" => "Vendor Enkpay email address",
            "type" => "text",
            "description" => "Enter Enkpay email address.",
            ],
           "before_payment_description" => [
                "title" => "Description Before Payment",
                "type" => "textarea",
                "description" => "Enter the description before payment.",
                "default" => " Secure and Fast web payment  ",
                ],

           
            "success_message" => [
            "title" => "Success Message",
            "type" => "textarea",
            "description" => "Enter the success message. Order number: {order_id} Tracking code: {track_id}",
            "default" => "Payment was successful. Tracking code: {track_id}",
            ],
           "failed_message" => [
            "title" => "Failed Message",
            "type" => "textarea",
            "description" => "Enter the failed payment message. Order number: {order_id} Error: {error}",
            "default" => "Payment was unsuccessful. {error}",
            ],
        ]);
    }

    public function process_payment($order_id): array
    {
        $order = new WC_Order($order_id);

        return [
            "result" => "success",
            "redirect" => $order->get_checkout_payment_url(true),
        ];
    }

    public function checkout_receipt_page($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

       
// Generate a random number with 5 digits
$random_Ref = '';
while (strlen($random_Ref) < 5) {
    $random_Ref .= wp_generate_password(1, false, false);
}
$this->$ref_with_prefix = 'WB' . $random_Ref;


$client_id = sha1(
            $order->get_customer_id() .
                "_" .
                $order_id .
                "_" .
                $amount .
                "_" .
                time()
        );


        $args = array(
            'wc_order' => $order_id,
            'ref' => $this->$ref_with_prefix,
            'amount' => $order->get_total(),
          'client_id' => $client_id
        );
        
        // Add query parameters to the URL
        $callback_url  = add_query_arg($args,  WC()->api_request_url("wc_gsama"));

   
        $url =  "https://web.enkpay.com/pay?"."amount=" . $order->get_total()   ."&key=" . $this->api_key . "&ref=" . $this->$ref_with_prefix. "&wc_order=" .  $order_id . "&redirectURL=" . $callback_url  ;


        wp_redirect($url);

        $amount = isset( $_GET['amount'] ) ? $_GET['amount'] : '';
         $trans_id = isset( $_GET['trans_id'] ) ? $_GET['trans_id'] : '';
        $status = isset( $_GET['status'] ) ? $_GET['status'] : '';

        if ($status == "failed") {
            $error = "The transaction failed";
            wc_add_notice($error, "error");
            $order->add_order_note($error);
            wp_redirect(wc_get_checkout_url());
            exit();
        }

        update_post_meta($order_id, "gsama_transaction_price", $order->get_total());
        update_post_meta($order_id, "gsama_transaction_fee", "0");
        update_post_meta(
            $this->$ref_with_prefix,
            "gsama_transaction_total_price",
            $order->get_total()
        );

        $note = "The user has been redirected to the payment gateway. Transaction ID: " . $this->$ref_with_prefix;
        $order->add_order_note($note);
        exit();
    }

    public function sama_checkout_return_handler()
    {
        global $woocommerce;
        // https://enkwave.com/wc-api/WC_GSama

        $order_id = intval($_GET["wc_order"]);
        $order = wc_get_order($order_id);

            if (empty($order)) {
            $notice = "No order exists.";
            wc_add_notice($notice, "error");
            wp_redirect(wc_get_checkout_url());
            exit();
        }

        if (
            $order->get_status() == "completed" ||
            $order->get_status() == "processing"
        ) {
            $this->display_success_message($order_id);
            wp_redirect(
                add_query_arg(
                    "wc_status",
                    "success",
                    $this->get_return_url($order)
                )
            );
            exit();
        }
        
        
        
        $ref = isset( $_GET['trans_id'] ) ? $_GET['trans_id'] : '';
        $saved_payment_price = isset( $_GET['amount'] ) ? $_GET['amount'] : '';
        $saved_client_id= isset( $_GET['client_id'] ) ? $_GET['client_id'] : '';


        $response = wp_remote_post(
                "https://web.enkpay.com/api/verify",
            [
                'method' => 'POST',
                "headers" => [
                    "Content-Type" => "application/json",
                ],
                "body" => json_encode(["trans_id" =>  $ref])
            ]
        );




        if (is_wp_error($response)) {
            for ($i = 0; $i <= 5; $i++) {
                $response = wp_remote_post(
                    "https://web.enkpay.com/api/verify",
                    [
                        "headers" => [
                            "Content-Type" => "application/json",
                        ],
                        "body" => json_encode([
                            "trans_id" =>  $ref,
                        ]),
                        "data_format" => "body",
                    ]
                );
                if (!is_wp_error($response)) {
                    break;
                }
            };
        }
        
        
        $data = json_decode(wp_remote_retrieve_body($response));

      

        if (is_wp_error($response)) {
            $notice = "An error occurred while connecting to the payment gateway.";
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, "", $notice);
            $order->update_status("failed");
            wp_redirect(wc_get_checkout_url());
            exit();
        }


        if (wp_remote_retrieve_response_code($response) != 200) {
            $error = $data->detail;
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace("{error}", $error, $notice);
            $notice = str_replace("{order_id}", $order_id, $notice);

            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, $error, "");
            $order->update_status("failed");
            wp_redirect(wc_get_checkout_url());
            exit();
        }

        if ($saved_payment_price != $data->price) {
            $notice = "Transaction amount does not match the order amount.";
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, "", $notice);
            $order->update_status("failed");
            wp_redirect(wc_get_checkout_url());
            exit();
        }
        

        
        if ($data->status != true ) {
            $notice = "Transaction not confirmed.";
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, "", $notice);
            $order->update_status("failed");
            wp_redirect(wc_get_checkout_url());
            exit();
        }
        

      
        update_post_meta($order_id, "gsama_payment_id", $data->payment->id);

        $new_status = $this->checkDownloadableItem($order)
            ? "completed"
            : "processing";

        $notice = wpautop(wptexturize($this->success_message));
        $notice = str_replace("{track_id}", $this->$ref_with_prefix, $notice);
        $notice = str_replace("{order_id}", $order_id, $notice);

        $this->display_success_message($order_id);
        $order->add_order_note($notice, 1);
        $order->payment_complete($this->$ref_with_prefix);
        $order->update_status($new_status);
        $woocommerce->cart->empty_cart();
        wp_redirect(
            add_query_arg("wc_status", "success", $this->get_return_url($order))
        );
        exit();
    }

    private function display_success_message($order_id, $default_notice = "")
    {
        $track_id = get_post_meta($order_id, "gsama_reference_number", true);
        $notice = wpautop(wptexturize($this->success_message));
        if (empty($notice)) {
            $notice = $default_notice;
        }
        $notice = str_replace("{track_id}", $track_id, $notice);
        $notice = str_replace("{order_id}", $order_id, $notice);
        wc_add_notice($notice, "success");
    }

    private function display_failed_message(
        $order_id,
        $error = "",
        $default_notice = ""
    ) {
        $notice = wpautop(wptexturize($this->failed_message));
        if (empty($notice)) {
            $notice = $default_notice;
        }
        $notice = str_replace("{error}", $error, $notice);
        $notice = str_replace("{order_id}", $order_id, $notice);
        wc_add_notice($notice, "error");
    }

    public function checkDownloadableItem($order): bool
    {
        foreach ($order->get_items() as $item) {
            if ($item->is_type("line_item")) {
                $product = $item->get_product();
                if (
                    $product &&
                    ($product->is_downloadable() || $product->has_file())
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getAmountOrder($amount, $currency)
    {
        switch ($currency) {
            case "IRR":
                return $amount;
            case "IRT":
                return $amount * 10;
            case "IRHR":
                return $amount * 1000;
            case "IRHT":
                return $amount * 10000;
            default:
                return 0;
        }
    }
}
