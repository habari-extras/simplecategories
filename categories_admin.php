<?php $theme->display('header');?>
 
<?php 
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree( 'term_display' );
// 	Utils::debug( $all_categories );
?>
	<div class="container">

<?php	echo $form; ?>

	</div>

	<div class="container">
<?php
	if ( count( $all_categories) > 0 ) { ?>
<table><tr><th>ID</th><th>term</th><th>term_display</th></tr>
<?php 		foreach ( $all_categories as $category ) {
			echo "<tr><td><b>{$category->id}</b></td><td>{$category->term}</td><td>{$category->term_display}</td></tr>";
		}
?></table><?php
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}


?></div>
 
<?php $theme->display('footer'); ?>
