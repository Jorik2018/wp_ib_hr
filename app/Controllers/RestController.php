<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use DiDom\Document;
/**
 * AppController
 * WordPress MVC controller.
 *
 * @author 
 * @package ib-cv
 * @version 1.0.0
 */

class RestController extends Controller
{

    private $background_process;

    public function __construct($app) {
        parent::__construct($app);
        $this->background_process = new \IB\cv\My_Background_Process();
    }

    function array_find($xs, $f) {
        foreach ($xs as $x) {
          if (call_user_func($f, $x) === true)
            return $x;
        }
        return null;
    }


    public function deltron_product_get($request){
        global $wpdb;
        $post_name = method_exists($request, 'get_params') ? $request->get_params()['id'] : $request;
        if(isset($post_name)){
            $args = array(
                'name'        => $post_name,
                'post_type'   => 'product',
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $post = get_posts($args);
            $post = $post[0];
            $document = new Document('https://www.deltron.com.pe/modulos/productos/items/producto.php?item_number='.strtoupper($post_name), true);
            $e=$document->first('#contentProductItem');
            if (!empty($e)) {
                // Get the first element from the array
                return die(111);
                return die($e->html());
            }
            $imgs = $document->find('#imageGallery img');
            $src=null;
            foreach($imgs as $img) {
                $src=$img->getAttribute('src');
            }
            
            if(!$src)$src="https://pics.freeicons.io/uploads/icons/png/18536323181658965919-512.png";
            $attachment_file_type = wp_check_filetype($src, null);
            $attachment_args = array(
                'guid'           => $src,
                'post_title'     => '',
                'post_content'   => '',
                'post_mime_type' => $attachment_file_type['type'],
                'post_author'    => 7777777777
            );
            $attachment_id = wp_insert_attachment($attachment_args, $image, $post_id);
            add_post_meta($post->ID, 'fifu_image_url', $src,true);
            add_post_meta($post->ID, '_thumbnail_id',$attachment_id,true);
            return $document->find('#contentProductItem');
        }

        $querystr = "
        SELECT $wpdb->posts.ID, $wpdb->posts.post_name
        FROM $wpdb->posts LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key = 'fifu_image_url' 
        WHERE $wpdb->posts.post_status = 'publish' 
        AND $wpdb->postmeta.meta_value IS NULL
        AND $wpdb->posts.post_type = 'product'
        AND $wpdb->posts.post_date < NOW()
        ORDER BY $wpdb->posts.post_date DESC
        ";
        $posts =[];
        $i=0;
        
        foreach($wpdb->get_results($querystr, OBJECT) as $post){
            if(($i++)>10)break;
            $posts[]=$post;
            //$this->deltron_product_get();
        }
        //https://stackoverflow.com/questions/70405727/insert-wordpress-post-using-wp-insert-post-and-attach-the-featured-image
        return $posts;
    }

    public function deltron_import_get(){
        $products = [];
        $categories=[];
        $current_category;
        $current_product;
        $brands=[];
        $row=[];
        if (!file_exists($filename=wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR ."DELTRON.csv")) {
            return"The file $filename not exists";
        }
        if (($handle = fopen(wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR ."DELTRON.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
                if($num>10&&$data[0]&&$data[1]){
                    if($data[0]=='CODIGO'){
                        $slug=sanitize_title($data[1]);
                        if (!$this->array_find($categories,function($e) use ($slug){return $e[0]==$slug;})) {
                            $categories[]=[$slug, preg_replace('/[^[:print:]]/', '',$data[1])];
                            $current_category=$slug;
                        }
                    } elseif ($data[10]){
                        $product_name = strtoupper(preg_replace('/[^[:print:]]/', '',$data[1]));
                        $brand = strtoupper(preg_replace('/[^[:print:]]/', '',$data[10]));
                        $slug = sanitize_title($data[10]);
                        $products[] = $current_product = [
                            "category" => $current_category,
                            "code" => $data[0],
                            "name" => $product_name,
                            "stock" => preg_replace('/[^[:print:]]/', '',$data[2]),
                            "price_dist" => preg_replace('/[^[:print:]]/', '',$data[3]),
                            "price" =>$data[4],
                            "garan" => preg_replace('/[^[:print:]]/', '',$data[9]),
                            "brand" => sanitize_title($slug),
                        ];
                        if (!$this -> array_find($brands,function($e) use ($slug){return $e[0]==$slug;})) {
                            $brands[] = [$slug,$brand];
                        }
                    } else{
                        $products[count($products)-1]["description"] = strtoupper((explode("[@@@]",$data[1]))[0]);
                    }
                }
            }
            fclose($handle);
        }
        //return array('brands'=>$brands, 'categories'=>$categories,'products'=>$products );
        $categories_terms=array_map(function($e){return $e->slug;},get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        )));
        //return $categories_terms;
        $categories = array_values(array_filter($categories,function($e) use ($categories_terms){return !in_array($e[0],$categories_terms);}));
        //return $categories;
        //$categories=array_map(function($e){return /*strtoupper*/ $e[0];}, $categories);
        $count=0;
        $inserted=[];
        foreach($categories as $item){
            $inserted[] = $item;
            $count++;
            if($count > 50)break;
            wp_insert_term(strtoupper($item[1]), 'product_cat', array(
                'description' => '',
                'parent' => 0,
                'slug' => $item[1]
            ));
        }
        $brands_terms = array_map(function($e){return $e->slug;}, get_terms(array(
            'taxonomy'   => 'pwb-brand',
            'hide_empty' => false,
        )));
        $brands=array_values(array_filter($brands,function($e) use ($brands_terms){return !in_array($e[0],$brands_terms);}));
        $count=0;
        $inserted=[];
        foreach($brands as $item){
            $count++;
            if($count>30)break;
            $inserted[] = $item;
            wp_insert_term(strtoupper($item[1]), 'pwb-brand', array(
                'description' => '',
                'parent' => 0,
                'slug' => $item[1]
            ));
        }
        if(empty($categories)&&empty($brands)){
            global $wpdb;
            $count=0;
            $categories_terms=get_terms(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ));
         
            $brands_terms=get_terms(array(
                'taxonomy'   => 'pwb-brand',
                'hide_empty' => false,
            ));
            $out=[];
            //return $products;
            foreach($products as $item){
                $count++;
                if($count>10)break;
                $this->background_process->push_to_queue($item);
            }
            $this->background_process->save()->dispatch();
            return $out;
        }
        return ['categories'=>$categories,'brands'=>$brands];
    }
    /**
     * @since 1.0.0
     *
     * @hook init
     *
     * @return
     */
    public function push_post($request)
    {
        $options = array(
            'cluster' => 'us3',
            'useTLS' => true
          );
        $o = $request->get_params();
        $pusher = new \Pusher\Pusher(
            '640591c22ec9ff892849',
            '915183a78bbae04916ab',
            '1821284',
            $options
          );
        $pusher->trigger('my-channel', 'my-event', $o);
    }

    public function init()
    {
        register_rest_route( 'api/deltron','/push', array(
            'methods' => 'POST',
            'callback' => array($this,'push_post')
        ));
        register_rest_route( 'api/deltron','/import', array(
            'methods' => 'GET',
            'callback' => array($this,'deltron_import_get')
        ));
        register_rest_route( 'api/deltron','/product/(?P<id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this,'deltron_product_get')
        ));
        register_rest_route( 'api/deltron','/product', array(
            'methods' => 'GET',
            'callback' => array($this,'deltron_product_get')
        ));
    }

}