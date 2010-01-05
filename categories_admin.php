<?php $theme->display('header');?>
 
<?php 
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree( 'term_display' );
// 	Utils::debug( $all_categories );
?>
	<div class="container">
		<form action="<?php URL::out( 'admin', 'page=categories');?>">

		<a href=""><?php _e( "Create a new category" ); ?></a>

		</form>
	</div>

	<div class="container">
<?php
	if ( count( $all_categories) > 0 ) {
		foreach ( $all_categories as $category ) {
			echo "<h3><b>{$category->id}</b>: {$category->term}: {$category->term_display}</h3>";
// 			Utils::debug( $category );
		}
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}
?></div>
 
<?php $theme->display('footer'); ?>
