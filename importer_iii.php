<?php
/*
Plugin Name: Scriblio III Catalog Importer
Plugin URI: http://about.scriblio.net/
Description: Imports catalog content directly from a III web OPAC, no MaRC export/import needed.
Version: 2.7 a
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/
/* Copyright 2006 - 2008 Casey Bisson & Plymouth State University

	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; either version 2 of the License, or 
	(at your option) any later version. 

	This program is distributed in the hope that it will be useful, 
	but WITHOUT ANY WARRANTY; without even the implied warranty of 
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
	GNU General Public License for more details. 

	You should have received a copy of the GNU General Public License 
	along with this program; if not, write to the Free Software 
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA 
*/ 


/*
	Revised by K.T. Lam (lblkt@ust.hk), Head of Library Systems, The Hong Kong University of Science and Technology Library
	Purpose: to enhance Scriblio's CJK support and to make it works with HKUST's INNOPPAC.
	Date: 13 November 2007; 22 November 2007; 17 December 2007; 29 December 2007; 14 January 2008; 13 May 2008;

*/



// The importer 
class ScribIII_import { 
	var $importer_code = 'scribimporter_iii'; 
	var $importer_name = 'Scriblio III Catalog Importer'; 
	var $importer_desc = 'Imports catalog content directly from a III web OPAC, no MaRC export/import needed. <a href="http://about.scriblio.net/wiki/">Documentation here</a>.'; 
	 
	// Function that will handle the wizard-like behaviour 
	function dispatch() { 
		if (empty ($_GET['step'])) 
			$step = 0; 
		else 
			$step = (int) $_GET['step']; 

		// load the header
		$this->header();

		switch ($step) { 
			case 0 :
				$this->greet();
				break;
			case 1 : 
				$this->iii_start(); 
				break;
			case 2:
				$this->iii_getrecords(); 
				break; 
			case 3:
				$this->ktnxbye(); 
				break; 
		} 

		// load the footer
		$this->footer();
	} 

	function header() {
		echo '<div class="wrap">';
		echo '<h2>'.__('Scriblio III Importer').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		$prefs = get_option('scrib_iiiimporter');
		$prefs['scrib_iii-warnings'] = array();
		$prefs['scrib_iii-errors'] = array();
		$prefs['scrib_iii-record_end'] = '';
		$prefs['scrib_iii-records_harvested'] = 0;
		update_option('scrib_iiiimporter', $prefs);

		$prefs = get_option('scrib_iiiimporter');

		echo '<p>'.__('Howdy! Start here to import records from a Innovative Interfaces (III) ILS system into Scriblio.').'</p>';

		echo '<form name="myform" id="myform" action="admin.php?import='. $this->importer_code .'&amp;step=1" method="post">';
?>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('The Innopac base hostname', 'scrib') ?></th>
<td>
<input name="scrib_iii-sourceinnopac" type="text" id="scrib_iii-sourceinnopac" value="<?php echo attribute_escape( $prefs['scrib_iii-sourceinnopac'] ); ?>" /><br />
example: lola.plymouth.edu
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('The source prefix', 'scrib') ?></th>
<td>
<input name="scrib_iii-sourceprefix" type="text" id="scrib_iii-sourceprefix" value="<?php echo attribute_escape( $prefs['scrib_iii-sourceprefix'] ); ?>" /><br />
example: bb (must be two characters, a-z and 0-9 accepted)
</td>
</tr>

</table>
<?php

		echo '<p>'.__('All Scriblio records have a &#039;sourceid,&#039; a unique alphanumeric string that&#039;s used to avoid creating duplicate records and, in some installations, link back to the source system for current availability information.').'</p>';
		echo '<p>'.__('The sourceid is made up of two parts: the prefix that you assign, and the bib number from the Innopac. Theoretically, you chould gather records from 1,296 different systems, it&#039;s a big world.').'</p>';

?>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Harvest records with', 'scrib') ?></th>
<td>
<input name="scrib_iii-require" type="text" id="scrib_iii-require" value="<?php echo attribute_escape( $prefs['scrib_iii-require'] ); ?>" /><br />
example: My Library Location Name (optional; leave blank to harvest any record)<br />
uses <a href="http://php.net/strpos">strpos</a> matching rules
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Ignore records with', 'scrib') ?></th>
<td>
<input name="scrib_iii-reject" type="text" id="scrib_iii-reject" value="<?php echo attribute_escape( $prefs['scrib_iii-reject'] ); ?>" /><br />
example: No Such Record<br />
uses <a href="http://php.net/strpos">strpos</a> matching rules 
</td>
</tr>
</table>

<table class="form-table">
<tr>
<th scope="row" class="th-full">
<label for="scrib_iii-capitalize_titles"><input type="checkbox" name="scrib_iii-capitalize_titles" id="scrib_iii-capitalize_titles" value="1" <?php if( !empty( $prefs['scrib_iii-capitalize_titles'] )) echo 'CHECKED'; ?> /> Capitalize titles</label>
</th>
</tr>
<tr>

<tr>
<th scope="row" class="th-full">
<label for="scrib_iii-convert_encoding"><input type="checkbox" name="scrib_iii-convert_encoding" id="scrib_iii-convert_encoding" value="1" <?php if( !empty( $prefs['scrib_iii-convert_encoding'] )) echo 'CHECKED'; ?> /> Convert character encoding to UTF8</label>
</th>
</tr>
</table>
<?php
		echo '<p>'.__('Many III web OPACs use encodings other than <a href="http://en.wikipedia.org/wiki/UTF-8">UTF8</a>. This option will attempt to convert the characters to UTF8 so that accented and non-latin characters are properly represented. However, do not use this option if your web OPAC is configured to output UTF8 characters.').'</p>';

		if(!function_exists('mb_convert_encoding')){
			echo '<br /><br />';	
			echo '<p>'.__('This PHP install does not support <a href="http://php.net/manual/en/ref.mbstring.php">multibyte string functions</a>, including <a href="http://php.net/mb_convert_encoding">mb_convert_encoding</a>. Without that function, this importer can&#039;t convert the character encoding from records in the ILS into UTF-8. Accented characters may not import correctly.').'</p>';
		}


		echo '<p class="submit"><input type="submit" name="next" value="'.__('Next &raquo;').'" /></p>';
		echo '</form>';


		echo '<br /><br />';	
		echo '<form action="admin.php?import=scribimporter&amp;step=3" method="post">';
		echo '<p class="submit">or jump immediately to <input type="submit" name="next" value="'.__('Publish Harvested Records &raquo;').'" /> <br />'. __('(goes to default Scriblio importer)').'</p>';
		echo '</form>';
		echo '</div>';
	}

	function ktnxbye() {
		echo '<div class="narrow">';
		echo '<p>'.__('All done.').'</p>';
		echo '</div>';
	}

	function iii_start(){
//note to HKUST: changed from $_POST to $_REQUEST so the script accepts either post or get variables.
		if(empty( $_REQUEST['scrib_iii-sourceprefix'] ) || empty( $_REQUEST['scrib_iii-sourceinnopac'] )){
			echo '<h3>'.__('Sorry, there has been an error.').'</h3>';
			echo '<p>'.__('Please complete all fields.').'</p>';
			return;
		}

		if( 2 <> strlen( ereg_replace('[^a-z|A-Z|0-9]', '', $_REQUEST['scrib_iii-sourceprefix'] ))){
			echo '<h3>'.__('Sorry, there has been an error.').'</h3>';
			echo '<p>'.__('The source prefix must be exactly two characters, a-z and 0-9 accepted.').'</p>';
			return;
		}

		// save these settings so we can try them again later
		$prefs = get_option('scrib_iiiimporter');
		$prefs['scrib_iii-sourceprefix'] = strtolower(ereg_replace('[^a-z|A-Z|0-9]', '', $_REQUEST['scrib_iii-sourceprefix']));
		stripslashes($_REQUEST['scrib_iii-sourceprefix']);
		$prefs['scrib_iii-sourceinnopac'] = ereg_replace('[^a-z|A-Z|0-9|-|\.]', '', $_REQUEST['scrib_iii-sourceinnopac']);
		$prefs['scrib_iii-convert_encoding'] = isset( $_REQUEST['scrib_iii-convert_encoding'] );

		$prefs['scrib_iii-require'] = $_REQUEST['scrib_iii-require'];
		$prefs['scrib_iii-reject'] = $_REQUEST['scrib_iii-reject'];
		$prefs['scrib_iii-capitalize_titles'] = isset( $_REQUEST['scrib_iii-capitalize_titles'] );
		update_option('scrib_iiiimporter', $prefs);


		$this->iii_options();
	}

	function iii_options( $record_start = FALSE, $record_end = FALSE ){
		global $wpdb, $scrib;

		$prefs = get_option('scrib_iiiimporter');

		if( !$record_start )
			$record_start = ( 100 * round( $wpdb->get_var( 'SELECT SUBSTRING( source_id, 3 ) FROM '. $scrib->harvest_table .' WHERE source_id LIKE "'. $prefs['scrib_iii-sourceprefix'] .'%" ORDER BY source_id DESC LIMIT 1' ) / 100 ));

		if( !$record_end )
			$record_end = $record_start + 1000;

		echo '<form name="myform" id="myform" action="admin.php?import='. $this->importer_code .'&amp;step=2" method="post">';
?>
<table class="form-table">

<tr valign="top">
<th scope="row"><?php _e('Start with bib number', 'scrib') ?></th>
<td>
<input type="text" name="scrib_iii-record_start" id="scrib_iii-record_start" value="<?php echo attribute_escape( $record_start ); ?>" /><br />
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('End', 'scrib') ?></th>
<td>
<input type="text" name="scrib_iii-record_end" id="scrib_iii-record_end" value="<?php echo attribute_escape( $record_end ); ?>" />
</td>
</tr>

</table>
<table class="form-table">

<tr>
<th scope="row" class="th-full">
<label for="scrib_iii-debug"><input type="checkbox" name="scrib_iii-debug" id="scrib_iii-debug" value="1" = /> Debug mode</label>
</th>
</tr>
<tr>
</table>

<input type="hidden" name="scrib_iii-sourceprefix" id="scrib_iii-sourceprefix" value="<?php echo attribute_escape( $prefs['scrib_iii-sourceprefix'] ); ?>" />
<input type="hidden" name="scrib_iii-sourceinnopac" id="scrib_iii-sourceinnopac" value="<?php echo attribute_escape( $prefs['scrib_iii-sourceinnopac'] ); ?>" />
<?php
		echo '<p class="submit"><input type="submit" name="next" value="'.__('Next &raquo;').'" /></p>';
		echo '</form>';

//exit;
	}

	function iii_getrecords(){
		global $wpdb, $scrib_import;
//note to HKUST: changed from $_POST to $_REQUEST so the script accepts either post or get variables.

		if(empty($_REQUEST['scrib_iii-sourceprefix']) || empty($_REQUEST['scrib_iii-sourceinnopac']) || empty($_REQUEST['scrib_iii-record_start'])){
			echo '<p>'.__('Sorry, there has been an error.').'</p>';
			echo '<p><strong>Please complete all fields</strong></p>';
			return;
		}

		// save these settings so we can try them again later
		$prefs = get_option('scrib_iiiimporter');
		$prefs['scrib_iii-record_start'] = (int) $_REQUEST['scrib_iii-record_start'];
		$prefs['scrib_iii-record_end'] = (int) $_REQUEST['scrib_iii-record_end'];
		update_option('scrib_iiiimporter', $prefs);

		$interval = 25;
		if( !$prefs['scrib_iii-record_end'] || ( $prefs['scrib_iii-record_end'] == $prefs['scrib_iii-record_start'] ))
			$_REQUEST['scrib_iii-debug'] = TRUE;
		if( !$prefs['scrib_iii-record_end'] || ( $prefs['scrib_iii-record_end'] - $prefs['scrib_iii-record_start'] < $interval ))
			$interval = $prefs['scrib_iii-record_end'] - $prefs['scrib_iii-record_start'];
		if( $prefs['scrib_iii-record_end'] - $prefs['scrib_iii-record_start'] < 1 )
			$interval = 0;

		ini_set('memory_limit', '1024M');
		set_time_limit(0);
		ignore_user_abort(TRUE);
		error_reporting(E_ERROR);

		if( !empty( $_REQUEST['scrib_iii-debug'] )){

			$host =  $prefs['scrib_iii-sourceinnopac'];
			$bibn = (int) $prefs['scrib_iii-record_start'];

			echo '<h3>The III Record:</h3><pre>';			
			echo $this->iii_get_record($host, $bibn);
			echo '</pre><h3>The Tags and Display Record:</h3><pre>';

			$test_pancake = $this->iii_parse_record( $this->iii_get_record( $host, $bibn ), $bibn );
			print_r( $test_pancake );
			echo '</pre>';
			
//			echo '<h3>The Raw Excerpt:</h3>'. $scrib_import->the_excerpt( $test_pancake ) .'<br /><br />';
//			echo '<h3>The Raw Content:</h3>'. $scrib_import->the_content( $test_pancake ) .'<br /><br />';
			echo '<h3>The SourceID: '. $test_pancake['_sourceid'] .'</h3>';
			
			// bring back that form
			echo '<h2>'.__('III Options').'</h2>';
			$this->iii_options();
		
		}else{
			// import with status
			$host =  ereg_replace('[^a-z|A-Z|0-9|-|\.]', '', $_REQUEST['scrib_iii-sourceinnopac']);

			$count = 0;
			echo "<p>Reading a batch of $interval records from {$prefs['scrib_iii-sourceinnopac']}. Please be patient.<br /><br /></p>";
			echo '<ol>';
			for($bibn = $prefs['scrib_iii-record_start'] ; $bibn < ($prefs['scrib_iii-record_start'] + $interval) ; $bibn++ ){
				if($record = $this->iii_get_record( $host , $bibn )){
					$bibr = $this->iii_parse_record( $record , $bibn );
					echo "<li>{$bibr['the_title']} {$bibr['_sourceid']}</li>";
					$count++;
				}
			}
			echo '</ol>';
			
			$prefs['scrib_iii-warnings'] = array_merge($prefs['scrib_iii-warnings'], $this->warn);
			$prefs['scrib_iii-errors'] = array_merge($prefs['scrib_iii-errors'], $this->error);
			$prefs['scrib_iii-records_harvested'] = $prefs['scrib_iii-records_harvested'] + $count;
			update_option('scrib_iiiimporter', $prefs);

			if( $bibn < $prefs['scrib_iii-record_end'] ){
				$prefs['scrib_iii-record_start'] = $prefs['scrib_iii-record_start'] + $interval;
				update_option('scrib_iiiimporter', $prefs);

				$this->iii_options( $prefs['scrib_iii-record_start'], $prefs['scrib_iii-record_end'] );
				?>
				<div class="narrow"><p><?php _e("If your browser doesn't start loading the next page automatically click this link:"); ?> <a href="javascript:nextpage()"><?php _e("Next Records"); ?></a> </p>
				<script language='javascript'>
				<!--
	
				function nextpage() {
					document.getElementById('myform').submit();
				}
				setTimeout( "nextpage()", 1250 );
	
				//-->
				</script>
				</div>
<?php
				echo '<pre>';
				print_r( $wpdb->queries );
				echo '<br /><br />';
				print_r( $scrib_import->queries );
				echo '</pre>';
				?><?php echo get_num_queries(); ?> queries. <?php timer_stop(1); ?> seconds. <?php
			}else{
				$this->iii_done();
				?>
				<script language='javascript'>
				<!--
					window.location='#complete';
				//-->
				</script>
				</div>
				<?php
			}
		}
	}

	function iii_get_record($host, $bibn){
		$prefs = get_option('scrib_iiiimporter');


		// get the regular web-view of the record and 
		// see if it matches the require/reject preferences
		$test_record = file_get_contents('http://'. $host .'/record=b'. $bibn);

		if( $prefs['scrib_iii-require'] && !strpos( $test_record, $prefs['scrib_iii-require'] ))
			return(FALSE);

		if( $prefs['scrib_iii-reject'] && strpos( $test_record, $prefs['scrib_iii-reject'] ))
			return(FALSE);


		// now get the MARC view of the record
		$recordurl = 'http://'. $host .'/search/.b'. $bibn .'/.b'. $bibn .'/1%2C1%2C1%2CB/marc~b'. $bibn;

//note to HKUST: Added an option to enabled utf8 encoding
		if( $prefs['scrib_iii-convert_encoding'] && function_exists( 'mb_convert_encoding' ))
			$record = mb_convert_encoding( file_get_contents( $recordurl ), 'UTF-8', 'LATIN1, ASCII, ISO-8859-1, UTF-8');
		else
			$record = file_get_contents($recordurl);

		if(!empty($record)){
			preg_match('/<pre>([^<]*)/', $record, $stuff);
//Start HKUST Customization
			//Create Tag 999
			$strline = '';

			//Check exists of ERM resources
			$matchcount=preg_match('/<!-- BEGIN ERM RESOURCE TABLE -->/', $record, $stuffdummy1);
			if ($matchcount>0) {
				$strline .= "|fE-Resource|lONLINE RESOURCE";
			}

			//Capture Item Locations
			//e.g. "<!-- field 1 -->&nbsp; <a href="http://library.ust.hk/info/maps/blink/1f-archive.html">UNIVERSITY ARCHIVES</a>"
			$matchcount = preg_match_all( '/<!-- field 1 -->.*>(.+)</', $record, $matches, PREG_SET_ORDER );
			if ( 0 < $matchcount ) {
				foreach( $matches as $match ){
					$strline .= '|l'.strtoupper( $match[1] );
				}
			}

			if ( strlen( $strline ))
				return( $stuff[1].'999    '. $strline ."\n");
			else
				return( $stuff[1] );
//End HKUST Customization
		}
		$this->error = 'Host unreachable or no parsable data found for record number '. $bibn .'.';
		return( FALSE );
	}

	function iii_done(){
		$prefs = get_option('scrib_iiiimporter');

		// click next
		echo '<div class="narrow">';

		if(count($prefs['scrib_iii-warnings'])){
			echo '<h3 id="warnings">Warnings</h3>';
			echo '<a href="#complete">bottom</a> &middot; <a href="#errors">errors</a>';
			echo '<ol><li>';
			echo implode($prefs['scrib_iii-warnings'], '</li><li>');
			echo '</li></ol>';
		}

		if(count($prefs['scrib_iii-errors'])){
			echo '<h3 id="errors">Errors</h3>';
			echo '<a href="#complete">bottom</a> &middot; <a href="#warnings">warnings</a>';
			echo '<ol><li>';
			echo implode($prefs['scrib_iii-errors'], '</li><li>');
			echo '</li></ol>';
		}		

		echo '<h3 id="complete">'.__('Processing complete.').'</h3>';
		echo '<p>'. $prefs['scrib_iii-records_harvested'] .' '.__('records harvested.').' with '. count($prefs['scrib_iii-warnings']) .' <a href="#warnings">warnings</a> and '. count($prefs['scrib_iii-errors']) .' <a href="#errors">errors</a>.</p>';

		echo '<p>'.__('Continue to the next step to publish those harvested catalog entries.').'</p>';

		echo '<form action="admin.php?import=scribimporter&amp;step=3" method="post">';
		echo '<p class="submit"><input type="submit" name="next" value="'.__('Publish Harvested Records &raquo;').'" /> <br />'. __('(goes to default Scriblio importer)').'</p>';
		echo '</form>';

		echo '</div>';
	}

	function iii_parse_row($lineray){
		$marcrow = array();
		unset($lineray[0]);
		foreach($lineray as $element){
			$count[$element{0}]++;
			$elementname = $element{0}.$count[$element{0}];
			$marcrow[$elementname] = trim( substr( $element, 1 ));
		}
		return($marcrow);
	}

	function iii_parse_record($marcrecord, $bibn){
		global $scrib;
		$prefs = get_option('scrib_iiiimporter');

		$spare_keys = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' );

		$atomic = $subjtemp = array();
		
		$marcrecord = str_replace("\n       ", ' ', $marcrecord);
		
		$details = explode( "\n", $marcrecord );
		array_pop($details);
		array_shift($details);

		$details[0] = str_replace('LEADER ', '000    ', $details[0]);
		foreach($details as $line){		
			unset($lineray);
			unset($marc);

			$line = trim($line);

			//handle romanized tags with subfield 6 - to avoid using it as the main entry, so that 880 data is used instead
			$line = preg_replace('/^245(.*?\|6880-)/', '246\\1', $line);
			$line = preg_replace('/^1(\d\d.*?\|6880-)/', '7\\1', $line);
			$line = preg_replace('/^250(.*?\|6880-)/', '950\\1', $line);
			$line = preg_replace('/^260(.*?\|6880-)/', '960\\1', $line);

			//handle 880 tags with subfield 6
			$line = preg_replace('/^880(.*?)\|6(\d\d\d)-/', '\\2\\1|6880-', $line);

			//Remove subfield 6 containing "880-.."
			$line = preg_replace('/\|6880-.*?\|/', '|', $line);

			//Remove the extra space in $line in front of the first subfield delimiter
			$line = preg_replace('/^.{7} /', '\\1', $line);

			//Insert subfield delimiter and subfield code "a" if it is not present - for non-00X tags
			$line = preg_replace('/^([0][1-9]\d.{4})([^\|])/', '\\1|a\\2', $line);
			$line = preg_replace('/^([1-9]\d{2}.{4})([^\|])/', '\\1|a\\2', $line);

			//Construct $lineray
			if (substr($line,7,1)=="|") {
				$lineray = substr($line, 0, 3) . '|' . substr($line, 4, 2) . substr($line, 7);
			}else{
				$lineray = substr($line, 0, 3) . '|' . substr($line, 4, 2) . '|a' . substr($line, 7);
			}

			$lineray = explode('|', ereg_replace('\.$', '', $lineray));
			unset($lineray[1]);

			if($lineray[0] > 99)
				$line = trim( $line );

			// languages
			if( $lineray[0] == '008' ){
				$atomic['published'][0]['lang'][] = $scrib->meditor_sanitize_punctuation( substr( $lineray[2], 36,3 ));

			}else if( $lineray[0] == '041' ){
				$marc = $this->iii_parse_row( $lineray );
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'e':
						case 'f':
						case 'g':
						case 'h':
							$atomic['published'][0]['lang'][] = $scrib->meditor_sanitize_punctuation( $val );
					}
				}



			// authors
			}else if(($lineray[0] == 100) || ($lineray[0] == 700) || ($lineray[0] == 110) || ($lineray[0] == 710) || ($lineray[0] == 111) || ($lineray[0] == 711)){
				$marc = $this->iii_parse_row($lineray);
				$temp = $marc['a1'];
				unset( $temp_role );
				if(($lineray[0] == 100) || ($lineray[0] == 700)){
					if($marc['d1'])
						$temp .= ' ' . $marc['d1'];
					if($marc['e1'])
						$temp_role = ' ' . $marc['e1'];
				}else if(($lineray[0] == 110) || ($lineray[0] == 710)){
					if ($marc['b1']) {
						$temp .= ' ' . $marc['b1'];
					}
				}else if(($lineray[0] == 111) || ($lineray[0] == 711)){
					if ($marc['n1']) {
						$temp .= ' ' . $marc['n1'];
					}
					if ($marc['d1']) {
						$temp .= ' ' . $marc['d1'];
					}
					if ($marc['c1']) {
						$temp .= ' ' . $marc['c1'];
					}
				}
				$temp = ereg_replace('[,|\.]$', '', $temp);
				$atomic['creator'][] = array( 'name' => $scrib->meditor_sanitize_punctuation( $temp ), 'role' => $temp_role ? $temp_role : 'Author' );

				//handle title in name
				$temp = '';
				if ($marc['t1']) {
					$temp .= ' ' . $marc['t1'];
				}
				if ($marc['n1']) {
					$temp .= ' ' . $marc['n1'];
				}
				if ($marc['p1']) {
					$temp .= ' ' . $marc['p1'];
				}
				if ($marc['l1']) {
					$temp .= ' ' . $marc['l1'];
				}
				if ($marc['k1']) {
					$temp .= ' ' . $marc['k1'];
				}
				if ($marc['f1']) {
					$temp .= ' ' . $marc['f1'];
				}
				$temp = ereg_replace('[,|\.]$', '', $temp);
				if (strlen($temp) >0) {
					$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				}

			//Standard Numbers
			}else if($lineray[0] == 10){
				$marc = $this->iii_parse_row($lineray);
				$atomic['idnumbers'][] = array( 'type' => 'lccn', 'id' => $marc['a1'] );

			}else if($lineray[0] == 20){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim($marc['a1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'isbn', 'id' => $temp );

			}else if($lineray[0] == 22){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim($marc['a1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );

				$temp = trim($marc['y1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );

				$temp = trim($marc['z1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );
			
			//Call Numbers
			}else if($lineray[0] == 50){
				$marc = $this->iii_parse_row($lineray);
				$atomic['callnumbers'][] = array( 'type' => 'lc', 'number' => implode( ' ', $marc ));
			}else if($lineray[0] == 82){
				$marc = $this->iii_parse_row($lineray);
				$atomic['callnumbers'][] = array( 'type' => 'dewey', 'number' => str_replace( '/', '', $marc['a1'] ));

			//Titles
			}else if($lineray[0] == 130){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $marc['a1'] ));
			}else if($lineray[0] == 245){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim(ereg_replace('/$', '', $marc['a1']) .' '. trim(ereg_replace('/$', '', $marc['b1']) .' '. trim(ereg_replace('/$', '', $marc['n1']) .' '. trim(ereg_replace('/$', '', $marc['p1'])))));
				$atomic['title'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				$atomic['attribution'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $marc['c1'] ));
			}else if($lineray[0] == 240){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));
			}else if($lineray[0] == 246){
				$marc = $this->iii_parse_row( $lineray );
				$temp = trim(ereg_replace('/$', '', $marc['a1']) .' '. trim(ereg_replace('/$', '', $marc['b1']) .' '. trim(ereg_replace('/$', '', $marc['n1']) .' '. trim(ereg_replace('/$', '', $marc['p1'])))));
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
			}else if(($lineray[0] > 719) && ($lineray[0] < 741)){
				$marc = $this->iii_parse_row($lineray);
				$temp = $marc['a1'];
				if ($marc['n1']) {
					$temp .= ' ' .$marc['n1'];
				}
				if ($marc['p1']) {
					$temp .= ' ' . $marc['p1'];
				}
				$temp = ereg_replace('[,|\.|;]$', '', $temp);
				if (strlen($temp) >0) {
					$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				}

			//Edition
			}else if($lineray[0] == 250){
				$marc = $this->iii_parse_row($lineray);
				$atomic['published'][0]['edition'] = $scrib->meditor_sanitize_punctuation( implode(' ', $marc));

			//Dates and Publisher
			}else if($lineray[0] == 260){
				$marc = $this->iii_parse_row($lineray);
				if($marc['b1']){
					$atomic['published'][0]['publisher'][] = $scrib->meditor_sanitize_punctuation($marc['b1']);
				}

				if($marc['c1']){
					$temp ="";
					//match for year pattern, such as "1997"
					$matchcount=preg_match('/(\d\d\d\d)/',$marc['c1'], $matches);
					if ($matchcount>0) {
						$temp = $matches[1];
					}else {
						//match for mingguo year pattern  (in traditional chinese character)
						$matchcount=preg_match('/\xE6\xB0\x91\xE5\x9C\x8B(\d{2})/',$marc['c1'], $matches);
						if ($matchcount>0) {
							$temp = strval(intval($matches[1])+1911);
						} else {
							//match for mingguo year pattern (in simplified chinese character)
							$matchcount=preg_match('/\xE6\xB0\x91\xE5\x9B\xBD(\d{2})/',$marc['c1'], $matches);
							if ($matchcount>0) {
								$temp = strval(intval($matches[1])+1911);
							}
						}
					}
					if ($temp) {
						$atomic['published'][0]['cy'][] = $temp;
					}
				}
			}else if($lineray[0] == 5){
				$atomic['_acqdate'][] = $line{7}.$line{8}.$line{9}.$line{10} .'-'. $line{11}.$line{12} .'-'. $line{13}.$line{14};
			}else if($lineray[0] == 8){
				$temp = intval(substr($line, 14, 4));
				if($temp)
					$atomic['published'][0]['cy'][] = preg_replace('/[^\d]/', '0' ,substr($line, 14, 4));
			
			//Subjects
			// tag 600 - Person
			}else if($lineray[0] == '600'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'q':
							$subjtemp[] = array( 'type' => 'person', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'd':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 648 - Time
			}else if($lineray[0] == '648'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 650 - Topical Terms
			}else if( $lineray[0] == '650' ){
				if( 6 == $line[5] )
					continue;

				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'c':
						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'd':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;
					}
				}

			// tag 651 - Geography
			}else if($lineray[0] == '651'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'e':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 654 - Topical Terms
			}else if($lineray[0] == '654'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'f':
						case 'g':
						case 'h':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 655 - Genre
			}else if($lineray[0] == '655'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'v':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 662 - Geography
			}else if($lineray[0] == '662'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'f':
						case 'g':
						case 'h':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'e':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;
					}
				}

			// everything else
			}else if(($lineray[0] > 599) && ($lineray[0] < 700)){
				if( 6 == $line[5] )
					continue;

				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'v':
						case 'k':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}


			//URLs
			}else if($lineray[0] == 856){
				$marc = $this->iii_parse_row($lineray);
				unset($temp);
				$temp['href'] = $temp['title'] = str_replace(' ', '', $marc['u1']);
				$temp['title'] = trim( parse_url( $temp['href'] , PHP_URL_HOST ), 'www.' );
				if($marc['31'])
					$temp['title'] = $marc['31'];
				if($marc['z1'])
					$temp['title'] = $marc['z1'];
				$atomic['linked_urls'][] = array( 'name' => $temp['title'], 'href' => $temp['href'] );

			//Notes
//			}else if(($lineray[0] > 299) && ($lineray[0] < 400)){
//				$marc = $this->iii_parse_row($lineray);
//				$atomic['physdesc'][] = implode(' ', array_values($marc));

			}else if(($lineray[0] > 399) && ($lineray[0] < 490)){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));

			}else if(($lineray[0] > 799) && ($lineray[0] < 841)){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));

			}else if(($lineray[0] > 499) && ($lineray[0] < 600)){
				$line = substr($line, 9);
				if($lineray[0] == 504)
					continue;
				if($lineray[0] == 505){
					$atomic['text'][] = array( 'type' => 'contents', 'content' => ( '<ul><li>'. implode( "</li>\n<li>", array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), explode( '--', str_replace( array( '|t', '|r' , '|g' ), ' ', preg_replace( '/-[\s]+-/', '--', $line )))) ) .'</li></ul>' ));
					continue;
				}

				//strip the subfield delimiter and codes
				$line = preg_replace('/\|[0-9|a-z]/', ' ', $line);
				$atomic['text'][] = array( 'type' => 'notes', 'content' => $scrib->meditor_sanitize_punctuation( $line ));
			}
			

			// pick up the subjects parsed above
			if( count( $subjtemp )){
				$temp = array();
				foreach( $subjtemp as $key => $val ){
					$temp[ $spare_keys[ $key ] .'_type' ] = $val['type']; 
					$temp[ $spare_keys[ $key ] ] = $val['val']; 
				}
				$atomic['subject'][] = $temp;
			}

			//Format
			if(($lineray[0] > 239) && ($lineray[0] < 246)){
				$marc = $this->iii_parse_row($lineray);
				$temp = ucwords(strtolower(str_replace('[', '', str_replace(']', '', $marc['h1']))));
				
				if(eregi('^book', $temp)){
					$atomic['format'][] = array( 'a' => 'Book' );

				}else if(eregi('^micr', $temp)){
					$atomic['format'][] = array( 'a' => 'Microform' );

				}else if(eregi('^electr', $temp)){
					$atomic['format'][] = array( 'a' => 'E-Resource' );

				}else if(eregi('^vid', $temp)){
					$atomic['format'][] = array( 'a' => 'Video' );
				}else if(eregi('^motion', $temp)){
					$atomic['format'][] = array( 'a' => 'Video' );

				}else if(eregi('^audi', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio' );
					$format = 'Audio';
				}else if(eregi('^cass', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Cassette' );
				}else if(eregi('^phono', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Phonograph' );
				}else if(eregi('^record', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Phonograph' );
				}else if(eregi('^sound', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio' );

				}else if(eregi('^carto', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}else if(eregi('^map', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}else if(eregi('^globe', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}
			}

			if($lineray[0] == '008' && (substr($lineray[2], 22,1) == 'p' || substr($lineray[2], 22,1) == 'n')){
				$atomic['format'][] = array( 'a' => 'Journal' );
			}
/*
disabled for now, no records to test against
//Start HKUST Customization
			// Handle tag 999 - for locations and formats
			if ($lineray[0] == '999'){
				$marc = $this->iii_parse_row($lineray);
				foreach($marc as $key=>$subfield){
					if ( substr($key,0,1)=='l' ) {
						$atomic['loc'][] = $subfield;
					}else if( substr($key,0,1)=='f' ) {
						if( !$atomic['format'][0] ){
							$atomic['format'][0] = 'Book';
							$atomic['formats'][0] = 'Book';
						}
						$atomic['format'][] = $subfield;
						$atomic['formats'][] = $subfield;
					}
				}
				$atomic['loc']=array_unique($atomic['loc']);
				$atomic['format']=array_unique($atomic['format']);
				$atomic['formats']=array_unique($atomic['formats']);
			}
//End HKUST Customization
*/
		}
		// end the big loop



		// Records without _acqdates are reserves by course/professor
		// we _can_ import them, but they don't have enough info
		// to be findable or display well.
		if(!$atomic['_acqdate'][0] && !$atomic['creator'][0]){
			$this->warn = 'Record number '. $bibn .' contains no catalog date or author info, skipped.';
			return( FALSE );
		}
		if(count( $atomic ) < 4){
			$this->warn = 'Record number '. $bibn .' has too little cataloging data, skipped.';
			return( FALSE );
		}

		// sanity check the pubyear
		foreach( array_filter( array_unique( $atomic['published'][0]['cy'] )) as $key => $temp )
			if( $temp > date('Y') + 2 )
				unset( $atomic['published'][0]['cy'][$key] );
		$atomic['published'][0]['cy'] = array_shift( $atomic['published'][0]['cy'] );
		if( empty( $atomic['published'][0]['cy'] ))
			$atomic['published'][0]['cy'] = date('Y') - 1;


		if(!$atomic['format'][0])
			$atomic['format'][0] = array( 'a' => 'Book' );

		if( $atomic['alttitle'] ){
			$atomic['title'] = array_merge( $atomic['title'], $atomic['alttitle'] );
			unset( $atomic['alttitle'] );
		}

		// clean up published
		if( isset( $atomic['published'][0]['lang'] ))
			$atomic['published'][0]['lang'] = array_shift( array_filter( $atomic['published'][0]['lang'] ));
		if( isset( $atomic['published'][0]['publisher'] ))
			$atomic['published'][0]['publisher'] = array_shift( array_filter( $atomic['published'][0]['publisher'] ));

		// unique the values
		foreach( $atomic as $key => $val )
			$atomic[ $key ] = $scrib->array_unique_deep( $atomic[ $key ] );

		// possibly capitalize titles
		if( $prefs['scrib_iii-capitalize_titles'] )
			foreach( $atomic['title'] as $key => $val )
				$atomic['title'][ $key ]['a'] = ucwords( $val['a'] );

		// insert the sourceid
		$atomic['_sourceid'] = substr( ereg_replace('[^a-z|0-9]', '', strtolower( $_REQUEST['scrib_iii-sourceprefix'] )), 0, 2 ) . $bibn;
		$atomic['idnumbers'][] = array( 'type' => 'sourceid', 'id' => $atomic['_sourceid'] );

		// sanity check the _acqdate
		$atomic['_acqdate'] = array_unique($atomic['_acqdate']);
		foreach( $atomic['_acqdate'] as $key => $temp )
			if( strtotime( $temp ) > strtotime( date('Y') + 2 ))
				unset( $atomic['_acqdate'][$key] );
		$atomic['_acqdate'] = array_values( $atomic['_acqdate'] );
		if( !isset( $atomic['_acqdate'][0] ))
			if( isset( $atomic['pubyear'][0] ))
				$atomic['_acqdate'][0] = $atomic['pubyear'][0] .'-01-01';
			else
				$atomic['_acqdate'][0] = ( date('Y') - 1 ) .'-01-01';
		$atomic['_acqdate'] = $atomic['_acqdate'][0];

		if( !empty( $atomic['title'] ) && !empty( $atomic['_sourceid'] )){
			foreach( $atomic as $ak => $av )
				foreach( $av as $bk => $bv )
					if( is_array( $bv ))
						$atomic[ $ak ][ $bk ] = array_merge( $bv, array( 'src' => 'sourceid:'. $atomic['_sourceid'] ));

			$scrib->import_insert_harvest( $atomic );
			return( $atomic );
		}else{
			$this->error = 'Record number '. $bibn .' couldn&#039;t be parsed.';
			return(FALSE);
		}

	}

	// Default constructor 
	function ScribIII_import() {
		// nothing
	} 
} 

// Instantiate and register the importer 
include_once(ABSPATH . 'wp-admin/includes/import.php'); 
if(function_exists('register_importer')) { 
	$scribiii_import = new ScribIII_import(); 
	register_importer($scribiii_import->importer_code, $scribiii_import->importer_name, $scribiii_import->importer_desc, array (&$scribiii_import, 'dispatch')); 
} 

add_action('activate_'.plugin_basename(__FILE__), 'scribiii_importer_activate'); 

function scribiii_importer_activate() { 
	global $wp_db_version, $scribiii_import; 
	 
	// Deactivate on pre 2.3 blogs 
	if($wp_db_version<6075) { 
		$current = get_settings('active_plugins'); 
		array_splice($current, array_search( plugin_basename(__FILE__), $current), 1 ); 
		update_option('active_plugins', $current); 
		do_action('deactivate_'.plugin_basename(__FILE__));		 
		return(FALSE);
	}
} 

?>