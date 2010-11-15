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
		$category_term = false;
		$parent_term = false;
		$parent_term_display = _t( 'None', 'simplecategories' );

		if( isset( $_GET['action'] ) ) {
			switch( $_GET['action'] ) {
				case 'delete':
					$term = $_GET['category'];
					$this->delete_category( $term );
					break;
				case 'edit':
					$term = $_GET['category'];
					$category_term = $this->vocabulary->get_term( (int)$term );
					if ( $category_term ) {
						$parent_term = $category_term->parent();
						if ( $parent_term ) {
							$parent_term_display = $parent_term->term_display;
						}
					}
					break;
			}
		}

		if ( isset( $GET['category'] ) ) {
			$term = $_GET['category'];
			$category_term = $this->vocabulary->get_term( (int)$term );
			if ( $category_term ) {
				$parent_term = $category_term->parent();
				if ( $parent_term ) {
					$parent_term_display = $parent_term->term_display;
				}
			}
		}

		$options = array( 0 => _t( '(none)', 'simplecategories') ) + $this->vocabulary->get_options();
		$form = new FormUI( 'simplecategories' );
		if ( $category_term ) {
			$category_id = $form->append( 'hidden', 'category_id' )->value = $category_term->id; // send this id, for seeing what has changed
			$fieldset = $form->append( 'fieldset', '', sprintf( _t( 'Edit Category: <b>%1$s</b>' , 'simplecategories' ), $category_term->term_display ) );
		}
		else {
			$fieldset = $form->append( 'fieldset', '', _t( 'Create a new Category', 'simplecategories' ) );
		}

		$category = $fieldset->append( 'text', 'category', 'null:null', _t( 'Category', 'simplecategories' ), 'formcontrol_text' );
		$category->value = !$category_term ? '' : $category_term->term_display;
		$category->add_validator( 'validate_required' );
		$category->class = 'pct30';

		$parent = $fieldset->append( 'select', 'parent', 'null:null', _t( 'Parent: <b>%1$s</b> Change Parent to:', array($parent_term_display), 'simplecategories'), $options, 'optionscontrol_select' );
		$parent->value = ( !$parent_term ? '': $parent_term->id ); // select the current parent
		$parent->class = 'pct50';

		$save_button = $fieldset->append( 'submit', 'save', _t( 'Save', 'simplecategories' ) );
		$save_button->class = 'pct20 last';

//		$cancelbtn = $form->append( 'button', 'btn', _t( 'Cancel', 'simplecategories' ) );
//		$cancelbtn->id = 'btn';
//		$cancelbtn->onclick = 'habari_ajax.get("' . URL::get( 'admin', 'page=categories' ) . '")' ;
		$cancelbtn = $form->append( 'static', 'btn', '<p id="btn" ><a class="button dashboardinfo" href="' . URL::get( 'admin', 'page=categories' ) . '">' . _t( 'Cancel', 'simplecategories' ) . "</a></p>\n" );

		if ( $category_term ) { //editing an existing category
			$form->on_success( array( $this, 'formui_edit_submit' ) );
		}
		else { //new category
			$form->on_success( array( $this, 'formui_create_submit' ) );
		}

		$theme->form = $form;

		$theme->all_categories = $this->vocabulary->get_tree();
		$theme->display( 'categories' );
		// End everything
		exit;
	}

	public function formui_create_submit( FormUI $form )
	{
		// time to create the new term.
		$parent = $this->vocabulary->get_term( (int)$form->parent->value );
		$new_term = $form->category->value;

		if ( $parent ) {
			$this->vocabulary->add_term( $new_term, $parent );
		}
		else {
			$this->vocabulary->add_term( $new_term );
		}
		// redirect to the page to update the form
		Utils::redirect( URL::get( 'admin', array( 'page'=>'categories' ) ), true );
	}

	public function formui_edit_submit( FormUI $form )
	{
		if( isset( $form->category ) && ( $form->category->value <> '' ) ) {
			if( isset( $form->category_id ) ) {
				$current_term = $this->vocabulary->get_term( (int)$form->category_id->value );

				// If there's a changed parent, change the parent.
				$cur_parent = $current_term->parent();
				$new_parent = $this->vocabulary->get_term( (int)$form->parent->value );

				if ( $new_parent ) {
					$this->vocabulary->move_term( $current_term, $new_parent );
				}
				else {
					$this->vocabulary->move_term( $current_term );
				}

				if ( $form->category->value !== $current_term->term_display ) {
//					SimpleCategories::rename( $form->category->value, $current_term->term_display );
					$this->vocabulary->merge( $form->category->value, array( $current_term->term_display ) );
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
//			$categories = $this->parse_categories( $form->categories->value );
			$categories = Terms::parse( $form->categories->value, 'Term', $this->vocabulary );
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
//				'parse_regex' => '%^category/(?P<category_slug>[^/]*)(?:/page/(?P<page>\d+))?/?$%i',
				'parse_regex' => '%^category/(?P<category_slug>.*)(?:/page/(?P<page>\d+))?/?$%i',
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
			$labels = explode( '/', $vars['category_slug'] );
			$level = count( $labels ) - 1;
			$term = $this->get_term( $labels, $level );

//			$term = $this->vocabulary->get_term( $vars['category_slug'] );
			if ( $term instanceof Term ) {
				$terms = (array)$term->descendants();
				array_push( $terms, $term );
				$filters['vocabulary'] = array_merge( $filters['vocabulary'], array( 'any' => $terms ) );
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
	private function delete_category( $category  )
	{
		// should there be a Plugins::act( 'category_delete_before' ...?
		$term = $this->vocabulary->get_term( (int)$category );
		if ( !$term ) {
			return false; // no match for category
		}

		$result = $this->vocabulary->delete_term( $term );

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

	protected function get_term( $labels, $level )
	{
		$root_term = false;
		$root = $labels[0];
		$roots = $this->vocabulary->get_root_terms();
		foreach( $roots as $term ) {
			if ( $root == $term->term ) {
				$root_term = $term;
				break;
			}
		}


		for( $i = 1; $i <= $level; $i++ ) {
			$term = $labels[$i];
			$roots = $root_term->children( $root_term );
			foreach( $roots as $cur ) {
				if ( $cur->term == $term ) {
					$root_term = $cur;
					break;
				}
			}
		}
		return $root_term;

	}

	public function filter_posts_search_to_get( $arguments, $flag, $value, $match, $search_string )
	{
		if ( 'category' == $flag ) {
			$arguments['vocabulary'][$this->vocabulary->name . ':term_display'][] = $value;
		}
		return $arguments;
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
