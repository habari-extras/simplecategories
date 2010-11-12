<?php $theme->display('header');?>

	<div class="container">

<?php	$form->out(); ?>

	</div>

	<div class="container plugins activeplugins">
<?php
	// this should be in the plugin, not on this page.

	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree();
	if ( count( $all_categories) > 0 ) {
		$right = array();
		foreach ( $all_categories as $category ) {
			while ( count($right) > 0 && $right[count($right) - 1] < $category->mptt_right ) {
				array_pop($right);
			}
			$pad = count($right)*30;
			$titlelink = sprintf(
				'<a href="%s" title="%s">%s - %d</a>',
				URL::get( 'admin', array( 'page' => 'posts', 'search' => 'category:' . $category->term ) ),
				_t( "Manage content categorized '%s'", array($category->term_display), 'simplecategories' ),
				$category->term_display, $category->id
			);

			$dogs_eat_cats = _t('Contains %d posts.', array( Posts::get(array ('vocabulary'=> array( 'categories:term' => $category->term ), 'count' => 'term' ) ) ), 'simplecategories' );

			// debugging
			$titlelink .= "<h4>{$category->mptt_left} :: {$category->mptt_right}</h4>";
			$dropbutton = '<ul class="dropbutton"><li><a href="'. URL::get( 'admin', array( 'page' => 'categories', 'action' => 'edit', 'category' => $category->id )  ) . '" title="' . _t( "Rename or move '{$category->term_display}'" ) . '">' .
					_t( "Edit" ) . '</a></li><li><a href="' . URL::get( 'admin', array( 'page' => 'categories', 'action' => 'delete', 'category' => $category->id ) ) . '" title="' . _t( "Delete '{$category->term_display}'" ) . '">' . _t( "Delete" ) . '</a></li></ul>';
			echo "\n<div class='item plugin clear' style='border-left: {$pad}px solid #e9e9e9; border-color:#e9e9e9;'><div class='head'>";
			echo "\n$titlelink $dropbutton\n</div><p>$dogs_eat_cats</p></div>";

			$right[] = $category->mptt_right;
		}
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}

?></div>

<?php $theme->display('footer'); ?>
