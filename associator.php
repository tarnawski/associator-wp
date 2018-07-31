<?php
/*
Plugin Name: Associator
Plugin URI: http://associator.eu
Description: Allow to display related products.
Author: Tomasz Tarnawski
Version: 1.0
Author URI: http://ttarnawski.usermd.net
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

$associator = new Associator('436c2444-9a0e-45cc-8808-1f6660345287');
$associator->initialize();

class Associator
{
    const BASE_URL = 'api.associator.eu';
    const VERSION = 'v1';
    const DEFAULT_SUPPORT = 5;
    const DEFAULT_CONFIDENCE = 5;

    /** @var string */
    private $apiKey;

    /** @var int */
    private $support;

    /** @var int */
    private $confidence;

    /**
     * Associator constructor.
     * @param $apiKey
     * @param $support
     * @param $confidence
     */
    public function __construct($apiKey, $support = self::DEFAULT_SUPPORT, $confidence = self::DEFAULT_CONFIDENCE)
    {
        $this->apiKey = $apiKey;
        $this->support = $support;
        $this->confidence = $confidence;
    }

    public function initialize()
    {
        add_action('woocommerce_after_single_product_summary', [$this, 'showRelatedProducts'], 9);
    }

    public function showRelatedProducts()
    {
        $samples = $this->getProductsFromCard();
        $products = $this->getAssociations($samples);

        if (empty($products)) {
            return true;
        }

        $args =array(
            'post_type' => 'product',
            'ignore_sticky_posts' => 1,
            'no_found_rows' => 1,
            'posts_per_page' => 12,
            'post__in' => $products
        );

        $products_list = new WP_Query($args);
        $title = __( 'Customers Who Bought This Item Also Bought', 'aheadzen' );
        if ($products_list->have_posts()) : ?>
            <div class="related products">
                <h2><?php echo $title; ?></h2>
                <?php woocommerce_product_loop_start(); ?>
                <?php while ( $products_list->have_posts() ) : $products_list->the_post(); ?>
                    <?php wc_get_template_part( 'content', 'product' ); ?>
                <?php endwhile; // end of the loop. ?>
                <?php woocommerce_product_loop_end(); ?>
            </div>
        <?php endif;

        wp_reset_postdata();
    }

    public function getProductsFromCard()
    {
        $products = [];
        $cart = WC()->cart;

        foreach ($cart->get_cart() as $cartItem => $values) {
            if ($values['quantity'] > 0) {
                $products[] = $values['product_id'];
            }
        }

        return $products;
    }

    /**
     * Fetch associations from AssociatorAPI
     * @param array $samples
     * @return array|mixed
     */
    function getAssociations(array $samples)
    {
        $parameters['api_key'] = $this->apiKey;
        $parameters['samples'] = json_encode($samples);
        $parameters['support'] = $this->support;
        $parameters['confidence'] = $this->confidence;
        $query = http_build_query($parameters);

        $url = sprintf('http://%s/%s/associations?%s', self::BASE_URL, self::VERSION, $query);
        $response = wp_remote_get($url);

        if ( !is_array( $response ) ) {
            return [];
        }

        $body = json_decode($response['body'], true);

        if ($body['status'] !== "Success") {
            return [];
        }

        $associations = $body['associations'];
        $associations = array_reduce($associations, 'array_merge', array());

        if (empty($associations)) {
            return [];
        }

        return $associations;
    }
}


