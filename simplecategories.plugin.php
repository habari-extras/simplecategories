<?php

class SimpleCategories extends Plugin
{
	private static $vocabulary = 'simplecategories';
	private static $content_type = 'entry';
	private static $select_none = 'none';

	/**
	 * Add the category vocabulary
	 *
	 **/
	public function action_plugin_activation($file)
	{
		if ( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			$params = array(
				'name' => self::$vocabulary,
				'description' => 'A vocabulary for describing Categories',
				'features' => array( 'multiple', 'free' )
			);

			$simple_categories = new Vocabulary( $params );
			$simple_categories->insert();

/* shouldn't need this anymore
			$test_term = $simple_categories->add_term( 'cat' );
 			$test_term->associate( 'post', 4 );
*/
		}
	}

	/**
	 *
	 **/
	public function action_init()
	{

	}

	public function action_form_publish ( $form, $post )
	{
		if ( $form->content_type->value == Post::type( self::$content_type ) ) {
			$parent_term = null;
			$descendants = null;
			$categories_vocab = Vocabulary::get( self::$vocabulary );

			$form->append( 'text', 'categories', 'null:null', _t( 'Categories, separated by, commas'), 'admincontrol_text');
			$form->categories->class = 'check-change';
			$form->move_after( $form->categories, $form->tags );

			// If this is an existing post, see if it has categories already
			if ( 0 != $post->id ) {
				$form->categories->value = implode( ', ', array_values( $this->get_categories( $post ) ) );
			}

			/* I think this if statement is leftovers from subpages */

			if ( 0 != $post->id ) {
				$page_term = $categories_vocab->get_term( $post->slug );
				if ( FALSE !== $page_term ) {
					$parent_term = $page_term->parent();
					$descendants = $page_term->descendants();
				}
			}

		}
	}

	public function action_publish_post( $post, $form )
	{
		if ( $post->content_type == Post::type( self::$content_type ) ) {
			$categories = array();
			$categories = $this->parse_categories( $form->categories->value );
			Vocabulary::get( self::$vocabulary )->set_object_terms( 'post', $post->id, $categories );
		}
	}

/*	public function action_publish_post( $post, $form )
	{
		if ( $post->content_type == Post::type( self::$content_type ) ) {
			$categories_vocab = Vocabulary::get( self::$vocabulary );
			$categories = Vocabulary::get( self::$vocabulary );

			// go through the categories in the input, add ones not already there, delete the ones not there anymore
//             $this->tags = $this->parsetags( $this->fields['tags'] );



			$page_term = $subpage_vocab->get_term( $post->slug );

			if ( null != $page_term ) {
				$parent_term = $page_term->parent();
			}

			$form_parent = $form->settings->parent->value;

			// If the parent has been changed, delete this page from its children
			if ( null != $parent_term && $form_parent != $parent_term->term ) {
				$subpage_vocab->delete_term( $page_term->term );
			}

			// If a new term has been set, add it to the subpages vocabulary
			if ( self::$select_none != $form_parent ) {
				// Make sure the parent term exists.
				$parent_term = $subpage_vocab->get_term( $form_parent );

				if ( null == $parent_term ) {
					// There's no term for the parent, add it as a top-level term
					$parent_term = $subpage_vocab->add_term( $form_parent );
				}

				$page_term = $subpage_vocab->add_term( $post->slug, $parent_term );
			}

		}
	}
*/
	/**
	 * Enable update notices to be sent using the Habari beacon
	 **/
	public function action_update_check()
	{
		Update::add( 'SimpleCategories', '379220dc-b464-4ea6-92aa-9086a521db2c',  $this->info->version );
	}

	/**
	 * Change the page rewrite rule to validate ancestors
	 * @param Array $rules Current rewrite rules
	 **/
	public function filter_rewrite_rules( $rules )
	{
		foreach ( $rules as $rule ) {
			if ( 'display_page' == $rule->name ) {
				$rule->parse_regex = '%^(?P<parentage>.*/)(?P<slug>[^/]+)(?:/page/(?P<page>\d+))?/?$%i';
				$rule->description = 'Return page matching specified slug and page hierarchy';
				$rule->parameters = serialize( array( 'require_match' => array('SubPagesPlugin', 'rewrite_match_subpage') ) );
				break;
			}
		}

		return $rules;
	}

	/**
	 * Validate ancestors for this page
	 * @param RewriteRule $rule The matched rewrite rule
	 * @param string The URL stub requested
	 * @param array $params Some stuff
	 **/
	public static function rewrite_match_subpage( $rule, $stub, $params )
	{
		// TODO Is there any way to get these apart from rematching ?
		// They're not set until there's a handler, and that isn't set yet.
		$slugs = explode('/', $stub);

		$args = array(
			'content_type' => 'page'
		);

		// Check we can get a page for each part of the slug hierarchy
		$args['slug'] = $slugs;

		$posts = Posts::get($args);

		if ( count($posts) != count($slugs) ) {
			return false;
		}

		// Check the stub matches the expected stub
		return $stub == self::subpage_stub( array_pop( $slugs ) );
	}

	/**
	 * Rewrite a post's permalink if it's a subpage
	 * @param Array $rules Current rewrite rules
	 **/
	public function filter_post_permalink( $permalink, $post )
	{
		if ( $post->content_type == Post::type( self::$content_type ) ) {
			$subpage_vocab = Vocabulary::get( self::$vocabulary );
			$page_term = $subpage_vocab->get_term( $post->slug );
			if ( null != $page_term ) {
				$permalink = Site::get_url( 'habari' ) . '/' . self::subpage_stub( $page_term );
			}
		}

		return $permalink;
	}

	/** 
	 * return an array of categories, having been cleaned up a bit. Taken from post.php r3907
	 * @param String $categories Text from the Category text input
	 */
	private static function parse_categories( $categories )
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

	public function filter_default_rewrite_rules( $rules ) {
		$rule = array( 	'name' => 'display_entries_by_category', 
				'parse_regex' => '%^category/(?P<category>[^/]*)(?:/page/(?P<page>\d+))?/?$%i', 
				'build_str' => 'category/{$category}(/page/{$page})', 
				'handler' => 'UserThemeHandler', 
				'action' => 'display_category', 
				'priority' => 5, 
				'description' => 'Return posts matching specified category.', 
// not so sure about this last line
				'parameters' => serialize( array( 'require_match' => array('Category', 'rewrite_category_exists') ) ) 
		);

		$rules[] = $rule;	
		return $rules;
	}

	/**
	 * Helper function: Display the posts for a category. Probably should be more generic eventually.
	 */
	public function filter_theme_act_display_entries_by_category( $handled, $theme ) {
		$paramarray['fallback'] = array(
			'category.{$category}',
			'category',
			'multiple',
		);

		// Makes sure home displays only entries
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
		$result = Vocabulary::get( self::$vocabulary )->get_object_terms( 'post', $post->id );
Utils::debug( $result );
		if( $result ) {
			foreach( $result as $t ) {
				$categories[$t->term] = $t->term_display;
			}
		}
		return $categories;
	}

	public function filter_post_get( $out, $name, $post )
	{
		if( $name != 'categories' ) {
			return $out;
		}
		$categories = array();
		$result = Vocabulary::get( self::$vocabulary )->get_object_terms( 'post', $post->id );
		if( $result ) {
			foreach( $result as $t ) {
				$categories[$t->term] = $t->term_display;
			}
		}
		return $categories;
	}

}

?>
