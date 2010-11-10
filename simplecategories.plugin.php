<?php

class SimpleCategories extends Plugin
{
	private static $vocabulary = 'categories';
	private static $content_type = 'entry';

	protected $_vocabulary;


	public function  __get( $name )
	{
		switch ( $name ) {
			case 'vocabulary':
				if ( !isset( $this->_vocabulary ) ) {
					$this->_vocabulary = Vocabulary::get( self::$vocabulary );
				}
				return $this->_vocabulary;
		}
	}
	/**
	 * Add the category vocabulary and create the admin token
	 *
	 **/
	public function action_plugin_activation( $file )
	{
		if ( Plugins::id_from_file( $file ) == Plugins::id_from_file(__FILE__) ) {
			$params = array(
				'name' => self::$vocabulary,
				'description' => 'A vocabulary for describing Categories',
				'features' => array( 'multiple', 'hierarchical' )
			);

			Vocabulary::create( $params );

			// create default access token
			ACL::create_token( 'manage_categories', _t( 'Manage categories' ), 'Administration', false );
			$group = UserGroup::get_by_name( 'admin' );
			$group->grant( 'manage_categories' );
		}
	}

	/**
	 * Remove the admin token
	 *
	 **/
	public function action_plugin_deactivation( $file )
	{
		// delete default access token
		ACL::destroy_token( 'manage_categories' );
	}

	/**
	 * Register admin template
	 **/
	public function action_init()
	{
		$this->add_template( 'categories', dirname( $this->get_file() ) . '/categories_admin.php' );
	}

	/**
	 * Check token to restrict access to the page
	 **/
	public function filter_admin_access_tokens( array $require_any, $page )
	{
		switch ( $page ) {
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

		if (!isset( $_GET[ 'category' ] ) ) { // create new category form

			$form = new FormUI( 'category-new' );
			$form->set_option( 'form_action', URL::get( 'admin', 'page=categories' ) );

			$create_fieldset = $form->append( 'fieldset', '', _t( 'Create a new Category', 'simplecategories' ) );
			$category = $create_fieldset->append( 'text', 'category', 'null:null', _t( 'Category', 'simplecategories' ), 'formcontrol_text' );
			$category->add_validator( 'validate_required' );
			$category->class = 'pct30';
			
			$parent = $create_fieldset->append( 'select', 'parent', 'null:null', _t( 'Parent', 'simplecategories' ), $this->vocabulary->get_options(), 'optionscontrol_select' );
			$parent->class = 'pct50';

			$save_button = $create_fieldset->append( 'submit', 'save', _t( 'Create', 'simplecategories' ) );
			$save_button->class = 'pct20 last';

			$cancelbtn = $form->append( 'button', 'btn', _t( 'Cancel', 'simplecategories' ) );

			$form->on_success( array( $this, 'formui_create_submit' ) );

 		} 
		else { // edit form for existing category

			$which_category = $_GET[ 'category' ];
			$category_term = $this->vocabulary->get_term( $which_category );
			if ( !$category_term ) {
				exit;
			}

			$parent_term = $category_term->parent();
			if ( !$parent_term ) {
				$parent_term_display = _t( 'none', 'simplecategories' );
			}
			else {
				$parent_term_display = $parent_term->term_display;
			}

			$form = new FormUI( 'category-edit' );
			$form->set_option( 'form_action', URL::get( 'admin', 'page=categories&category=' . $_GET[ 'category' ] ) );
			$category_id = $form->append( 'hidden', 'category_id' )->value = $category_term->id; // send this id, for seeing what has changed
			$edit_fieldset = $form->append( 'fieldset', '', sprintf( _t( 'Edit Category: <b>%1$s</b>' , 'simplecategories' ), $category_term->term_display ) );
			$category = $edit_fieldset->append( 'text', 'category', 'null:null', _t( 'Rename Category', 'simplecategories' ), 'formcontrol_text' );
			$category->value = $category_term->term_display;
			$category->add_validator( 'validate_required' );
			$category->class = 'pct30';

			$parent = $edit_fieldset->append( 'select', 'parent', 'null:null', _t( 'Current Parent: <b>%1$s</b> Change Parent to:', array($parent_term_display), 'simplecategories'), $this->vocabulary->get_options(), 'optionscontrol_select' );
			$parent->class = 'pct50';
			$parent->value = ( !$parent_term ? '': $parent_term->id ); // select the current parent

			$save_button = $edit_fieldset->append( 'submit', 'save', _t( 'Edit', 'simplecategories' ) );
			$save_button->class = 'pct20 last';

			$cancel_button = $form->append( 'submit', 'cancel_btn', _t( 'Cancel', 'simplecategories' ) );
	
			$form->on_success( array( $this, 'formui_edit_submit' ) );
		}
		$theme->form = $form->get();

		$theme->display( 'categories' );
		// End everything
		exit;
	}

	public function formui_create_submit( FormUI $form )
	{
		if( isset( $form->category ) && ( $form->category->value <> '' ) ) {

			// time to create the new term.
			$form_parent = $form->parent->value;
			$new_term = $form->category->value;

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
		// redirect to the page to update the form
		Utils::redirect( URL::get( 'admin', array( 'page'=>'categories' ) ), true );
	}

	public function formui_edit_submit( FormUI $form )
	{
		if( isset( $form->category ) && ( $form->category->value <> '' ) ) {
			if( isset( $form->category_id ) ) {
				$current_term = $this->vocabulary->get_term( $form->category_id->value );

				// If there's a changed parent, change the parent.
				$cur_parent = $current_term->parent();
				$new_parent = $this->vocabulary->get_term( $form->parent->value );

				if ( $cur_parent ) {
					if ( $cur_parent->id <> $form->parent->value ) {
						// change the parent to the new ID.
						$this->vocabulary->move_term( $current_term, $new_parent );
					}
				}
				else 	{
					// cur_parent is false, should mean $current_term is a root element
					$this->vocabulary->move_term( $current_term, $new_parent );
				}

			if ( $form->category->value !== $current_term->term_display ) {
			SimpleCategories::rename( $form->category->value, $current_term->term_display );
				// If the category has been renamed, modify the term}
				}
			}
		}
		// redirect to the page to update the form
		Utils::redirect( URL::get( 'admin', array( 'page'=>'categories' ) ), true );
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
			'url' => URL::get( 'admin', 'page=categories' ),
			'title' => _t( 'Manage blog categories' ),
			'text' => _t( 'Categories' ),
			'hotkey' => 'W',
			'selected' => false,
			'access' => array( 'manage_categories' => true )
		) );
		
		$slice_point = array_search( 'dashboard', array_keys( $menu ) ); // Element will be inserted before "groups"
		$pre_slice = array_slice( $menu, 0, $slice_point );
		$post_slice = array_slice( $menu, $slice_point );
		
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

			$form->append( 'text', 'categories', 'null:null', _t( 'Categories, separated by, commas' ), 'admincontrol_text' );
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
			$term = $this->vocabulary->get_term( $vars['category_slug'] );
			if ( $term instanceof Term ) {
				$terms = (array)$term->descendants();
				$terms = array_map( create_function( '$a', 'return $a->term;' ), $terms );
				array_push( $terms, $vars['category_slug'] );
				$filters['vocabulary'] = array_merge( $filters['vocabulary'], array( self::$vocabulary . ':term' => $terms ) );
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
		$paramarray[ 'fallback' ] = array(
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
				$categories[ $t->term ] = $t->term_display;
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

	/**
	 * function delete_category
	 * Deletes an existing category and all relations to it.
	 **/
	public static function delete_category( $category = '' )
	{
		$vocabulary = Vocabulary::get( self::$vocabulary );
		// should there be a Plugins::act( 'category_delete_before' ...?
		$term = $vocabulary->get_term( $category );
		if ( !$term ) {
			return false; // no match for category
		}

		$result = $vocabulary->delete_term( $term );

		if ( $result ) {
			EventLog::log( sprintf( _t( 'Category \'%1$s\' deleted.' ), $category ), 'info', 'content', 'simplecategories' );
		}
		// should there be a Plugins::act( 'category_delete_after' ...?
		return $result;
	}

	/**
	 * Renames a category
	 * If the master category exists, the categories will be merged with it.
	 * If not, it will be created first.
	 *
	 * Adapted from Tags::rename()
	 *
	 * @param mixed tag The category text, slug or id to be renamed
	 * @param mixed master The category to which it should be renamed, or the slug, text or id of it
	 **/
	public static function rename( $master, $category, $object_type = 'post' )
	{
		$vocabulary = Vocabulary::get( self::$vocabulary );
		$type_id = Vocabulary::object_type_id( $object_type );

		// get the term to be renamed
		$term = $vocabulary->get_term( $category );

		// get the master term
		$master_term = $vocabulary->get_term( $master );

		// check if it already exists
		if ( !isset( $master_term->term ) ) {
			// it didn't exist, so we assume it's text and create it
			$term->term_display = $master;
			$term->term = $master;
			$term->update();
			// that's it, we're done.
			EventLog::log(
				_t( 'Category %s has been renamed to %s.', array( $category, $master ), 'simplecategories' ), 'info', 'category', 'simplecategories'
			);
		}
		else {
			if ( ! $master_term->is_descendant_of( $term ) ) {

				$posts = array();
				$posts = Posts::get( array(
					'vocabulary' => array( 'any' => array( $term ), 'not' => array( $master_term ) ),
					'nolimit' => true,
				) );

				// categorize all the $category Posts as $master
				foreach ( $posts as $post ) {
	//				$vocabulary->set_object_terms( 'post', $post->id, $master );
					$master_term->associate( 'post', $post->id );
				}

				// move the old $term's children over to $master_term
				foreach ( $term->children() as $child ) {
					// is this needed?
	//				$child = $vocabulary->get_term( $child->id );
					$vocabulary->move_term( $child, $master_term );
				}

				// delete the old $term and all its associations
				self::delete_category( $term->id );
				EventLog::log(
					_t( 'Category %s has been merged into %s.', array( $category, $master ), 'simplecategories' ), 'info', 'category', 'simplecategories'
				);
			}
			else {
				Session::notice( _t( 'Cannot merge %1$s into %2$s, since %2$s is a descendant of %1$s', array( $term, $master, ), 'shelves' ) );
			}
		}
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
			$between_last = _t( ' and ', 'simplecategories' );
		}

		$array = array_map( 'SimpleCategoriesFormat::link_cat', $array, array_keys( $array ) );
		$last = array_pop( $array );
		$out = implode( $between, $array );
		$out .= ( $out == '' ) ? $last : $between_last . $last;
		return $out;
	}

	public static function link_cat( $a, $b ) {
		return '<a href="' . URL::get( "display_entries_by_category", array( "category_slug" => $b ) ) . "\" rel=\"category\">$a</a>";
	}

}

?>
