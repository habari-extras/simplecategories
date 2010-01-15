<?php

class SimpleCategories extends Plugin
{
	private static $vocabulary = 'categories';
	private static $content_type = 'entry';

	protected $_vocabulary;


	public function  __get($name)
	{
		switch ( $name ) {
			case 'vocabulary':
				if ( !isset($this->_vocabulary) ) {
					$this->_vocabulary = Vocabulary::get(self::$vocabulary);
				}
				return $this->_vocabulary;
		}
	}
	/**
	 * Add the category vocabulary and create the admin token
	 *
	 **/
	public function action_plugin_activation($file)
	{
		if ( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			$params = array(
				'name' => self::$vocabulary,
				'description' => 'A vocabulary for describing Categories',
				'features' => array( 'multiple', 'hierarchical' )
			);

			$simple_categories = new Vocabulary( $params );
			$simple_categories->insert();

			// create default access token
			ACL::create_token( 'manage_categories', _t('Manage categories'), 'Administration', false );
			$group = UserGroup::get_by_name( 'admin' );
			$group->grant( 'manage_categories' );
		}
	}

	/**
	 * Remove the admin token
	 *
	 **/
	public function action_plugin_deactivation($file)
	{
		if ( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			// delete default access token
			ACL::destroy_token( 'manage_categories' );
		}
	}

	/**
	 * Register admin template
	 **/
	public function action_init()
	{
		$this->add_template( 'categories', dirname($this->get_file()) . '/categories_admin.php' );
	}

	/**
	 * Check token to restrict access to the page
	 **/
	public function filter_admin_access_tokens( array $require_any, $page )
	{
		switch ($page) {
			case 'categories':
				$require_any = array( 'manage_categories' => true );
				break;
		}
		return $require_any;
	}

	/**
	 * Display the page
	 **/
	public function action_admin_theme_get_categories( AdminHandler $handler, Theme $theme )
	{
		$all_terms = array();
		$all_terms = $this->vocabulary->get_tree();

		$form = new FormUI( 'category-new' );
		$form->set_option( 'form_action', URL::get( 'admin', 'page=categories' ) );

		$create_fieldset = $form->append( 'fieldset', '', _t( 'Create a new Category' ) );
		$new_term = $create_fieldset->append( 'text', 'new_term', 'null:null', _t( 'Category', 'simplecategories' ), 'formcontrol_text' );
		$new_term->add_validator( 'validate_required' );

		$new_term->class = 'pct30';
		$parent = $create_fieldset->append( 'select', 'parent', 'null:null', _t( 'Parent', 'simplecategories' ), 'asdasdaoptionscontrol_select' );
		$parent->class = 'pct50';
		$parent->options = array();
		$parent->options[ '' ] = ''; // top should be blank
		$right = array();
		foreach( $all_terms as $term ) {
			while ( count($right) > 0 && $right[count($right) - 1] < $term->mptt_right ) {
				array_pop($right);
			}
			$parent->options[ $term->id ] = str_repeat(' - ', count($right) ) . $term->term_display;
			$right[] = $term->mptt_right;
		}
		$action = $form->append( 'hidden', 'action', 'create' );
		$save_button = $create_fieldset->append( 'submit', 'save', _t('Create', 'simplecategories') );
		$save_button->class = 'pct20 last';

		$form->on_success( array($this, 'formui_submit') );

		$theme->form = $form->get();

		$theme->display( 'categories' );
 
		// End everything
		exit;
	}

	public function formui_submit( FormUI $form )
	{
		// probably should have some sort of action switch.

		if( isset($form->new_term) && ( $form->new_term->value <> '' ) ) {

			// time to create the new term.
			$form_parent = $form->parent->value;
			$new_term = $form->new_term->value;


			// If a new term has been set, add it to the categories vocabulary
			if ( '' != $form_parent ) {
				// Make sure the parent term exists.
				$parent_term = $this->vocabulary->get_term( $form_parent );

				if ( null == $parent_term ) {
					// There's no term for the parent, add it as a top-level term
					$parent_term = $this->vocabulary->add_term( $form_parent );
				}

				$category_term = $this->vocabulary->add_term( $new_term, $parent_term );
			}
			else {
				$category_term = $this->vocabulary->add_term( $new_term );
			}

		}
	}

	/**
	 * TEMPORARY FUNCTION, SHOULD BE IMPLEMENTED BETTER IN VOCABULARY
	 * Retrieve the terms of the vocabulary 
	 * @return Array The Term objects in the vocabulary, in aplhabetical order
	 **/
	private function get_terms($orderby = 'term_display')
	{
		return DB::get_results(
			"SELECT * FROM {terms} WHERE vocabulary_id = :vocabulary_id ORDER BY :orderby",
			array(
				'orderby' => $orderby,
				'vocabulary_id' => $this->vocabulary->id
			),
			'Term'
		);
	}


	/**
	 * Cover both post and get requests for the page
	 **/
	public function alias()
	{
		return array( 
			'action_admin_theme_get_categories'=> 'action_admin_theme_post_categories' 
		);
	}
	
	/**
	 * Add menu item above 'dashboard'
	 **/
	public function filter_adminhandler_post_loadplugins_main_menu( array $menu )
	{
		$item_menu = array( 'categories' => array(
			'url' => URL::get( 'admin', 'page=categories'),
			'title' => _t('Manage blog categories'),
			'text' => _t('Categories'),
			'hotkey' => 'W',
			'selected' => false,
			'access' => array( 'manage_categories' => true )
		) );
		
		$slice_point = array_search( 'dashboard', array_keys( $menu ) ); // Element will be inserted before "groups"
		$pre_slice = array_slice( $menu, 0, $slice_point);
		$post_slice = array_slice( $menu, $slice_point);
		
		$menu = array_merge( $pre_slice, $item_menu, $post_slice );
		
		return $menu;
	}

	/**
	 * Add categories to the publish form
	 **/
	public function action_form_publish ( $form, $post )
	{
		if ( $form->content_type->value == Post::type( self::$content_type ) ) {
			$parent_term = null;
			$descendants = null;

			$form->append( 'text', 'categories', 'null:null', _t( 'Categories, separated by, commas'), 'admincontrol_text');
			$form->categories->class = 'check-change';
			$form->categories->tabindex = $form->tags->tabindex + 1;
			$form->move_after( $form->categories, $form->tags );
			$form->save->tabindex = $form->save->tabindex + 1;

			// If this is an existing post, see if it has categories already
			if ( 0 != $post->id ) {
				$form->categories->value = implode( ', ', array_values( $this->get_categories( $post ) ) );
			}
		}
	}

	/**
	 * Process categories when the publish form is received
	 *
	 **/
	public function action_publish_post( $post, $form )
	{
		if ( $post->content_type == Post::type( self::$content_type ) ) {
			$categories = array();
			$categories = $this->parse_categories( $form->categories->value );
			$this->vocabulary->set_object_terms( 'post', $post->id, $categories );
		}
	}

	/**
	 * Enable update notices to be sent using the Habari beacon
	 **/
	public function action_update_check()
	{
		Update::add( 'SimpleCategories', '379220dc-b464-4ea6-92aa-9086a521db2c',$this->info->version );
	}

	/** 
	 * return an array of categories, having been cleaned up a bit. Taken from post.php r3907
	 * @param String $categories Text from the Category text input
	 */
	public static function parse_categories( $categories )
	{
		if ( is_string( $categories ) ) {
			if ( '' === $categories ) {
				return array();
			}
			// just as dirrty as it is in post.php ;)
			$rez = array( '\\"'=>':__unlikely_quote__:', '\\\''=>':__unlikely_apos__:' );
			$zer = array( ':__unlikely_quote__:'=>'"', ':__unlikely_apos__:'=>"'" );
			// escape
			$catstr = str_replace( array_keys( $rez ), $rez, $categories );
			// match-o-matic
			preg_match_all( '/((("|((?<= )|^)\')\\S([^\\3]*?)\\3((?=[\\W])|$))|[^,])+/', $catstr, $matches );
			// cleanup
			$categories = array_map( 'trim', $matches[0] );
			$categories = preg_replace( array_fill( 0, count( $categories ), '/^(["\'])(((?!").)+)(\\1)$/'), '$2', $categories );
			// unescape
			$categories = str_replace( array_keys( $zer ), $zer, $categories );
			// just as hooray as it is in post.php
			return $categories;
		}
		elseif ( is_array( $categories ) ) {
			return $categories;
		}
	}

	/**
	 * Add a category rewrite rule
	 * @param Array $rules Current rewrite rules
	 **/
	public function filter_default_rewrite_rules( $rules ) {
		$rule = array( 	'name' => 'display_entries_by_category', 
				'parse_regex' => '%^category/(?P<category_slug>[^/]*)(?:/page/(?P<page>\d+))?/?$%i', 
				'build_str' => 'category/{$category_slug}(/page/{$page})', 
				'handler' => 'UserThemeHandler', 
				'action' => 'display_entries_by_category', 
				'priority' => 5, 
				'description' => 'Return posts matching specified category.', 
		);

		$rules[] = $rule;	
		return $rules;
	}

	/**
	 * function filter_template_where_filters
	 * Limit the Posts::get call to categories 
	 * (uses tag_slug because that's really term under the hood)
	 **/
	public function filter_template_where_filters( $filters ) {
		$vars = Controller::get_handler_vars();
		if( isset( $vars['category_slug'] ) ) {
// 			$filters['tag_slug'] = $vars['category_slug'];
			$term = Term::get($this->vocabulary, $vars['category_slug']);
			if ( $term instanceof Term ) {
				$terms = (array) $term->descendants();
				$terms = array_map(create_function('$a', 'return $a->term;'), $terms);
				$terms = array_push($terms, $vars['category_slug']);
				$filters['tag_slug'] = $terms;
			}
		}
		return $filters;
	}

	/**
	 * function filter_theme_act_display_entries_by_category
	 * Helper function: Display the posts for a category. Probably should be more generic eventually.
	 * Does not appear to work currently.
	 */
	public function filter_theme_act_display_entries_by_category( $handled, $theme ) {
		$paramarray = array();
		$paramarray['fallback'] = array(
			'category.{$category}',
			'category',
			'multiple',
		);

		// Makes sure home displays only entries ... maybe not necessary. Probably not, in fact.
		$default_filters = array(
 			'content_type' => Post::type( 'entry' ),
		);

		$paramarray[ 'user_filters' ] = $default_filters;

		$theme->act_display( $paramarray );
		return true;
	}

	/**
	 * function get_categories
	 * Gets the categories for the post
	 * @return array The categories array for this post
	 */
	private function get_categories( $post )
	{
		$categories = array();
		$result = $this->vocabulary->get_object_terms( 'post', $post->id );
		if( $result ) {
			foreach( $result as $t ) {
				$categories[$t->term] = $t->term_display;
			}
		}
		return $categories;
	}

	/**
	 * function filter_post_get
	 * Allow post->categories
	 * @return array The categories array for this post
	 **/
	public function filter_post_get( $out, $name, $post )
	{
		if( $name != 'categories' ) {
			return $out;
		}
		$categories = array();
		$result = $this->vocabulary->get_object_terms( 'post', $post->id );
		if( $result ) {
			foreach( $result as $t ) {
				$categories[$t->term] = $t->term_display;
			}
		}
		return $categories;
	}
}

class SimpleCategoriesFormat extends Format {

	/**
	 * function category_and_list
	 * Formatting function (should be in Format class?)
	 * Turns an array of category names into an HTML-linked list with commas and an "and".
	 * @param array $array An array of category names
	 * @param string $between Text to put between each element
	 * @param string $between_last Text to put between the next to last element and the last element
	 * @return string HTML links with specified separators.
	 **/
	public static function category_and_list( $array, $between = ', ', $between_last = NULL )
	{
		if ( ! is_array( $array ) ) {
			$array = array ( $array );
		}

		if ( $between_last === NULL ) {
			$between_last = _t(' and ');
		}

		$array = array_map('SimpleCategoriesFormat::link_cat', $array, array_keys($array));
		$last = array_pop($array);
		$out = implode($between, $array);
		$out .= ($out == '') ? $last : $between_last . $last;
		return $out;
	}

	public static function link_cat( $a, $b ) {
		return '<a href="' . URL::get("display_entries_by_category", array( "category_slug" => $b) ) . "\" rel=\"category\">$a</a>";
	}

}

?>
