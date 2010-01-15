<?php $theme->display('header');?>

<?php

	if (isset( $_GET['action'] ) and User::identify()->can( 'manage_categories' ) ) {
		switch( $_GET[ 'action' ] ) {
			case "delete":
die( "<h1>Delete</h1>" );
				break;
			case "edit":
die( "<h1>Edit</h1>" );
				break;
			default:
				// shouldn't come to this.
		}
		// ok, token checked, but is it overkill?
// 		Utils::debug( $_GET );
	}	
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree();
// 	Utils::debug( $all_categories );
?>
	<div class="container">

<?php	echo $form; ?>

	</div>

	<div class="container plugins activeplugins">
<?php
	if ( count( $all_categories) > 0 ) {
		$right = array();
		foreach ( $all_categories as $category ) {
			while ( count($right) > 0 && $right[count($right) - 1] < $category->mptt_right ) {
				array_pop($right);
			}
			$pad = count($right)*5 + 5;
			$rest = 95 - $pad;
			$titlelink = '<a href="' . URL::get( 'admin', array( 'page' => 'posts', 'search' => "category:{$category->term}" ) ). '" title="' . 
					_t( "Manage content categorized '{$category->term_display}'" ) . "\">{$category->term_display}</a>";
			$dropbutton = '<ul class="dropbutton"><li><a href="'. URL::get( 'admin', array( 'page' => 'categories', 'action' => 'edit', 'category' => $category->term )  ) . '" title="' . _t( "Rename or move '{$category->term_display}'" ) . '">' . 
					_t( "Edit" ) . '</a></li><li><a href="' . URL::get( 'admin', array( 'page' => 'categories', 'action' => 'delete', 'category' => $category->term ) ) . '" title="' . _t( "Delete '{$category->term_display}'" ) . '">' . _t( "Delete" ) . '</a></li></ul>';
			echo "\n<div class='item plugin clear'><div class='head'><span class='pct{$pad}'>&nbsp;</span>\n<span class='pct{$rest} last'>$titlelink $dropbutton</span>\n</div></div>";
			$right[] = $category->mptt_right;
		}
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}

?></div>

<?php $theme->display('footer'); ?>
