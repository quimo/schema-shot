<?php
/**
 * Plugin Name: Schema Shot
 * Description: WordPress plugin che inserisce markup JSON-LD (disabilita i JSON-LD di Yoast). Mostra i breadcrumb tramite shortcode [ss_breadcrumbs].
 * Version: 1.5.2
 * Author: Simone Alati
 * Author URI: https://www.simonealati.it
 * Text Domain:  schemashot
 */

class SchemaShot {

    private $menu_level = 1;
    private $yoast = false;

    private $local_business_list = array(
        'Seleziona...',
        'AnimalShelter',
        'ArchiveOrganization',
        'AutomotiveBusiness',
        'ChildCare',
        'Dentist',
        'DryCleaningOrLaundry',
        'EmergencyService',
        'EmploymentAgency',
        'EntertainmentBusiness',
        'FinancialService',
        'FoodEstablishment',
        'GovernmentOffice',
        'HealthAndBeautyBusiness',
        'HomeAndConstructionBusiness',
        'InternetCafe',
        'LegalService',
        'Library',
        'LodgingBusiness',
        'MedicalBusiness',
        'ProfessionalService',
        'RadioStation',
        'RealEstateAgent',
        'RecyclingCenter',
        'Restaurant',
        'SelfStorage',
        'ShoppingCenter',
        'SportsActivityLocation',
        'Store',
        'TelevisionStation',
        'TouristInformationCenter',
        'TravelAgency'
    );

    function __construct() {
        register_activation_hook(__FILE__, array($this, 'activation'));
        register_deactivation_hook( __FILE__, array($this, 'deactivation'));
        add_action('wp_enqueue_scripts', array($this, 'init'));
        add_shortcode('ss_schema', array($this, 'render_shortcode'));
        add_shortcode('ss_breadcrumbs', array($this, 'render_breadcrumbs'));
        add_action('wp_footer', array($this, 'render_footer'));
        add_filter('nav_menu_link_attributes', array($this, 'render_menu_link'), 10, 3);
        add_filter('wp_nav_menu', array($this, 'render_menu'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('wp', array($this, 'check_yoast_plugin'));
        /* rimuovo i JSON-LD di Yoast */
        add_filter('wpseo_json_ld_output', '__return_false');
    }

    function activation(){
        $this->add_settings();
    }

    public function check_yoast_plugin() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $this->yoast = is_plugin_active('wordpress-seo/wp-seo.php');
    }

    private function is_active_yoast_plugin() {
        return $this->yoast;
    }

    public function add_settings() {
        add_option('schema-shot-organization', 0);
        add_option('schema-shot-organization-page', 0);
        add_option('schema-shot-localbusiness', 0);
        add_option('schema-shot-localbusiness-page', 0);
        add_option('schema-shot-localbusiness-type', 'Seleziona...');
        add_option('schema-shot-restaurant', 0);
        add_option('schema-shot-website', 0);
        add_option('schema-shot-website-parameters', array(
            'name' => '',
            'alternateName' => ''
        ));
    }

    function deactivation(){
        $this->remove_settings();
    }

    public function remove_settings() {
        delete_option('schema-shot-organization');
        delete_option('schema-shot-organization-page');
        delete_option('schema-shot-localbusiness');
        delete_option('schema-shot-localbusiness-page');
        delete_option('schema-shot-localbusiness-type');
        delete_option('schema-shot-restaurant');
        delete_option('schema-shot-website');
        delete_option('schema-shot-website-parameters');
    }

    function init() {
        wp_enqueue_style('schemashot', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' , array(), mt_rand());
        wp_enqueue_script('schemashot', plugin_dir_url( __FILE__ ) . 'assets/js/wordpress-schema-shot.js',array('jquery'),mt_rand() ,true);
    }


/**
 *
 * render: renderizza uno schema nel content
 *
 */

    function render_shortcode($atts, $content = null) {
        extract(shortcode_atts(array(
            'template' => 'default',
            ), $atts,  'render'));

        $html = $this->get_schema($template);
        ob_start();
		echo '<script type="application/ld+json">' . $html . '</script>';
        return ob_get_clean();
    }

/**
 *
 * get_schema: recupera il markup da un file json
 * https://technicalseo.com/seo-tools/schema-markup-generator/
 *
 */

    function get_schema($template) {

        global $post;

        $tmpl_url = plugin_dir_url( __FILE__ ) . 'assets/templates/' . $template.'.json';
        $html = @file_get_contents($tmpl_url);

        /* site url */
        $html = str_replace("[+site_url+]", get_site_url(), $html);

        if (!empty($post)) {
            if ($this->is_active_yoast_plugin() && get_post_meta(get_the_ID(), '_yoast_wpseo_title', true)) {
                $html = str_replace("[+post_title+]", get_post_meta(get_the_ID(), '_yoast_wpseo_title', true), $html);
            } else {
                $html = str_replace("[+post_title+]", $post->post_title, $html);
            }
            /* featured image */
            if (function_exists('has_post_thumbnail') && has_post_thumbnail($post->ID)) {
                $thumb_id = get_post_thumbnail_id($post->ID);
                $image = wp_get_attachment_image_src($thumb_id,'full');
                $html = str_replace("[+post_image+]", $image[0], $html);
            } else {
                if (function_exists('the_custom_logo') && get_theme_mod('custom_logo')) {
                    $custom_logo_id = get_theme_mod('custom_logo');
                    $image = wp_get_attachment_image_src($custom_logo_id ,'full');
                    $html = str_replace("[+post_image+]", $image[0], $html);
                } else {
                    $html = str_replace("[+post_image+]", get_site_url(), $html);
                }
            }
            /* post author */
            $author_id = $post->post_author;
            $author_nicename = get_the_author_meta('user_nicename', $author_id);
            $author_firstname  = get_the_author_meta('first_name', $author_id);
            $author_lastname = get_the_author_meta('last_name', $author_id);
            $author_data = '';
            if (!$author_firstname && !$author_lastname) {
                $author_data .= $author_nicename;
            } else {
                $author_data .= ($author_firstname) ? $author_firstname . ' ' : '';
                $author_data .= ($author_lastname) ? $author_lastname . ' ' : '';
                $author_data = substr($author_data, 0, -1);
            }
            $html = str_replace("[+post_author+]", $author_data, $html);

            /* post date */
            $html = str_replace("[+post_date+]", date('Y-m-d', strtotime($post->post_date)), $html);
            $html = str_replace("[+post_modified+]",  date('Y-m-d', strtotime($post->post_modified)), $html);

            /* post publisher */
            $html = str_replace("[+post_publisher+]", get_bloginfo('name'), $html);

            /* permalink */
            $html = str_replace("[+post_permalink+]", get_permalink($post->ID), $html);

            /* excerpt */
            if ($this->is_active_yoast_plugin() && get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true)) {
                $html = str_replace("[+post_excerpt+]", get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true), $html);
            } else {
                $html = str_replace("[+post_excerpt+]", get_the_excerpt($post->ID), $html);
            }

            /* content */
            /* ??? */

            /* custom logo */
            $custom_logo_id = get_theme_mod('custom_logo');
            $image = wp_get_attachment_image_src($custom_logo_id, 'full');
            $html = str_replace("[+post_logo+]", $image[0], $html);

            /* options */
            $website = get_option('schema-shot-website-parameters');
            $html = str_replace("[?name?]", $website['name'], $html);
            $html = str_replace("[?alternateName?]", $website['alternateName'], $html);
            $html = str_replace("[?localBusinessType?]", get_option('schema-shot-localbusiness-type'), $html);

        }

        return $html;

    }

/**
 *
 * render_footer: recupera i markup da inserire nel footer
 *
 */

    function render_footer() {
        /* organization */
        if (get_option('schema-shot-organization')) {
            /* pagina in cui inserire l'organization */
            if (is_page(get_option('schema-shot-organization-page'))) {
                $json = $this->get_schema('organization');
                echo "<!-- #### ORGANIZATION #### -->\n";
                echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
            }
        }
        /* local business generico */
        if (get_option('schema-shot-localbusiness')) {
          /* inserito in tutte le pagine tranne che in quella dell'organization */
          if (get_option('schema-shot-organization') && !is_page(get_option('schema-shot-organization-page'))) {
                /* il local business è un restaurant */
                if (get_option('schema-shot-restaurant')) {
                    $json = $this->get_schema('local-business-restaurant');
                    /* il restaurant ha i MenuSection e MenuItem */
                    if (get_option('schema-shot-menu')) {
                        /* elenco i custom post di tipo menù */
                        $json .= "\n" . $this->get_menu_pages(get_option('schema-shot-menu-custom'));
                    }
                    $json .= "\n}\n";
                } else {
                    /* il local business non è un restaurant */
                    $json .= $this->get_schema('local-business');
                }
                echo "<!-- #### LOCAL BUSINESS #### -->\n";
                echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
            }
        }

        /* website - in homepage */
        if (get_option('schema-shot-website')) {
            if (is_front_page()) {
                $json = $this->get_schema('website');
                echo "<!-- #### WEBSITE #### -->\n";
                echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
            }
        }


        /* webpage - in tutte le pagine */
        /* GENERA UNA SERIE DI WARNING SULLO SCHEMA TOOL DI GOOGLE =================
        if (get_option('schema-shot-website')) {
            $json = $this->get_schema('webpage');
            echo "<!-- #### WEBPAGE #### -->\n";
            echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
        }
        */

        /* in base al tipo di post */
        switch (get_post_type()) {
            case 'post': /* article */
                if (is_single()) $json = $this->get_schema('article');
                echo "<!-- #### ARTICLE #### -->\n";
                echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
                break;
        }

        /* nei custom post 'menu' metto il menu e i piatti */
        if (get_option('schema-shot-menu')) {
            if (get_post_type() == get_option('schema-shot-menu-custom')) {
                $json = $this->get_schema('menu');
                if (get_option('schema-shot-piatti')) {
                    $piatti = $this->get_dishes_pages(get_option('schema-shot-piatti-custom'));
                    if ($piatti) $json .= ",\n" . $piatti;
                }
                echo "<!-- #### MENU #### -->\n";
                echo "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
            }
        }

        /* TODO *********************************************
        if (function_exists('is_product') && is_product()) {
            global $product;

            //schema org per prodotto singolo
            $product_schema = $this->get_schema('product');

            //aggiungo le varianti
            if ($product->is_type('variable')) {
                $offers = '"offers": [';
                $variations = $product->get_available_variations();
                for ($i = 0; $i < count($variations); $i++) {
                    $variation = wc_get_product($variations[$i]['variation_id']);
                    $stock_quantity = $variation->get_stock_quantity();
                    $offers .= '{ "@type": "Offer",';
                    $offers .= '"availability": "OutOfStock",';
                    $offers .= '},';
                }
                $offers .= "]";
                echo '<script type="application/ld+json">' . $product_schema . $offers . '}' . '</script>';
            }

        }
        */


    }

/**
 *
 * render_menu_link: inserisce i micro dati in ogni voce di menu <li>
 *
 */

    function render_menu_link($atts, $items, $args) {
        //if ($args['theme_location'] == 'primary') {
            $atts['itemprop'] = 'url';
            return $atts;
        //}
    }

/**
 *
 * render_menu: inserisce i micro dati nel tag <ul>
 *
 */
    function render_menu($content) {
        $content = str_replace('ul', 'ul itemscope itemtype="http://www.schema.org/SiteNavigationElement"', $content);
        return $content;
    }

/**
 *
 * breadcrumbs: mostra la sequenza di <li> dei breadcrumbs
 *
 */

    function breadcrumbs($id = 0, $class_prefix = 'ss_breadcrumbs', $i = 0) {
		$this->menu_level++;
        if (!$id) $id = get_the_ID();
        $page_title = get_the_title($id);
        $parent_id = wp_get_post_parent_id($id);
        $i++;
        if ($parent_id) $this->breadcrumbs($parent_id, $class_prefix, $i);
		if (!is_front_page()) {
			echo "<li itemprop=\"itemListElement\" itemscope itemtype=\"http://schema.org/ListItem\" class=\"{$class_prefix}__item {$class_prefix}--lv{$this->menu_level}\"><a itemtype=\"https://schema.org/Thing\" itemprop=\"item\" href=\"".get_the_permalink($id)."\"><span itemprop=\"name\">" . $page_title . "</span></a><meta itemprop=\"position\" content=\"{$this->menu_level}\" /></li>";
        }
    }

/**
 *
 * render_breadcrumbs: shortcode per i breadcrumbs
 * uso: echo do_shortcode('[ss_breadcrumbs home="Homepage"]');
 *
 */

    function render_breadcrumbs($atts, $content = null) {
        extract(shortcode_atts(array(
                'home' => 'Homepage'
            ), $atts,  'render_breadcrumbs'
        ));
        ob_start();
        echo '<ol itemscope itemtype="http://schema.org/BreadcrumbList" class="ss_breadcrumbs">';
        echo '<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem" class="ss_breadcrumbs__item ss_breadcrumbs--lv1"><a itemtype="https://schema.org/Thing" itemprop="item" href="/"><span itemprop="name">' . $home . '</span></a><meta itemprop="position" content="1"></li>';
        $this->breadcrumbs();
        echo '</ol>';
        return ob_get_clean();
    }

/**
 *
 * Aggiunta della pagina delle impostazioni
 *
 */

    function add_settings_page() {
        add_options_page(
            'Schema Shot',
            'Schema Shot',
            'manage_options',
            'schema-shot',
            array($this,'render_settings_page')
        );
    }

/**
 *
 * Render della pagina delle impostazioni
 *
 */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) wp_die('Non possiedi i permessi per accedere a questa pagina');
        ?>
        <div class="wrap">
            <h2>Schema Shot | Impostazioni</h2>
            <?php
            if (isset($_POST['submit']) && wp_verify_nonce($_POST['modify_settings_nonce'], 'modify_settings')) {
                update_option('schema-shot-organization', $_POST['schema-shot-organization']);
                update_option('schema-shot-organization-page', $_POST['schema-shot-organization-page']);
                update_option('schema-shot-localbusiness', $_POST['schema-shot-localbusiness']);
                //update_option('schema-shot-localbusiness-page', $_POST['schema-shot-localbusiness-page']);
                update_option('schema-shot-localbusiness-type', $_POST['schema-shot-localbusiness-type']);
                update_option('schema-shot-restaurant', $_POST['schema-shot-restaurant']);
                update_option('schema-shot-website', $_POST['schema-shot-website']);
                update_option('schema-shot-website-parameters', array(
                    'name' => $_POST['schema-shot-website-name'],
                    'alternateName' => $_POST['schema-shot-website-alternatename'],
                ));
            }
            ?>
            <form method="post">
                <?php wp_nonce_field('modify_settings', 'modify_settings_nonce') ?>
                <h3>1. Aggiungo lo schema per <em>Website?</em></h3>
                <select name="schema-shot-website">
                    <option value="0">No</option>
                    <option value="1" <?php if (get_option('schema-shot-website')) echo 'selected="selected"' ?>>Si</option>
                </select>
                <?php
                $website = get_option('schema-shot-website-parameters');
                ?>
                <br>
                <label>name</label><br>
                <input type="text" name="schema-shot-website-name" value="<?php echo $website['name'] ?>"><br>
                <label>alternateName</label><br>
                <input type="text" name="schema-shot-website-alternatename" value="<?php echo $website['alternateName'] ?>">
                <p><small>I campi <em>name</em> e <em>alternateName</em> sono condivisi da <em>Website</em>, <em>Local Business</em> e <em>Organization</em>.</small></p>
                <h3>2. Aggiungo lo schema per <em>Organization?</em></h3>
                <select name="schema-shot-organization">
                    <option value="0">No</option>
                    <option value="1" <?php if (get_option('schema-shot-organization')) echo 'selected="selected"' ?>>Si</option>
                </select>
                <small>Ricordati di <a target="_blank" href="<?php echo plugin_dir_url( __FILE__ ) . 'assets/templates/organization.json' ?>">controllare il JSON</a></small><br>
                <br><label>Seleziona su quale pagina inserire l'<em>Organization</em></label><br>
                <?php $pages = get_pages(); ?>
                <select name="schema-shot-organization-page">
                    <option value="0">Seleziona...</option>
                    <?php
                        for ($i = 0; $i < count($pages); $i++) {
                            echo '<option';
                            if (get_option('schema-shot-organization-page') == $pages[$i]->ID) echo ' selected="selected"';
                            echo ' value="' . $pages[$i]->ID . '">' . $pages[$i]->post_title . '</option>';
                        }
                    ?>
                </select>
                <h3>3. Aggiungo lo schema per <em>Local Business?</em></h3>
                <select name="schema-shot-localbusiness">
                    <option value="0">No</option>
                    <option value="1" <?php if (get_option('schema-shot-localbusiness')) echo 'selected="selected"' ?>>Si</option>
                </select>
                <small>Ricordati di <a target="_blank" href="<?php echo plugin_dir_url( __FILE__ ) . 'assets/templates/local-business.json' ?>">controllare il JSON</a></small><br>
                <label>tipo di LocalBusiness</label><br>
                <select name="schema-shot-localbusiness-type">
                    <?php
                    for ($i = 0; $i < count($this->local_business_list); $i++) {
                        echo "<option value=\"{$this->local_business_list[$i]}\"";
                        if (get_option('schema-shot-localbusiness-type') == $this->local_business_list[$i]) echo ' selected="selected"';
                        echo ">{$this->local_business_list[$i]}</option>";
                    }
                    ?>
                </select>
                <!--
                <br><label>Seleziona su quale pagina inserire il <em>Local Business</em></label><br>
                <?php //$pages = get_pages(); ?>
                <select name="schema-shot-localbusiness-page">
                    <option value="0">Seleziona...</option>
                    <?php
                    /*
                      for ($i = 0; $i < count($pages); $i++) {
                          echo '<option';
                          if (get_option('schema-shot-localbusiness-page') == $pages[$i]->ID) echo ' selected="selected"';
                          echo ' value="' . $pages[$i]->ID . '">' . $pages[$i]->post_title . '</option>';
                      }
                    */
                    ?>
                </select>
                -->
                <h3>3.1 Il <em>Local Business</em> è un <em>Restaurant</em>?</h3>
                <select name="schema-shot-restaurant">
                    <option value="0">No</option>
                    <option value="1" <?php if (get_option('schema-shot-restaurant')) echo 'selected="selected"' ?>>Si</option>
                </select>
                <small>Ricordati di <a target="_blank" href="<?php echo plugin_dir_url( __FILE__ ) . 'assets/templates/local-business-restaurant.json' ?>">controllare il JSON</a></small><br>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_menu_pages($slug) {
        $json = "\t" . '"hasMenu": [' . "\n";
        $query = new WP_query(array(
            'post_type' => $slug,
            'nopaging' => 'true',
            'posts_per_page' => -1,
        ));
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $json .= "\t\t" . '{' . "\n";
                $json .= "\t\t\t" . '"@type": "Menu",' . "\n";
                $json .= "\t\t\t" . '"name": "' . get_the_title() . '",' . "\n";
                $json .= "\t\t\t" . '"url": "' . get_permalink() . '",' . "\n";
                $json .= "\t\t" . '},' . "\n";
            }
        }
        $json .= "\t" . ']' . "\n";
        wp_reset_query();
        wp_reset_postdata();
        return $json;
    }

}

new SchemaShot();
