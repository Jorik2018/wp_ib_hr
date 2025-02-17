<?php
declare(strict_types=1);

namespace IB\cv;

use WPMVC\Bridge;

class My_Background_Process extends \WP_Background_Process {
    protected $action = 'my_background_process';

    protected function task( $item ) {
        $args = array(
            'name'        => $item['code'],
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => 1
        );
        $product = get_posts($args);
        if (empty( $product ) ) {
            $out[]=$item;
            $product = new \WC_Product_Simple();
            $product->set_name($item['name']); // product title
            $product->set_slug($item['code']);
            $product->set_regular_price(round(((float)$item['price_dist'])*1.25,2));
            if(isset($item['description'])){
                $product->set_short_description($item['description']);
            }
            // you can also add a full product description
            // $product->set_description( 'long description here...' );
            //$product->set_image_id( 90 );
            // let's suppose that our 'Accessories' categories has ID = 19 
            $categories=array_map(function($e){return $e->term_id;},array_values(array_filter($categories_terms,function($e) use ($item){return $e->slug==$item['category'];})));
            $product->set_category_ids($categories);
            $brands=array_map(function($e){return $e->term_id;},array_values(array_filter($brands_terms,function($e) use ($item){return $e->slug==$item['brand'];})));
            
            //return [$brands,$item];
            // you can also use $product->set_tag_ids() for tags, brands etc
            $product->save();
            
            wp_set_object_terms($product->get_id(),$brands, 'pwb-brand' , false);
        }else{
            //$_product_factory = new \WC_Product_Factory();
            //$product = $_product_factory ->get_product($product[0] ->ID);
            $product = wc_get_product($product[0] ->ID);
            //$product -> set_name($product->get_name());
            $product -> set_name($item['name']);
            //$tag = array( 5 ); // Correct. This will add the tag with the id 5.
            //wp_set_post_terms( $post_id, $tag, $taxonomy );
            //return [get_the_terms( $product ->ID, 'product_cat' ),get_the_terms( $product ->ID, 'pwb-brand' )];
            //

            //return get_post_field('pwb-brand',$product ->ID);
            //return $product->get_slug();
            //$product_slug = get_post_field('post_name', $product_id);
            // Example of how to return the sale price if on sale.
            //$price = $product->get_price();

            //if ( $product->is_on_sale() ) {
            // $price = $product->get_sale_price();
            //}
            $product->save();
        }
        $progress = $item;
        $this->send_websocket_message($progress);
        return false; // Return false to indicate the task is complete
    }

    protected function complete() {
        parent::complete();
        // Actions to perform when all tasks are complete
    }

    protected function send_websocket_message($progress) {
        $options = array(
            'cluster' => 'us3',
            'useTLS' => true
          );
          /*$pusher = new \Pusher\Pusher(
            '640591c22ec9ff892849',
            '915183a78bbae04916ab',
            '1821284',
            $options
          );
        
          $data = array('progress' => $progress);
          $pusher->trigger('my-channel', 'my-event', $data);*/
    }
}

$my_background_process = new My_Background_Process();
class Main extends Bridge {

    public function api_covid() {
        return 2;
    }

    public function return_view() {
        return $this->mvc->view->get( 'view.key' );
    }

    public function init() {
        $this->add_action( 'rest_api_init','RestController@init' );
        $this->add_action( 'rest_api_init','EmployeeRestController@init');
        $this->add_action( 'rest_api_init','StudyRestController@init');
        $this->add_action( 'rest_api_init','RiskTypeRestController@init');
        $this->add_action( 'rest_api_init','TrainingRestController@init');
        $this->add_action( 'rest_api_init','ExperienceRestController@init');
        $this->add_action( 'rest_api_init','DocumentRestController@init');
        $this->add_action( 'plugins_loaded', 'AdminController@activate' );
    }

    public function on_admin() {
        $this->add_action('admin_menu', 'AdminController@init');
    }

}