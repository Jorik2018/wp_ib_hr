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

    function array_find($xs, $f) {
        foreach ($xs as $x) {
          if (call_user_func($f, $x) === true)
            return $x;
        }
        return null;
    }


    public function deltron_product_get(){
        //CPILI313100F
        global $wpdb;
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
            $document = new Document('https://www.deltron.com.pe/modulos/productos/items/producto.php?item_number='.strtoupper($post->post_name), true);
            $imgs = $document->find('#imageGallery img');
            $item=[];
            $src=null;
            foreach($imgs as $img) {
                $src=$img->getAttribute('src');
            }
            if(!$src)$src="https://pics.freeicons.io/uploads/icons/png/18536323181658965919-512.png";
            $attachment_file_type = wp_check_filetype($src, null);
            $attachment_args = array(
                'guid'           => $src,
                'post_title'     => '',
                'post_content'   =>'',
                'post_mime_type' => $attachment_file_type['type'],
                'post_author'    =>7777777777
            );
            $attachment_id = wp_insert_attachment($attachment_args, $image, $post_id);
            add_post_meta($post->ID, 'fifu_image_url', $src,true);
            add_post_meta($post->ID, '_thumbnail_id',$attachment_id,true);
            // get_post_meta(10, 'age', true);
        }
        //https://stackoverflow.com/questions/70405727/insert-wordpress-post-using-wp-insert-post-and-attach-the-featured-image
        return $posts;        
    }

    public function deltron_import_get(){
        $products = [];
        $categories=[];
        $brands=[];
        $row=[];
       // return json_encode(wp_upload_dir());
        if (($handle = fopen(wp_upload_dir()['basedir']."\DELTRON.csv", "r")) !== FALSE) {
            return 11;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);

               // echo "<p> $num fields in line $row: <br /></p>\n";
                $row++;
                if($num==9){
                    
                    if($data[1]=='CODIGO'){
                        //$categories[]=$data[2];
                        $row=[];
                       // $products[]=$row;
                    }else{
                        //$row[]=$data;
                        //,"CODIGO","Y PATCH CORD - COBRE","STOCK","PREC DISTRIB US $","PREC S/.","FLETE ","GARAN","MARCA"
                        $products[]=[
                            "category"=>sanitize_title($data[0]),
                            "description"=>strtoupper($data[0])." ".strtoupper((explode("[@@@]",$data[2]))[0]),
                            "code"=>$data[1],
                            "name"=>strtoupper((explode("[@@@]",$data[2]))[0]),
                            "stock"=>$data[3],
                            "price_dist"=>$data[4],
                            "price"=>$data[5],
                            "flete"=>$data[6],
                            "garan"=>$data[7],
                            "brand"=>sanitize_title($data[8]),
                        ];

                        //$categories[]=$data[8];
                        $slug=sanitize_title($data[8]);
                        if (!$this->array_find($brands,function($e) use ($slug){return $e[0]==$slug;})) {
                            $brands[]=[$slug,strtoupper($data[8])];
                        }
                        $slug=sanitize_title($data[0]);
                        if (!$this->array_find($categories,function($e) use ($slug){return $e[0]==$slug;})) {
                            $categories[]=[$slug,$data[0]];
                        }
                        // ,"CODIGO","Y PATCH CORD - COBRE","STOCK","PREC DISTRIB US $","PREC S/.","FLETE ","GARAN","MARCA"
                    }
                }
                //for ($c=0; $c < $num; $c++) {
                  //  echo $data[$c] . "<br />\n";
                //}
            }
            fclose($handle);
        }

        $categories_terms=array_map(function($e){return $e->slug;},get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        )));
        $categories=array_values(array_filter($categories,function($e) use ($categories_terms){return !in_array($e[0],$categories_terms);}));
        //$categories=array_map(function($e){return /*strtoupper*/ $e[0];}, $categories);
        $count=0;
        foreach($categories as $item){
            $count++;
            if($count>20)break;
            wp_insert_term(strtoupper($item[1]), 'product_cat', array(
                'description' => '', // optional
                'parent' => 0, // optional
                'slug' => $item[1] // optional
            ));
        }
        
        $brands_terms=array_map(function($e){return $e->slug;},get_terms(array(
            'taxonomy'   => 'pwb-brand',
            'hide_empty' => false,
        )));
        
        
        $brands=array_values(array_filter($brands,function($e) use ($brands_terms){return !in_array($e[0],$brands_terms);}));
        $count=0;
        foreach($brands as $item){
            $count++;
            if($count>20)break;
            wp_insert_term(strtoupper($item[1]), 'pwb-brand', array(
                'description' => '', // optional
                'parent' => 0, // optional
                'slug' => $item[1] // optional
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
            $querystr = "
            SELECT $wpdb->posts.ID, $wpdb->posts.post_name
            FROM $wpdb->posts
            WHERE $wpdb->posts.post_status = 'publish' 
            AND $wpdb->posts.post_type = 'product'
            AND $wpdb->posts.post_date < NOW()
            ORDER BY $wpdb->posts.post_date DESC
            ";
            
            $posts =[];
            foreach($wpdb->get_results($querystr, OBJECT) as $post){
                $posts[(explode("_",$post->post_name))[0]]=$post->ID;
            }
            foreach($products as $item){
                $product = $posts[$item['code']];
                //$product = get_page_by_path($item['code'], OBJECT, 'product' );
                if (empty( $product ) ) {
            
                    $count++;if($count>30)break;
                    $product = new \WC_Product_Simple();
                    $product->set_name($item['name']); // product title
                    $product->set_slug($item['code']);
                    $product->set_regular_price(round(((float)$item['price_dist'])*1.25,2)); // in current shop currency
                    $product->set_short_description($item['description']);
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
                    //$_pf = new \WC_Product_Factory();  
                    //$tag = array( 5 ); // Correct. This will add the tag with the id 5.
                    //wp_set_post_terms( $post_id, $tag, $taxonomy );
                    //return [get_the_terms( $product ->ID, 'product_cat' ),get_the_terms( $product ->ID, 'pwb-brand' )];
                    //$product = $_pf->get_product($product ->ID);

                    //return get_post_field('pwb-brand',$product ->ID);
                    //return $product->get_slug();
                    //$product_slug = get_post_field('post_name', $product_id);
                    //$product = wc_get_product( $product ->ID);

                    // Example of how to return the sale price if on sale.
                    //$price = $product->get_price();

                    //if ( $product->is_on_sale() ) {
                    // $price = $product->get_sale_price();
                    //}
                }

            }
        }
        return $products;
    }
    /**
     * @since 1.0.0
     *
     * @hook init
     *
     * @return
     */
    public function init()
    {
        register_rest_route( 'api/deltron','/import', array(
            'methods' => 'GET',
            'callback' =>//  __CLASS__ . '::get_deltron_import',
            array($this,'deltron_import_get')
        ));
        register_rest_route( 'api/deltron','/product', array(
            'methods' => 'GET',
            'callback' =>//  __CLASS__ . '::get_deltron_import',
            array($this,'deltron_product_get')
        ));
    }

}